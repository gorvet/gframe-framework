<?php

namespace GFrame\Install;

use GFrame\Modules\ModuleCatalog;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class MigrationRunner
{
    public function __construct(
        private readonly ModuleCatalog $modules,
        private readonly SqlStatementParser $parser = new SqlStatementParser()
    ) {
    }

    public static function frameworkDefault(): self
    {
        return new self(ModuleCatalog::frameworkDefault());
    }

    public function migrate(PDO $pdo, string $driver, array $moduleNames): array
    {
        if ($pdo->inTransaction()) throw new RuntimeException('Las migraciones requieren una conexión sin transacción activa.');
        $driver = $this->driver($driver);
        $this->createTable($pdo, $driver);
        $applied = $this->applied($pdo);
        $executed = [];
        foreach ($this->migrationFiles($driver, $moduleNames) as $migration) {
            if (isset($applied[$migration['id']])) continue;
            if ($driver === 'sqlite') $pdo->beginTransaction();
            try {
                foreach ($this->parser->parse((string)file_get_contents($migration['path'])) as $statement) {
                    if (!$this->existingAddColumn($pdo, $driver, $statement)) $pdo->exec($statement);
                }
                $insert = $pdo->prepare('INSERT INTO gframe_migrations (migration, module, applied_at) VALUES (?, ?, ?)');
                $insert->execute([$migration['id'], $migration['module'], date('Y-m-d H:i:s')]);
                if ($driver === 'sqlite') $pdo->commit();
                $executed[] = $migration['id'];
            } catch (\Exception $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
        }
        return ['executed' => $executed, 'count' => count($executed)];
    }

    public function baseline(PDO $pdo, string $driver, array $moduleNames): array
    {
        $driver = $this->driver($driver);
        $this->createTable($pdo, $driver);
        $applied = $this->applied($pdo);
        $recorded = [];
        $insert = $pdo->prepare('INSERT INTO gframe_migrations (migration, module, applied_at) VALUES (?, ?, ?)');
        foreach ($this->migrationFiles($driver, $moduleNames) as $migration) {
            if (isset($applied[$migration['id']])) continue;
            $insert->execute([$migration['id'], $migration['module'], date('Y-m-d H:i:s')]);
            $recorded[] = $migration['id'];
        }
        return ['recorded' => $recorded, 'count' => count($recorded)];
    }

    public function pending(PDO $pdo, string $driver, array $moduleNames): array
    {
        $driver = $this->driver($driver);
        try {
            $applied = $this->applied($pdo);
        } catch (\Exception $exception) {
            $applied = [];
        }
        $pending = [];
        foreach ($this->migrationFiles($driver, $moduleNames) as $migration) {
            if (!isset($applied[$migration['id']])) $pending[] = $migration['id'];
        }
        return ['pending' => $pending, 'count' => count($pending)];
    }

    private function migrationFiles(string $driver, array $moduleNames): array
    {
        $files = [];
        foreach ($this->modules->resolve(array_values(array_map('strval', $moduleNames))) as $module) {
            foreach ((array)($module['migrations'][$driver] ?? []) as $relative) {
                $relative = str_replace('\\', '/', trim((string)$relative));
                if ($relative === '' || str_starts_with($relative, '/') || in_array('..', explode('/', $relative), true)) {
                    throw new RuntimeException("Migración inválida en {$module['name']}.");
                }
                $path = rtrim((string)$module['path'], '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (!is_file($path)) throw new RuntimeException("No se encontró la migración {$relative}.");
                $files[] = ['id' => (string)$module['name'] . ':' . $relative, 'module' => (string)$module['name'], 'path' => $path];
            }
        }
        return $files;
    }

    private function createTable(PDO $pdo, string $driver): void
    {
        $id = $driver === 'mysql' ? 'VARCHAR(255)' : 'TEXT';
        $module = $driver === 'mysql' ? 'VARCHAR(100)' : 'TEXT';
        $pdo->exec("CREATE TABLE IF NOT EXISTS gframe_migrations (migration {$id} PRIMARY KEY, module {$module} NOT NULL, applied_at " . ($driver === 'mysql' ? 'DATETIME' : 'TEXT') . ' NOT NULL)');
    }

    /** Additive migrations may resume only when the existing column has the expected definition. */
    private function existingAddColumn(PDO $pdo, string $driver, string $statement): bool
    {
        if (!preg_match('/^\s*ALTER\s+TABLE\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?\s+ADD\s+COLUMN\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?\s+((?:BIGINT(?:\s+UNSIGNED)?|INTEGER|TEXT|JSON|VARCHAR\(\d+\)))\s+(NOT\s+NULL|NULL)(?:\s+DEFAULT\s+(\d+|\x27[^\x27]*\x27))?(?:\s+AFTER\s+`?[a-zA-Z_][a-zA-Z0-9_]*`?)?\s*;?\s*$/i', $statement, $match)) return false;
        if ($driver === 'sqlite') {
            $columns = $pdo->query('PRAGMA table_info("' . $match[1] . '")')->fetchAll(PDO::FETCH_ASSOC);
            $column = null;
            foreach ($columns as $row) if ($row['name'] === $match[2]) $column = $row;
            if ($column === null) return false;
            $type = $column['type']; $nullable = !(bool)$column['notnull']; $default = $column['dflt_value'];
            if (is_string($default)) $default = trim($default, "'");
        } else {
            $query = $pdo->prepare('SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $query->execute([$match[1], $match[2]]);
            $column = $query->fetch(PDO::FETCH_ASSOC);
            if ($column === false) return false;
            $type = preg_replace('/\b(bigint|int)\(\d+\)/i', '$1', $column['COLUMN_TYPE']);
            $nullable = $column['IS_NULLABLE'] === 'YES'; $default = $column['COLUMN_DEFAULT'];
            if (is_string($default)) $default = trim($default, "'");
        }
        $expectedDefault = isset($match[5]) && $match[5] !== '' ? trim($match[5], "'") : null;
        if (strtoupper($type) !== strtoupper($match[3]) || $nullable !== (strtoupper($match[4]) === 'NULL')
            || ($default === null ? null : (string)$default) !== $expectedDefault) {
            throw new RuntimeException("La columna {$match[1]}.{$match[2]} existe con una definición incompatible.");
        }
        return true;
    }

    private function applied(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT migration FROM gframe_migrations')->fetchAll(PDO::FETCH_COLUMN);
        return array_fill_keys(array_map('strval', $rows ?: []), true);
    }

    private function driver(string $driver): string
    {
        $driver = strtolower(trim($driver));
        if (!in_array($driver, ['mysql', 'sqlite'], true)) throw new InvalidArgumentException("El motor {$driver} no admite migraciones.");
        return $driver;
    }
}
