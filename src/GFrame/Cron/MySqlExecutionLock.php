<?php

namespace GFrame\Cron;

use PDO;
use RuntimeException;

final class MySqlExecutionLock implements ExecutionLock
{
    private bool $owned = false;
    private string $name;
    private static ?\WeakMap $owners = null;

    public function __construct(private readonly PDO $database)
    {
        $this->name = 'gframe.cron.' . substr(hash('sha256', (string)$database->query('SELECT DATABASE()')->fetchColumn()), 0, 40);
    }

    public function acquire(): bool
    {
        self::$owners ??= new \WeakMap();
        if ($this->owned || isset(self::$owners[$this->database])) return false;
        $statement = $this->database->prepare('SELECT GET_LOCK(?, 0)');
        $statement->execute([$this->name]);
        $result = $statement->fetchColumn();
        if ($result === null || $result === false) throw new RuntimeException('No se pudo adquirir el bloqueo del cron.');
        $this->owned = (int)$result === 1;
        if ($this->owned) self::$owners[$this->database] = true;
        return $this->owned;
    }

    public function assertOwned(): void
    {
        $statement = $this->database->prepare('SELECT IS_USED_LOCK(?) = CONNECTION_ID()');
        $statement->execute([$this->name]);
        if (!$this->owned || (int)$statement->fetchColumn() !== 1) {
            throw new RuntimeException('Se perdió la propiedad de ejecución del cron.');
        }
    }

    public function release(): void
    {
        if (!$this->owned) return;
        $this->owned = false;
        unset(self::$owners[$this->database]);
        // RELEASE_LOCK solo libera un bloqueo perteneciente a esta conexión.
        $statement = $this->database->prepare('SELECT RELEASE_LOCK(?)');
        $statement->execute([$this->name]);
    }

    public function now(): int
    {
        return (int)$this->database->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
    }
}
