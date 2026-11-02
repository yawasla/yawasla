<?php

declare(strict_types=1);

namespace Yawasla\Database;

use LogicException;
use PDO;
use RuntimeException;

final class SQLiteAdapter
{
    private const NAME = '(?:`[^`]+`|\[[^\]]+\]|\x1A\d+\x1A|\w+)';

    private const CONSTRAINT = '/^(?:CONSTRAINT|PRIMARY\s+KEY|UNIQUE|CHECK|FOREIGN\s+KEY)\b/i';

    /** @var list<string> */
    private array $strings = [];

    /** @var list<string> */
    private array $tables = [];

    public function __construct(private PDO $pdo)
    {
    }

    public function execute(string $sql): void
    {
        $this->strings = [];
        $sql = rtrim(trim($this->mask($sql, true)), "; \t\n\r");

        if (str_contains($sql, ';')) {
            throw new LogicException('One query per sql() call: ' . $this->unmask($sql));
        }

        $unsupported = [
            'SET\s*\(' => 'SET',
            'ON\s+UPDATE\s+CURRENT_TIMESTAMP' => 'ON UPDATE CURRENT_TIMESTAMP',
            'FULLTEXT' => 'FULLTEXT',
            'SPATIAL' => 'SPATIAL',
            'ON\s+DUPLICATE\s+KEY\s+UPDATE' => 'ON DUPLICATE KEY UPDATE',
        ];

        foreach ($unsupported as $pattern => $label) {
            if (preg_match('/\b' . $pattern . '(?!\w)/i', $sql)) {
                throw new LogicException($label . ' is not supported by SQLite: ' . $this->unmask($sql));
            }
        }

        if (preg_match('/^CREATE\s+(?:TEMPORARY\s+)?TABLE\b/i', $sql)) {
            $this->createTable($sql);
        } elseif (preg_match('/^ALTER\s+TABLE\b/i', $sql)) {
            $this->alterTable($sql);
        } else {
            $this->run($this->statement($sql));
        }
    }

    public function indexForeignKeys(): void
    {
        foreach (array_unique($this->tables) as $table) {
            $quoted = $this->quote($table);
            $foreign = [];

            foreach ($this->rows('PRAGMA foreign_key_list(' . $quoted . ')') as $row) {
                $foreign[$row['id']][$row['seq']] = $row['from'];
            }

            if ($foreign === []) {
                continue;
            }

            $covered = [];

            foreach ($this->rows('PRAGMA index_list(' . $quoted . ')') as $index) {
                $first = $this->rows('PRAGMA index_info(' . $this->quote($index['name']) . ')')[0]['name'] ?? null;

                if ($first !== null) {
                    $covered[] = strtolower($first);
                }
            }

            $primary = array_values(array_filter($this->rows('PRAGMA table_info(' . $quoted . ')'), fn (array $column): bool => $column['pk'] > 0));

            if (count($primary) === 1 && strtoupper($primary[0]['type']) === 'INTEGER') {
                $covered[] = strtolower($primary[0]['name']);
            }

            foreach ($foreign as $columns) {
                ksort($columns);
                $first = strtolower(reset($columns));

                if (in_array($first, $covered, true)) {
                    continue;
                }

                $this->pdo->exec('CREATE INDEX IF NOT EXISTS ' . $this->quote($table . '_' . implode('_', $columns)) . ' ON ' . $quoted . ' (' . implode(', ', array_map($this->quote(...), $columns)) . ')');
                $covered[] = $first;
            }
        }
    }

    public function checkForeignKeys(): void
    {
        $touched = array_unique($this->tables);
        $tables = array_map(fn (array $row): string => strtolower($row['name']), $this->rows("SELECT name FROM sqlite_master WHERE type = 'table'"));
        $checked = array_intersect($tables, $touched);

        foreach ($tables as $table) {
            foreach ($this->rows('PRAGMA foreign_key_list(' . $this->quote($table) . ')') as $foreign) {
                if (in_array(strtolower($foreign['table']), $touched, true)) {
                    $checked[] = $table;
                    break;
                }
            }
        }

        foreach (array_unique($checked) as $table) {
            $row = $this->rows('PRAGMA foreign_key_check(' . $this->quote($table) . ')')[0] ?? null;

            if ($row !== null) {
                throw new RuntimeException('Foreign key violation: ' . $row['table'] . ' row ' . ($row['rowid'] ?? '?') . ' references ' . $row['parent']);
            }
        }
    }

