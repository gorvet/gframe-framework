<?php

namespace GFrame\Session;

use PDO;
use RuntimeException;
use SessionHandlerInterface;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;

final class DatabaseSessionHandler implements SessionHandlerInterface, SessionIdInterface, SessionUpdateTimestampHandlerInterface, ActiveSessionRegistry
{
    private array $owners = [];
    private array $roles = [];
    private array $roleVersions = [];
    private array $authorizationVersions = [];
    private array $tenantRoles = [];
    private array $tenantRoleVersions = [];

    public function __construct(private readonly PDO $database, private readonly int $lifetime = 1800)
    {
    }

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $hash = $this->hash($id);
        $statement = $this->database->prepare('SELECT s.user_id, s.payload, s.role_id, s.role_version, s.authorization_version, s.tenant_role_id, s.tenant_role_version, r.security_version AS current_role_version, tr.security_version AS current_tenant_role_version, account.authorization_version AS current_authorization_version, account.status AS account_status FROM gframe_sessions s LEFT JOIN roles r ON r.role_id = s.role_id LEFT JOIN roles tr ON tr.role_id = s.tenant_role_id LEFT JOIN users account ON account.user_id = s.user_id WHERE s.session_hash = ? AND s.expires_at >= ? LIMIT 1');
        $statement->execute([$hash, time()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) return '';
        if ((int)($row['user_id'] ?? 0) > 0 && (string)($row['account_status'] ?? '') !== 'verify') {
            $this->deleteHash($hash);
            return '';
        }
        $this->owners[$hash] = (int)($row['user_id'] ?? 0);
        $this->roles[$hash] = (int)($row['role_id'] ?? 0);
        $this->roleVersions[$hash] = (int)($row['role_version'] ?? 1);
        $this->authorizationVersions[$hash] = (int)($row['authorization_version'] ?? 1);
        $this->tenantRoles[$hash] = (int)($row['tenant_role_id'] ?? 0);
        $this->tenantRoleVersions[$hash] = (int)($row['tenant_role_version'] ?? 1);
        if ($this->roles[$hash] > 0 && $this->roleVersions[$hash] !== (int)($row['current_role_version'] ?? 1)) {
            SessionRuntime::markAuthorizationStale();
        }
        if ($this->owners[$hash] > 0 && $this->authorizationVersions[$hash] !== (int)($row['current_authorization_version'] ?? 1)) SessionRuntime::markAuthorizationStale();
        if ($this->tenantRoles[$hash] > 0 && $this->tenantRoleVersions[$hash] !== (int)($row['current_tenant_role_version'] ?? 0)) SessionRuntime::markAuthorizationStale();
        return (string)($row['payload'] ?? '');
    }

    public function write(string $id, string $data): bool
    {
        $hash = $this->hash($id);
        $userID = (int)($this->owners[$hash] ?? 0);
        $now = time();
        if ($this->driver() === 'sqlite') {
            $sql = 'INSERT INTO gframe_sessions (session_hash, user_id, role_id, role_version, authorization_version, tenant_role_id, tenant_role_version, payload, last_activity, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) '
                . 'ON CONFLICT(session_hash) DO UPDATE SET user_id = excluded.user_id, role_id = excluded.role_id, role_version = excluded.role_version, authorization_version = excluded.authorization_version, tenant_role_id = excluded.tenant_role_id, tenant_role_version = excluded.tenant_role_version, payload = excluded.payload, last_activity = excluded.last_activity, expires_at = excluded.expires_at';
        } else {
            $sql = 'INSERT INTO gframe_sessions (session_hash, user_id, role_id, role_version, authorization_version, tenant_role_id, tenant_role_version, payload, last_activity, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), role_id = VALUES(role_id), role_version = VALUES(role_version), authorization_version = VALUES(authorization_version), tenant_role_id = VALUES(tenant_role_id), tenant_role_version = VALUES(tenant_role_version), payload = VALUES(payload), last_activity = VALUES(last_activity), expires_at = VALUES(expires_at)';
        }
        if ($userID <= 0) return $this->database->prepare($sql)->execute([$hash, null, null, 1, 1, null, 1, $data, $now, $now + $this->lifetime]);

        $statement = $this->database->prepare('UPDATE gframe_sessions SET role_id = ?, role_version = ?, authorization_version = ?, tenant_role_id = ?, tenant_role_version = ?, payload = ?, last_activity = ?, expires_at = ? WHERE session_hash = ? AND user_id = ? AND expires_at >= ?');
        $statement->execute([(int)($this->roles[$hash] ?? 0) ?: null, (int)($this->roleVersions[$hash] ?? 1), (int)($this->authorizationVersions[$hash] ?? 1), (int)($this->tenantRoles[$hash] ?? 0) ?: null, (int)($this->tenantRoleVersions[$hash] ?? 1), $data, $now, $now + $this->lifetime, $hash, $userID, $now]);
        return true;
    }

    public function destroy(string $id): bool
    {
        $hash = $this->hash($id);
        unset($this->owners[$hash]);
        unset($this->roles[$hash], $this->roleVersions[$hash]);
        unset($this->authorizationVersions[$hash]);
        unset($this->tenantRoles[$hash], $this->tenantRoleVersions[$hash]);
        return $this->deleteHash($hash);
    }

    public function gc(int $max_lifetime): int|false
    {
        $statement = $this->database->prepare('DELETE FROM gframe_sessions WHERE expires_at < ?');
        $statement->execute([time()]);
        return $statement->rowCount();
    }

    public function create_sid(): string { return bin2hex(random_bytes(32)); }

