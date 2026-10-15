<?php

namespace GFrame\Cron;

use PDO;
use RuntimeException;

/** Persistencia de coordinación. Las actualizaciones se realizan bajo ExecutionLock. */
final class CronState
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function read(): array
    {
        $row = $this->database->query('SELECT * FROM cron_runtime WHERE runtime_id = 1')->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('Falta la migración de coordinación del cron.');
        return $row;
    }

    public function update(array $changes): void
    {
        $allowed = ['launch_token', 'pending_until', 'next_launch_at', 'last_launch_at', 'started_at', 'finished_at', 'processed', 'failed', 'recovered', 'last_error'];
        if ($changes === [] || array_diff(array_keys($changes), $allowed) !== []) {
            throw new RuntimeException('Actualización de coordinación inválida.');
        }
        $columns = array_map(static fn(string $key): string => $key . ' = ?', array_keys($changes));
        $statement = $this->database->prepare('UPDATE cron_runtime SET ' . implode(', ', $columns) . ' WHERE runtime_id = 1');
        $statement->execute(array_values($changes));
    }
}