    /** @return list<string> */
    private function statement(string $sql): array
    {
        if (preg_match($this->re('^(?:(?:INSERT|REPLACE)\s+(?:IGNORE\s+)?(?:INTO\s+)?|UPDATE\s+(?:IGNORE\s+)?|DELETE\s+FROM\s+|TRUNCATE\s+(?:TABLE\s+)?|DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?)({name})'), $sql, $m)) {
            $this->touch($m[1]);
        }

        if (preg_match($this->re('^RENAME\s+TABLE\s+(.+)$'), $sql, $m)) {
            $queries = [];

            foreach ($this->split($m[1]) as $pair) {
                if (!preg_match($this->re('^({name})\s+TO\s+({name})$'), $pair, $p)) {
                    throw new LogicException('Unsupported RENAME TABLE: ' . $this->unmask($sql));
                }

                $this->touch($p[2]);
                $queries[] = 'ALTER TABLE ' . $p[1] . ' RENAME TO ' . $p[2];
            }

            return $queries;
        }

        if (preg_match($this->re('^DROP\s+INDEX\s+({name})\s+ON\s+{name}$'), $sql, $m)) {
            return ['DROP INDEX ' . $m[1]];
        }

        if (preg_match($this->re('^TRUNCATE\s+(?:TABLE\s+)?({name})$'), $sql, $m)) {
            return ['DELETE FROM ' . $m[1]];
        }

        if (preg_match('/^CREATE\s+(?:UNIQUE\s+)?INDEX\b/i', $sql)) {
            return [preg_replace(['/(\w|`)\s*\(\d+\)/', '/\s+USING\s+\w+/i'], ['$1', ''], $sql)];
        }

        return [preg_replace('/^INSERT\s+IGNORE\b/i', 'INSERT OR IGNORE', $sql)];
    }

    private function createTable(string $sql): void
    {
        if (!preg_match($this->re('^(CREATE\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?)({name})\s*\('), $sql, $m)) {
            throw new LogicException('Unsupported CREATE TABLE: ' . $this->unmask($sql));
        }

        $table = $m[2];
        $open = strlen($m[0]) - 1;
        $close = $this->close($sql, $open);

        if (!preg_match('/^(?:\s*,?\s*(?:ENGINE|(?:DEFAULT\s+)?(?:CHARSET|CHARACTER\s+SET|COLLATE)|AUTO_INCREMENT|COMMENT|ROW_FORMAT)\s*=?\s*\S+)*\s*$/i', substr($sql, $close + 1))) {
            throw new LogicException('Unsupported table option: ' . $this->unmask($sql));
        }

        $columns = [];
        $constraints = [];
        $indexes = [];

        foreach ($this->split(substr($sql, $open + 1, $close - $open - 1)) as $element) {
            $index = $this->index($element);

            if ($index !== null && $index[2] !== '') {
                $indexes[] = $this->createIndex($index[0], $index[1], $table, $index[3]);
            } elseif (preg_match(self::CONSTRAINT, $element)) {
                $constraints[] = $element;
            } else {
                $columns[] = $this->column($element);
            }
        }

        $this->touch($table);
        $this->run([$m[1] . $table . ' (' . implode(', ', [...$columns, ...$constraints]) . ')', ...$indexes]);
    }

