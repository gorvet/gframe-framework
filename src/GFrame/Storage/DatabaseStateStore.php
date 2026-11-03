<?php

namespace GFrame\Storage;

use GFrame\Config\ConfigRepository;
use PDO;
use RuntimeException;
use Throwable;

/** Database persistence for ephemeral module state, without filesystem fallbacks. */
final class DatabaseStateStore
{
    public function __construct(private readonly PDO $database) {}

    public static function configured(?string $connection = null): self
    {
        $database = \DatabaseManager::connection($connection ?? ConfigRepository::get('storage.connection'));
        if (!$database instanceof PDO) throw new RuntimeException('No se pudo abrir el almacenamiento de ejecución.');
        return new self($database);
    }

    public function create(string $namespace, string $key, array $payload, ?int $expiresAt = null): void
    {
        $this->transaction(function () use ($namespace, $key, $payload, $expiresAt): void {
            $this->prune();
            $insert = $this->database->prepare('INSERT INTO gframe_runtime_state (record_key, payload, expires_at) VALUES (?, ?, ?)');
            $insert->execute([$this->key($namespace, $key), json_encode($payload, JSON_THROW_ON_ERROR), $expiresAt]);
        });
    }

    /** Callback may change the array; failures roll back its database changes. */
    public function update(string $namespace, string $key, callable $operation, bool $consume = false): mixed
    {
        return $this->transaction(function () use ($namespace, $key, $operation, $consume): mixed {
            $recordKey = $this->key($namespace, $key);
            $query = $this->database->prepare('SELECT payload, expires_at FROM gframe_runtime_state WHERE record_key = ?');
            $query->execute([$recordKey]);
            $row = $query->fetch(PDO::FETCH_ASSOC);
            if ($row === false || ($row['expires_at'] !== null && (int)$row['expires_at'] <= time())) throw new RuntimeException('El registro no está disponible.');
            $payload = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) throw new RuntimeException('El registro no es válido.');
            $result = $operation($payload);
            if ($consume) {
                $write = $this->database->prepare('DELETE FROM gframe_runtime_state WHERE record_key = ?');
                $write->execute([$recordKey]);
            } else {
                $write = $this->database->prepare('UPDATE gframe_runtime_state SET payload = ? WHERE record_key = ?');
                $write->execute([json_encode($payload, JSON_THROW_ON_ERROR), $recordKey]);
            }
            return $result;
        });
    }

    public function delete(string $namespace, string $key): void
    {
        $this->transaction(function () use ($namespace, $key): void {
            $delete = $this->database->prepare('DELETE FROM gframe_runtime_state WHERE record_key = ?');
            $delete->execute([$this->key($namespace, $key)]);
        });
    }

    public function prune(?int $now = null): int
    {
        $delete = $this->database->prepare('DELETE FROM gframe_runtime_state WHERE expires_at IS NOT NULL AND expires_at <= ?');
        $delete->execute([$now ?? time()]);
        return $delete->rowCount();
    }

    /** Atomic TTL marker, used to rate-limit diagnostics without local stamp files. */
    public function claim(string $namespace, string $key, int $ttl): bool
    {
        return $this->transaction(function () use ($namespace, $key, $ttl): bool {
            $recordKey = $this->key($namespace, $key);
            $query = $this->database->prepare('SELECT expires_at FROM gframe_runtime_state WHERE record_key = ?');
            $query->execute([$recordKey]);
            $expires = $query->fetchColumn();
            if ($expires !== false && (int)$expires > time()) return false;
            $delete = $this->database->prepare('DELETE FROM gframe_runtime_state WHERE record_key = ?');
            $delete->execute([$recordKey]);
            $insert = $this->database->prepare('INSERT INTO gframe_runtime_state (record_key, payload, expires_at) VALUES (?, ?, ?)');
            $insert->execute([$recordKey, '{}', time() + max(1, $ttl)]);
            return true;
        });
    }

    private function key(string $namespace, string $key): string { return hash('sha256', $namespace . ':' . $key); }

    private function transaction(callable $operation): mixed
    {
        if ($this->database->inTransaction()) throw new RuntimeException('El almacenamiento requiere una transacción independiente.');
        $this->database->beginTransaction();
        try {
            $lock = $this->database->prepare('UPDATE gframe_runtime_lock SET revision = revision + 1 WHERE lock_id = 1');
            $lock->execute();
            if ($lock->rowCount() !== 1) throw new RuntimeException('Falta la migración de almacenamiento de ejecución.');
            $result = $operation();
            $this->database->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $exception;
        }
    }
}
