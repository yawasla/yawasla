<?php

declare(strict_types=1);

namespace Yawasla\Command;

use LogicException;
use Throwable;
use Yawasla\Core\Modules;
use Yawasla\Database\Database;
use Yawasla\Database\Migrator;
use Yawasla\Database\Transfer;

final class DbMigrateCommand implements Command
{
    private const USAGE = "Usage:\n  php bin/console db:migrate\n  php bin/console db:migrate --server mysql|sqlite\n  php bin/console db:migrate --create [Module]\n";

    public function __construct(private string $root)
    {
    }

    public function description(): string
    {
        return 'Run the pending migrations (--server: move to another SQL server, --create: new migration)';
    }

    public function run(array $args): int
    {
        try {
            return match (true) {
                $args === [] => $this->migrate(),
                $args[0] === '--server' && count($args) === 2 => $this->server($args[1]),
                $args[0] === '--create' && count($args) <= 2 => $this->create($args[1] ?? $this->ask('Module: ')),
                default => $this->usage(),
            };
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . ($e->getPrevious() ? ': ' . $e->getPrevious()->getMessage() : '') . "\n");

            return 1;
        }
    }

    private function migrate(): int
    {
        $migrated = (new Migrator())->run();
        echo $migrated === [] ? "Nothing to migrate.\n" : 'Migrated: ' . implode(', ', $migrated) . "\n";

        return 0;
    }

    private function server(string $driver): int
    {
        if (!in_array($driver, ['mysql', 'sqlite'], true)) {
            return $this->usage();
        }

        $counts = (new Transfer(Database::getInstance()->medoo()->pdo, Database::driver(), Database::connect($driver)->pdo, $driver))->run();

        foreach ($counts as $table => $count) {
            printf("  %-20s %d\n", $table, $count);
        }

        echo "Copied to {$driver}. Check the result, then set DB_DRIVER={$driver} in .env.\n";

        return 0;
    }

    private function create(string $name): int
    {
        if (!preg_match('#^(?:(Core|Extension)/)?([A-Za-z0-9]+)$#', $name, $m)) {
            throw new LogicException('Invalid module name: ' . $name);
        }

        $module = Modules::getInstance()->get($m[2]) ?? throw new LogicException('Unknown module: ' . $m[2]);

        if ($m[1] !== '' && ($m[1] === 'Core') !== $module['core']) {
            throw new LogicException($module['name'] . ' is not in ' . $m[1] . '/');
        }

        if ($module['core'] && $module['id'] !== 'main') {
            throw new LogicException('Core migrations go in Main');
        }

        $dir = $module['dir'] . '/migrations';

        if (!is_dir($dir) && !mkdir($dir, 0755)) {
            throw new LogicException('Cannot create ' . $dir);
        }

        $date = date('Ymd');
        $numbers = array_map(fn (string $file): int => (int) substr(basename($file, '.php'), 9), glob($dir . '/' . $date . '-[0-9][0-9].php') ?: []);
        $next = $numbers === [] ? 1 : max($numbers) + 1;

        if ($next > 99) {
            throw new LogicException('No migration number left for today');
        }

        $file = sprintf('%s/%s-%02d.php', $dir, $date, $next);
        $handle = fopen($file, 'x');

        if ($handle === false) {
            throw new LogicException('Cannot create ' . $file);
        }

        fwrite($handle, "<?php\n\ndeclare(strict_types=1);\n\nuse Yawasla\\Database\\Migration;\n\nreturn new class extends Migration {\n    public function up(): void\n    {\n    }\n\n    public function down(): void\n    {\n    }\n};\n");
        fclose($handle);
        echo 'Created: ' . substr($file, strlen($this->root) + 1) . "\n";

        return 0;
    }

    private function ask(string $question): string
    {
        echo $question;

        return trim((string) fgets(STDIN));
    }

    private function usage(): int
    {
        fwrite(STDERR, self::USAGE);

        return 1;
    }
}
