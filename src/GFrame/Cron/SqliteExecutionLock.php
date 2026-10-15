<?php

namespace GFrame\Cron;

use PDO;
use PDOException;
use RuntimeException;

final class SqliteExecutionLock implements ExecutionLock
{
    private PDO $lockDatabase;
    private bool $owned = false;

    public function __construct(PDO $database)
    {
        $path = '';
        foreach ($database->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['name'] === 'main') $path = (string)$row['file'];
        }
        // Una base en memoria no se comparte entre procesos; se usa solo en pruebas aisladas.
        $this->lockDatabase = new PDO($path === '' ? 'sqlite::memory:' : 'sqlite:' . $path . '.gframe-cron-lock.sqlite');
        $this->lockDatabase->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->lockDatabase->exec('PRAGMA busy_timeout = 0');
    }

    public function acquire(): bool
    {
        if ($this->owned) return false;
        try {
            // Base auxiliar junto a la base compartida: no bloquea las escrituras de los handlers.
            $this->lockDatabase->exec('BEGIN IMMEDIATE');
            return $this->owned = true;
        } catch (PDOException $exception) {
            if (in_array((int)($exception->errorInfo[1] ?? 0), [5, 6], true)) return false;
            throw $exception;
        }
    }

    public function assertOwned(): void
    {
        if (!$this->owned) {
            throw new RuntimeException('Se perdió la propiedad de ejecución del cron.');
        }
        $this->lockDatabase->query('SELECT 1');
    }

    public function release(): void
    {
        if (!$this->owned) return;
        $this->owned = false;
        $this->lockDatabase->exec('ROLLBACK');
    }

    public function now(): int { return time(); }
}
