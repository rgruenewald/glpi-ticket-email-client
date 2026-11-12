<?php
declare(strict_types=1);

// Shared deterministic fixtures for regression tests and the local benchmark.
if (!class_exists('Document')) {
    class Document
    {
        public static array $records = [];
        public static int $loads = 0;
        private array $fields = [];

        public function getFromDB(int $id): bool
        {
            ++self::$loads;
            $this->fields = self::$records[$id] ?? [];
            return $this->fields !== [];
        }

        public function getField(string $name): mixed
        {
            return $this->fields[$name] ?? null;
        }
    }
}

final class FrequentActionsDatabase
{
    public int $queries = 0;
    public array $requests = [];

    public function __construct(public array $tables = [])
    {
    }

    public function tableExists(string $table): bool { return isset($this->tables[$table]); }

    public function request(string|array $table, array $criteria = []): iterable
    {
        if (is_array($table)) { $criteria = $table; $table = $criteria['FROM']; }
        ++$this->queries;
        $this->requests[] = [$table, $criteria];
        $where = $criteria['WHERE'] ?? $criteria;
        foreach ($this->tables[$table] ?? [] as $row) {
            foreach ($where as $key => $expected) {
                $values = is_array($expected) ? $expected : [$expected];
                if (!in_array($row[$key] ?? null, $values, true)) {
                    continue 2;
                }
            }
            yield $row;
        }
    }
}

if (!defined('GLPI_DOC_DIR')) { define('GLPI_DOC_DIR', sys_get_temp_dir() . '/ticketmailer-benchmark-' . getmypid()); }
if (!class_exists('Plugin')) {
    class Plugin { public static function getWebDir(string $name): string { return '/plugins/' . $name; } }
}
function frequentActionsFile(): string
{
    if (!is_dir(GLPI_DOC_DIR)) { mkdir(GLPI_DOC_DIR, 0700); }
    $path = GLPI_DOC_DIR . '/fixture.txt';
    file_put_contents($path, 'synthetic attachment');
    register_shutdown_function(static function () use ($path): void { unlink($path); rmdir(GLPI_DOC_DIR); });
    return 'fixture.txt';
}
