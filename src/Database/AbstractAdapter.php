<?php

declare(strict_types=1);

namespace Yawasla\Database;

use LogicException;
use PDO;

abstract class AbstractAdapter
{
    protected const NAME = '(?:`[^`]+`|\[[^\]]+\]|\x1A\d+\x1A|\w+)';

    /** @var list<string> */
    protected array $strings = [];

    public function __construct(protected PDO $pdo)
    {
    }

    abstract public function execute(string $sql): void;

    public function begin(): void
    {
    }

    public function finish(): void
    {
    }

    public function end(): void
    {
    }

    protected function literal(string $quoted): string
    {
        return $quoted;
    }

    protected function mask(string $sql, bool $mysql): string
    {
        $pattern = $mysql
            ? '/\'(?:[^\'\\\\]|\\\\.|\'\')*\'|"(?:[^"\\\\]|\\\\.|"")*"/s'
            : '/\'(?:[^\']|\'\')*\'|"(?:[^"]|"")*"/s';

        return preg_replace_callback($pattern, function (array $m) use ($mysql): string {
            $this->strings[] = $mysql ? $this->literal($m[0]) : $m[0];

            return "\x1A" . (count($this->strings) - 1) . "\x1A";
        }, $sql);
    }

    protected function unmask(string $sql): string
    {
        return preg_replace_callback('/\x1A(\d+)\x1A/', fn (array $m): string => $this->strings[(int) $m[1]], $sql);
    }

    protected function normalize(string $name): string
    {
        $name = $this->unmask($name);

        if (preg_match('/^[`"\[]/', $name)) {
            $name = substr($name, 1, -1);
        }

        return strtolower($name);
    }

    protected function quote(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    /** @return list<array<string, mixed>> */
    protected function rows(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<string> */
    protected function split(string $sql): array
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

    protected function close(string $sql, int $open): int
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

    protected function re(string $pattern): string
    {
        return '/' . str_replace('{name}', self::NAME, $pattern) . '/is';
    }
}