    public function validateId(string $id): bool
    {
        $statement = $this->database->prepare('SELECT 1 FROM gframe_sessions WHERE session_hash = ? AND expires_at >= ? LIMIT 1');
        $statement->execute([$this->hash($id), time()]);
        return (bool)$statement->fetchColumn();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $hash = $this->hash($id);
        $now = time();
        $statement = $this->database->prepare('UPDATE gframe_sessions SET last_activity = ?, expires_at = ? WHERE session_hash = ? AND expires_at >= ?');
        return $statement->execute([$now, $now + $this->lifetime, $hash, $now]);
    }

    public function register(int $userID, string $sessionID, array $authorization = []): void
    {
        if ($userID <= 0 || $sessionID === '') throw new RuntimeException('No se puede registrar una sesión sin usuario e identificador.');
        $hash = $this->hash($sessionID);
        $now = time();
        $payload = session_status() === PHP_SESSION_ACTIVE ? session_encode() : '';
        if ($payload === false) throw new RuntimeException('No se pudo serializar la sesión.');
        $roleID = (int)($authorization['role_id'] ?? 0);
        $roleVersion = max(1, (int)($authorization['role_version'] ?? 1));
        $authorizationVersion = max(1, (int)($authorization['authorization_version'] ?? 1));
        $ownsTransaction = !$this->database->inTransaction();
        if ($ownsTransaction) $this->database->beginTransaction();
        try {
            $lock = $this->driver() === 'mysql' ? ' FOR UPDATE' : '';
            $statement = $this->database->prepare('SELECT status FROM users WHERE user_id = ? LIMIT 1' . $lock);
            $statement->execute([$userID]);
            if ((string)$statement->fetchColumn() !== 'verify') throw new RuntimeException('La cuenta no admite nuevas sesiones.');

            $statement = $this->database->prepare('INSERT INTO gframe_sessions (session_hash, user_id, role_id, role_version, authorization_version, tenant_role_id, tenant_role_version, payload, last_activity, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$hash, $userID, $roleID ?: null, $roleVersion, $authorizationVersion, null, 1, $payload, $now, $now + $this->lifetime]);
            if ($ownsTransaction) $this->database->commit();
        } catch (\Exception $exception) {
            if ($ownsTransaction && $this->database->inTransaction()) $this->database->rollBack();
            throw $exception;
        }

        $this->owners[$hash] = $userID;
        $this->roles[$hash] = $roleID;
        $this->roleVersions[$hash] = $roleVersion;
        $this->authorizationVersions[$hash] = $authorizationVersion;
        $this->tenantRoles[$hash] = 0;
        $this->tenantRoleVersions[$hash] = 1;
    }

    public function unregister(int $userID, string $sessionID): void
    {
        if ($userID <= 0 || $sessionID === '') return;
        $hash = $this->hash($sessionID);
        unset($this->owners[$hash]);
        unset($this->roles[$hash], $this->roleVersions[$hash]);
        unset($this->authorizationVersions[$hash]);
        unset($this->tenantRoles[$hash], $this->tenantRoleVersions[$hash]);
        $this->deleteHash($hash);
    }

    public function revokeUser(int $userID, bool $block = false): int
    {
        if ($userID <= 0) return 0;
        $statement = $this->database->prepare('DELETE FROM gframe_sessions WHERE user_id = ?');
        $statement->execute([$userID]);
        return $statement->rowCount();
    }

    public function allowUser(int $userID): void
    {
        // El estado de la cuenta se consulta en users al iniciar sesión.
    }

    public function publishRoleVersion(int $roleID, int $version): void
    {
    }

    public function publishUserAuthorizationVersion(int $userID, int $version): void
    {
    }

    public function updateAuthorization(int $userID, string $sessionID, int $roleID, int $roleVersion, int $authorizationVersion = 1): void
    {
        if ($userID <= 0 || $sessionID === '') return;
        $hash = $this->hash($sessionID);
        $this->roles[$hash] = $roleID;
        $this->roleVersions[$hash] = max(1, $roleVersion);
        $this->authorizationVersions[$hash] = max(1, $authorizationVersion);
        $statement = $this->database->prepare('UPDATE gframe_sessions SET role_id = ?, role_version = ?, authorization_version = ?, tenant_role_id = NULL, tenant_role_version = 1 WHERE session_hash = ? AND user_id = ?');
        $statement->execute([$roleID, max(1, $roleVersion), max(1, $authorizationVersion), $hash, $userID]);
        $this->tenantRoles[$hash] = 0;
        $this->tenantRoleVersions[$hash] = 1;
    }

    public function updateTenantAuthorization(int $userID, string $sessionID, int $roleID, int $roleVersion): void
    {
        if ($userID <= 0 || $sessionID === '') return;
        $hash = $this->hash($sessionID);
        $this->tenantRoles[$hash] = $roleID;
        $this->tenantRoleVersions[$hash] = max(1, $roleVersion);
        $this->database->prepare('UPDATE gframe_sessions SET tenant_role_id = ?, tenant_role_version = ? WHERE session_hash = ? AND user_id = ?')
            ->execute([$roleID, max(1, $roleVersion), $hash, $userID]);
    }

    private function deleteHash(string $hash): bool
    {
        return $this->database->prepare('DELETE FROM gframe_sessions WHERE session_hash = ?')->execute([$hash]);
    }

    private function driver(): string { return (string)$this->database->getAttribute(PDO::ATTR_DRIVER_NAME); }
    private function hash(string $id): string { return hash('sha256', $id); }
}