    private function alterTable(string $sql): void
    {
        if (!preg_match($this->re('^ALTER\s+TABLE\s+({name})\s+(.+)$'), $sql, $m)) {
            throw new LogicException('Unsupported ALTER TABLE: ' . $this->unmask($sql));
        }

        $table = $target = $m[1];
        $before = [];
        $after = [];
        $changes = [];

        foreach ($this->split($m[2]) as $op) {
            $index = preg_match('/^ADD\s+/i', $op) ? $this->index(preg_replace('/^ADD\s+/i', '', $op)) : null;

            if ($index !== null && ($index[0] || $index[2] !== '')) {
                $after[] = $index;
            } elseif (preg_match($this->re('^DROP\s+(?:INDEX|KEY)\s+({name})$'), $op, $o)) {
                $before[] = 'DROP INDEX ' . $o[1];
            } elseif (preg_match($this->re('^RENAME\s+COLUMN\s+({name})\s+TO\s+({name})$'), $op, $o)) {
                $changes[] = ['rename', $o[1], $o[2]];
            } elseif (preg_match($this->re('^RENAME\s+(?:TO\s+|AS\s+)?({name})$'), $op, $o)) {
                $target = $o[1];
            } elseif (preg_match($this->re('^ADD\s+((?:CONSTRAINT\s+{name}\s+)?(?:FOREIGN\s+KEY|PRIMARY\s+KEY|CHECK)\b.*)$'), $op, $o)) {
                $changes[] = ['constraint', $o[1]];
            } elseif (preg_match('/^ADD\s+(?:COLUMN\s+)?(.+)$/is', $op, $o)) {
                $changes[] = ['add', $this->column($o[1])];
            } elseif (preg_match('/^DROP\s+PRIMARY\s+KEY$/i', $op)) {
                $changes[] = ['primary'];
            } elseif (preg_match($this->re('^DROP\s+(?:FOREIGN\s+KEY|CONSTRAINT|CHECK)\s+({name})$'), $op, $o)) {
                $changes[] = ['unconstraint', $o[1]];
            } elseif (preg_match($this->re('^DROP\s+(?:COLUMN\s+)?({name})$'), $op, $o)) {
                $changes[] = ['drop', $o[1]];
            } elseif (preg_match('/^MODIFY\s+(?:COLUMN\s+)?(.+)$/is', $op, $o)) {
                $definition = $this->column($o[1]);
                $changes[] = ['modify', $this->leading($definition), $definition];
            } elseif (preg_match($this->re('^CHANGE\s+(?:COLUMN\s+)?({name})\s+(.+)$'), $op, $o)) {
                $changes[] = ['modify', $o[1], $this->column($o[2])];
            } elseif (preg_match($this->re('^ALTER\s+(?:COLUMN\s+)?({name})\s+(?:SET\s+DEFAULT\s+(.+)|DROP\s+DEFAULT)$'), $op, $o)) {
                $changes[] = ['default', $o[1], $o[2] ?? null];
            } else {
                throw new LogicException('Unsupported ALTER TABLE operation in SQLite: ' . $this->unmask($op));
            }
        }

        $this->run($before);

        if (count($changes) === 1 && $changes[0][0] === 'add' && !$this->rebuildNeeded($changes[0][1])) {
            $this->run(['ALTER TABLE ' . $table . ' ADD COLUMN ' . $changes[0][1]]);
        } elseif (count($changes) === 1 && $changes[0][0] === 'rename') {
            $this->run(['ALTER TABLE ' . $table . ' RENAME COLUMN ' . $changes[0][1] . ' TO ' . $changes[0][2]]);
        } elseif ($changes !== []) {
            $this->rebuild($table, $changes);
        }

        if ($target !== $table) {
            $this->run(['ALTER TABLE ' . $table . ' RENAME TO ' . $target]);
        }

        $this->run(array_map(fn (array $index): string => $this->createIndex($index[0], $index[1], $target, $index[3]), $after));
        $this->touch($target);
    }

