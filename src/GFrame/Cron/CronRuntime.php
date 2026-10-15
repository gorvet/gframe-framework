<?php

namespace GFrame\Cron;

use GFrame\Config\ConfigRepository;
use GFrame\Config\Environment;
use PDO;
use RuntimeException;

final class CronRuntime
{
    private ExecutionLock $lock;
    private CronState $state;

    public function __construct(PDO $database, ?ExecutionLock $lock = null)
    {
        $this->lock = $lock ?? match ($database->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'mysql' => new MySqlExecutionLock($database),
            'sqlite' => new SqliteExecutionLock($database),
            default => throw new RuntimeException('Motor de cron no compatible.'),
        };
        $this->state = new CronState($database);
    }

    public static function frameworkDefault(): self
    {
        $database = \DatabaseManager::connection();
        if (!$database instanceof PDO) throw new RuntimeException('No se pudo conectar al almacenamiento del cron.');
        return new self($database);
    }

    public static function settings(): array
    {
        $mode = (string)Environment::get('GFRAME_CRON_MODE', ConfigRepository::get('cron.mode', 'real'));
        if (!in_array($mode, ['real', 'virtual'], true)) throw new RuntimeException('GFRAME_CRON_MODE debe ser real o virtual.');
        $executor = (string)Environment::get('GFRAME_CRON_EXECUTOR', ConfigRepository::get('cron.executor', 'cli'));
        if (!in_array($executor, ['cli', 'http'], true)) throw new RuntimeException('GFRAME_CRON_EXECUTOR debe ser cli o http.');
        $values = ['mode' => $mode, 'executor' => $executor];
        foreach (['min_interval' => ['GFRAME_CRON_MIN_INTERVAL', 60, 1, 86400], 'batch_limit' => ['GFRAME_CRON_BATCH_LIMIT', 10, 1, 100]] as $key => [$environment, $default, $min, $max]) {
            $value = Environment::get($environment, ConfigRepository::get('cron.' . $key, $default));
            $validated = filter_var($value, FILTER_VALIDATE_INT);
            if ($validated === false || $validated < $min || $validated > $max) throw new RuntimeException($environment . ' está fuera del rango permitido.');
            $values[$key] = $validated;
        }
        return $values;
    }

    public function acquire(): bool { return $this->lock->acquire(); }
    public function assertOwned(): void { $this->lock->assertOwned(); }
    public function release(): void { $this->lock->release(); }

    public function reserveLaunch(int $interval, ?int $now = null): ?string
    {
        if (!$this->acquire()) return null;
        try {
            $now ??= $this->lock->now();
            $row = $this->state->read();
            if ((int)$row['next_launch_at'] > $now || (int)$row['pending_until'] > $now) return null;
            $token = bin2hex(random_bytes(24));
            $this->state->update(['launch_token' => $token, 'pending_until' => $now + 120, 'next_launch_at' => $now + $interval, 'last_launch_at' => $now]);
            return $token;
        } finally {
            $this->release();
        }
    }

    public function abandonLaunch(string $token, string $message): void
    {
        if (!$this->acquire()) return;
        try {
            if ($this->state->read()['launch_token'] !== $token) return;
            $this->state->update(['launch_token' => null, 'pending_until' => 0, 'last_error' => $message]);
        } finally {
            $this->release();
        }
    }

    /** Se invoca con el bloqueo adquirido; un ticket vencido nunca autoriza ejecución. */
    public function start(?string $token = null): bool
    {
        $this->assertOwned();
        $row = $this->state->read();
        $now = $this->lock->now();
        if ($token !== null && ($row['launch_token'] !== $token || (int)$row['pending_until'] <= $now)) return false;
        $this->state->update(['launch_token' => null, 'pending_until' => 0, 'started_at' => $now, 'finished_at' => null, 'last_error' => null]);
        return true;
    }

    public function finish(array $result): void
    {
        $this->assertOwned();
        $data = $result['data'] ?? [];
        $this->state->update(['finished_at' => $this->lock->now(), 'processed' => (int)($data['processed'] ?? 0), 'failed' => (int)($data['failed'] ?? 0), 'recovered' => (int)($data['recovered'] ?? 0), 'last_error' => ($result['status'] ?? '') === 'error' ? (string)($result['message'] ?? 'cron_failed') : ((int)($data['failed'] ?? 0) > 0 ? 'cron_handlers_failed' : null)]);
    }

    public function diagnostic(): array
    {
        $state = $this->state->read();
        $available = $this->acquire();
        if ($available) $this->release();
        unset($state['launch_token']);
        return ['settings' => self::settings(), 'busy' => !$available, 'state' => $state];
    }
}
