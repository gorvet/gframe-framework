<?php

namespace GFrame\Security;

use Closure;
use GFrame\Config\ConfigRepository;
use PDO;
use RuntimeException;
use Throwable;

/** Short database-only transactions; transport work never runs under a SQL lock. */
final class DatabaseRateLimitStore
{
    public function __construct(private readonly PDO $pdo, private readonly int $lockTimeoutSeconds = 2) {}

    public static function configured(string $section): self
    {
        $connection = ConfigRepository::get($section . '.connection');
        $pdo = \DatabaseManager::connection($connection === null ? null : (string)$connection);
        if (!$pdo instanceof PDO) throw new RuntimeException('Rate limit connection unavailable.');
        return new self($pdo, max(1, (int)ConfigRepository::get($section . '.lock_timeout_seconds', 2)));
    }

    public function reserveAuth(array $keys, array $limits, int $now, int $window): int
    {
        return $this->transaction('auth', function () use ($keys, $limits, $now, $window): int {
            $this->cleanup($now, 'auth');
            $select = $this->pdo->prepare('SELECT attempts, expires_at FROM gframe_rate_buckets WHERE bucket_key = ?');
            $buckets = [];
            $retry = 0;
            foreach ($keys as $kind => $key) {
                $select->execute([$key]);
                $bucket = $select->fetch(PDO::FETCH_ASSOC);
                if ($bucket !== false && (int)$bucket['expires_at'] > $now) {
                    $buckets[$key] = $bucket;
                    if ((int)$bucket['attempts'] >= $limits[$kind]) $retry = max($retry, (int)$bucket['expires_at'] - $now);
                }
            }
            if ($retry > 0) return $retry;
            $insert = $this->pdo->prepare('INSERT INTO gframe_rate_buckets (bucket_key, attempts, expires_at) VALUES (?, 1, ?)');
            $update = $this->pdo->prepare('UPDATE gframe_rate_buckets SET attempts = attempts + 1 WHERE bucket_key = ?');
            foreach ($keys as $key) {
                if (isset($buckets[$key])) $update->execute([$key]);
                else $insert->execute([$key, $now + $window]);
            }
            return 0;
        });
    }

    /** @return array{retry:positive-int, token:null}|array{retry:0, token:string} */
    public function reserveMail(string $key, int $maximum, int $window, int $now): array
    {
        return $this->transaction('mail', function () use ($key, $maximum, $window, $now): array {
            $this->cleanup($now, 'mail');
            $query = $this->pdo->prepare('SELECT reserved_at FROM gframe_rate_reservations WHERE bucket_key = ? AND reserved_at > ? ORDER BY reserved_at');
            $query->execute([$key, $now - $window]);
            $attempts = $query->fetchAll(PDO::FETCH_COLUMN);
            if (count($attempts) >= $maximum) {
                return ['retry' => max(1, (int)$attempts[count($attempts) - $maximum] + $window - $now), 'token' => null];
            }
            $token = bin2hex(random_bytes(16));
            $insert = $this->pdo->prepare('INSERT INTO gframe_rate_reservations (reservation_id, bucket_key, reserved_at, expires_at) VALUES (?, ?, ?, ?)');
            $insert->execute([$token, $key, $now, $now + $window]);
            return ['retry' => 0, 'token' => $token];
        });
    }

    public function releaseMail(string $token): void
    {
        $this->transaction('mail', function () use ($token): void {
            $delete = $this->pdo->prepare('DELETE FROM gframe_rate_reservations WHERE reservation_id = ?');
            $delete->execute([$token]);
        });
    }

    /** May also be invoked by the application's scheduled maintenance. */
    public function prune(int $now): int
    {
        $count = 0;
        foreach (['auth', 'mail'] as $kind) {
            $count += $this->transaction($kind, fn(): int => $this->cleanup($now, $kind));
        }
        return $count;
    }

    private function cleanup(int $now, string $kind): int
    {
        $table = $kind === 'auth' ? 'gframe_rate_buckets' : 'gframe_rate_reservations';
        $delete = $this->pdo->prepare("DELETE FROM {$table} WHERE expires_at <= ?");
        $delete->execute([$now]);
        return $delete->rowCount();
    }

    private function transaction(string $kind, Closure $work): mixed
    {
        if ($this->pdo->inTransaction()) throw new RuntimeException('Rate limits require an independent transaction.');
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($driver, ['mysql', 'sqlite'], true)) throw new RuntimeException('Unsupported rate limit driver.');
        $previousTimeout = $driver === 'mysql'
            ? (int)$this->pdo->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn()
            : (int)$this->pdo->query('PRAGMA busy_timeout')->fetchColumn();
        try {
            $this->setLockTimeout($driver, $driver === 'mysql' ? $this->lockTimeoutSeconds : $this->lockTimeoutSeconds * 1000);
            $this->pdo->beginTransaction();
            // An actual write locks the permanent mutex row, including SQLite's writer lock.
            $lock = $this->pdo->prepare('UPDATE gframe_rate_locks SET revision = revision + 1 WHERE limiter = ?');
            $lock->execute([$kind]);
            if ($lock->rowCount() !== 1) throw new RuntimeException('Rate limit migration missing.');
            $result = $work();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        } finally {
            $this->setLockTimeout($driver, $previousTimeout);
        }
    }

    private function setLockTimeout(string $driver, int $timeout): void
    {
        $this->pdo->exec($driver === 'mysql'
            ? 'SET SESSION innodb_lock_wait_timeout = ' . $timeout
            : 'PRAGMA busy_timeout = ' . $timeout);
    }
}