    /** @param list<array<int, ?string>> $changes */
    private function rebuild(string $table, array $changes): void
    {
        $key = $this->normalize($table);
        $stmt = $this->pdo->prepare("SELECT sql FROM sqlite_master WHERE type = 'table' AND lower(name) = ?");
        $stmt->execute([$key]);
        $create = $stmt->fetchColumn();

        if (!is_string($create)) {
            throw new LogicException('Unknown table: ' . $key);
        }

        $create = $this->mask($create, false);
        $open = (int) strpos($create, '(');
        $close = $this->close($create, $open);
        $elements = [];

        foreach ($this->split(substr($create, $open + 1, $close - $open - 1)) as $definition) {
            $column = !preg_match(self::CONSTRAINT, $definition);
            $elements[] = ['def' => $definition, 'from' => $column ? $this->leading($definition) : null, 'column' => $column];
        }

        $renamed = [];
        $dropped = [];

        foreach ($changes as $change) {
            switch ($change[0]) {
                case 'add':
                    $last = max(array_keys(array_filter($elements, fn (array $element): bool => $element['column'])));
                    array_splice($elements, $last + 1, 0, [['def' => $change[1], 'from' => null, 'column' => true]]);
                    break;

                case 'drop':
                    $name = $this->normalize((string) $change[1]);

                    foreach ($elements as $element) {
                        if (!$element['column'] && $this->mentions($element['def'], $name)) {
                            throw new LogicException('Column ' . $name . ' is used by a constraint of ' . $key . ': drop the constraint first');
                        }
                    }

                    unset($elements[$this->find($elements, $name)]);
                    $elements = array_values($elements);
                    $dropped[] = $name;
                    break;

                case 'modify':
                case 'rename':
                    $i = $this->find($elements, $old = $this->normalize((string) $change[1]));
                    $definition = $change[0] === 'modify' ? (string) $change[2] : $change[2] . substr($elements[$i]['def'], strlen($this->leading($elements[$i]['def'])));
                    $elements[$i]['def'] = $definition;
                    $new = $this->leading($definition);

                    if ($this->normalize($new) !== $old) {
                        $renamed[$old] = $new;

                        foreach ($elements as $j => $element) {
                            $elements[$j]['def'] = $element['column'] ? $this->renameChecks($element['def'], $old, $new) : $this->renameIn($element['def'], $old, $new);
                        }
                    }
                    break;

                case 'default':
                    $i = $this->find($elements, $this->normalize((string) $change[1]));
                    $definition = preg_replace('/\s+DEFAULT\s+(?:\x1A\d+\x1A|\([^()]*\)|\S+)/i', '', $elements[$i]['def']);
                    $elements[$i]['def'] = $change[2] === null ? $definition : $definition . ' DEFAULT ' . $change[2];
                    break;

                case 'constraint':
                    $elements[] = ['def' => $change[1], 'from' => null, 'column' => false];
                    break;

                case 'unconstraint':
                    $name = $this->normalize((string) $change[1]);
                    $found = false;

                    foreach ($elements as $i => $element) {
                        if (!$element['column'] && preg_match($this->re('^CONSTRAINT\s+({name})'), $element['def'], $c) && $this->normalize($c[1]) === $name) {
                            unset($elements[$i]);
                            $found = true;
                        }
                    }

                    if (!$found) {
                        throw new LogicException('Unknown constraint ' . $name . ' on ' . $key);
                    }

                    $elements = array_values($elements);
                    break;

                case 'primary':
                    $found = false;

                    foreach ($elements as $i => $element) {
                        if (!$element['column'] && preg_match($this->re('^(?:CONSTRAINT\s+{name}\s+)?PRIMARY\s+KEY\b'), $element['def'])) {
                            unset($elements[$i]);
                            $found = true;
                        } elseif ($element['column'] && preg_match('/\s+PRIMARY\s+KEY\b/i', $element['def'])) {
                            $elements[$i]['def'] = preg_replace('/\s+PRIMARY\s+KEY(?:\s+(?:ASC|DESC))?(?:\s+AUTOINCREMENT)?\b/i', '', $element['def']);
                            $found = true;
                        }
                    }

                    if (!$found) {
                        throw new LogicException('No primary key on ' . $key);
                    }

                    $elements = array_values($elements);
                    break;
            }
        }

        $stmt = $this->pdo->prepare("SELECT type, name, sql FROM sqlite_master WHERE type IN ('index', 'trigger') AND lower(tbl_name) = ? AND sql IS NOT NULL");
        $stmt->execute([$key]);
        $objects = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['type'] === 'index') {
                $columns = array_map(fn (?string $column): string => strtolower((string) $column), array_column($this->rows('PRAGMA index_info(' . $this->quote($row['name']) . ')'), 'name'));

                if (array_intersect($columns, $dropped) !== []) {
                    continue;
                }

                if (array_intersect($columns, array_keys($renamed)) !== []) {
                    $row['sql'] = $this->renameIndex($row['sql'], $renamed);
                }
            }

            $objects[] = $row['sql'];
        }

        $temp = $this->quote($key . '_new');
        $write = [];
        $read = [];

        foreach ($elements as $element) {
            if ($element['column'] && $element['from'] !== null) {
                $write[] = $this->leading($element['def']);
                $read[] = $element['from'];
            }
        }

        $this->run([
            'CREATE TABLE ' . $temp . ' (' . implode(', ', array_column($elements, 'def')) . ')',
            'INSERT INTO ' . $temp . ' (' . implode(', ', $write) . ') SELECT ' . implode(', ', $read) . ' FROM ' . $table,
            'DROP TABLE ' . $table,
            'ALTER TABLE ' . $temp . ' RENAME TO ' . $table,
            ...$objects,
        ]);
    }

    private function column(string $definition): string
    {
        if (preg_match('/\bAUTO_INCREMENT\b/i', $definition)) {
            if (!preg_match($this->re('^({name})\s+(?:TINY|SMALL|MEDIUM|BIG)?INT(?:EGER)?\b.*\bPRIMARY\s+KEY\b'), $definition, $m)) {
                throw new LogicException('AUTO_INCREMENT needs an inline integer PRIMARY KEY: ' . $this->unmask($definition));
            }

            return $m[1] . ' INTEGER PRIMARY KEY AUTOINCREMENT';
        }

        $definition = preg_replace([
            '/\s+(?:UNSIGNED|ZEROFILL)\b/i',
            '/\s+(?:CHARACTER\s+SET|CHARSET)\s+\w+/i',
            '/\s+COLLATE\s+\w+/i',
            '/\s+COMMENT\s+\x1A\d+\x1A/i',
            $this->re('\s+(?:AFTER\s+{name}|FIRST)$'),
        ], '', $definition);
        $definition = preg_replace($this->re('^({name})\s+JSON\b'), '$1 TEXT', $definition);

        if (preg_match($this->re('^({name})\s+ENUM\s*\('), $definition, $m)) {
            $open = strlen($m[0]) - 1;
            $close = $this->close($definition, $open);
            $definition = $m[1] . ' VARCHAR' . substr($definition, $close + 1) . ' CHECK (' . $m[1] . ' IN ' . substr($definition, $open, $close - $open + 1) . ')';
        }

        return $definition;
    }

    private function rebuildNeeded(string $definition): bool
    {
        return preg_match('/\bPRIMARY\s+KEY\b|\bUNIQUE\b/i', $definition)
            || (preg_match('/\bNOT\s+NULL\b/i', $definition) && !preg_match('/\bDEFAULT\b/i', $definition))
            || preg_match('/\bDEFAULT\s+(?:\(|CURRENT_|NOW\b|LOCALTIME)/i', $definition)
            || (preg_match('/\bREFERENCES\b/i', $definition) && preg_match('/\bDEFAULT\s+(?!NULL\b)/i', $definition));
    }

    /** @return array{bool, ?string, string, string}|null */
    private function index(string $element): ?array
    {
        if (!preg_match($this->re('^(?:CONSTRAINT\s+({name})\s+)?(UNIQUE\b\s*)?(?:(INDEX|KEY)\b\s*)?({name})?\s*\('), $element, $m)) {
            return null;
        }

        $unique = ($m[2] ?? '') !== '';
        $type = $m[3] ?? '';

        if (!$unique && $type === '') {
            return null;
        }

        $open = strlen($m[0]) - 1;
        $close = $this->close($element, $open);

        if (!preg_match('/^(?:\s+USING\s+\w+)?$/i', substr($element, $close + 1))) {
            return null;
        }

        $name = ($m[4] ?? '') !== '' ? $m[4] : (($m[1] ?? '') !== '' ? $m[1] : null);

        return [$unique, $name, $type, preg_replace('/\s*\(\d+\)/', '', substr($element, $open + 1, $close - $open - 1))];
    }

    private function createIndex(bool $unique, ?string $name, string $table, string $columns): string
    {
        $name ??= $this->quote($this->normalize($table) . '_' . implode('_', array_map(fn (string $column): string => $this->normalize(preg_replace('/\s+(?:ASC|DESC)$/i', '', $column)), $this->split($columns))));

        return 'CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ' . $name . ' ON ' . $table . ' (' . $columns . ')';
    }

    private function renameIndex(string $sql, array $renamed): string
    {
        $sql = $this->mask($sql, false);

        if (!preg_match($this->re('\bON\s+{name}\s*\('), $sql, $m, PREG_OFFSET_CAPTURE)) {
            return $this->unmask($sql);
        }

        $open = $m[0][1] + strlen($m[0][0]) - 1;
        $close = $this->close($sql, $open);
        $columns = preg_replace_callback($this->re('{name}'), fn (array $t): string => $renamed[$this->normalize($t[0])] ?? $t[0], substr($sql, $open, $close - $open + 1));

        return $this->unmask(substr($sql, 0, $open) . $columns . substr($sql, $close + 1));
    }

    private function renameIn(string $constraint, string $old, string $new): string
    {
        $open = strpos($constraint, '(');

        if ($open === false) {
            return $constraint;
        }

        return $this->renameGroup($constraint, $open, $old, $new);
    }

    private function renameChecks(string $definition, string $old, string $new): string
    {
        preg_match_all('/\bCHECK\s*\(/i', $definition, $m, PREG_OFFSET_CAPTURE);

        foreach (array_reverse($m[0]) as [$match, $offset]) {
            $definition = $this->renameGroup($definition, $offset + strlen($match) - 1, $old, $new);
        }

        return $definition;
    }

    private function renameGroup(string $sql, int $open, string $old, string $new): string
    {
        $close = $this->close($sql, $open);
        $group = preg_replace_callback($this->re('{name}'), fn (array $t): string => $this->normalize($t[0]) === $old ? $new : $t[0], substr($sql, $open, $close - $open + 1));

        return substr($sql, 0, $open) . $group . substr($sql, $close + 1);
    }

    private function mentions(string $constraint, string $name): bool
    {
        return $this->renameIn($constraint, $name, "\x1A-\x1A") !== $constraint;
    }

    /** @param list<array{def: string, from: ?string, column: bool}> $elements */
    private function find(array $elements, string $name): int
    {
        foreach ($elements as $i => $element) {
            if ($element['column'] && $this->normalize($this->leading($element['def'])) === $name) {
                return $i;
            }
        }

        throw new LogicException('Unknown column: ' . $name);
    }

    private function leading(string $definition): string
    {
        if (!preg_match($this->re('^({name})'), $definition, $m)) {
            throw new LogicException('Invalid column definition: ' . $this->unmask($definition));
        }

        return $m[1];
    }

    private function normalize(string $name): string
    {
        $name = $this->unmask($name);

        if (preg_match('/^[`"\[]/', $name)) {
            $name = substr($name, 1, -1);
        }

        return strtolower($name);
    }

    private function quote(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    private function touch(string $table): void
    {
        $this->tables[] = $this->normalize($table);
    }

    /** @param list<string> $queries */
    private function run(array $queries): void
    {
        foreach ($queries as $query) {
            $this->pdo->exec($this->unmask($query));
        }
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<string> */
    private function split(string $sql): array
    {
        $parts = [];
        $depth = 0;
        $start = 0;
        $quote = null;

        for ($i = 0, $length = strlen($sql); $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                $quote = $char === $quote ? null : $quote;
            } elseif ($char === '`') {
                $quote = '`';
            } elseif ($char === '[') {
                $quote = ']';
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = trim(substr($sql, $start, $i - $start));
                $start = $i + 1;
            }
        }

        $parts[] = trim(substr($sql, $start));

        return array_values(array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    private function close(string $sql, int $open): int
    {
        $depth = 0;
        $quote = null;

        for ($i = $open, $length = strlen($sql); $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                $quote = $char === $quote ? null : $quote;
            } elseif ($char === '`') {
                $quote = '`';
            } elseif ($char === '[') {
                $quote = ']';
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')' && --$depth === 0) {
                return $i;
            }
        }

        throw new LogicException('Unbalanced parentheses: ' . $this->unmask($sql));
    }

    private function mask(string $sql, bool $mysql): string
    {
        $pattern = $mysql
            ? '/\'(?:[^\'\\\\]|\\\\.|\'\')*\'|"(?:[^"\\\\]|\\\\.|"")*"/s'
            : '/\'(?:[^\']|\'\')*\'|"(?:[^"]|"")*"/s';

        return preg_replace_callback($pattern, function (array $m) use ($mysql): string {
            $this->strings[] = $mysql ? $this->literal($m[0]) : $m[0];

            return "\x1A" . (count($this->strings) - 1) . "\x1A";
        }, $sql);
    }

    private function unmask(string $sql): string
    {
        return preg_replace_callback('/\x1A(\d+)\x1A/', fn (array $m): string => $this->strings[(int) $m[1]], $sql);
    }

    private function literal(string $quoted): string
    {
        $quote = $quoted[0];
        $escapes = ['0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'Z' => "\x1A", '%' => '\\%', '_' => '\\_'];
        $value = preg_replace_callback('/\\\\(.)|' . $quote . $quote . '/s', fn (array $m): string => isset($m[1]) ? ($escapes[$m[1]] ?? $m[1]) : $quote, substr($quoted, 1, -1));

        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function re(string $pattern): string
    {
        return '/' . str_replace('{name}', self::NAME, $pattern) . '/is';
    }
}
