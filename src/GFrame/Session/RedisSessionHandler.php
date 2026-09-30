<?php

namespace GFrame\Session;

use PDO;
use RuntimeException;
use SessionHandlerInterface;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;

final class RedisSessionHandler implements SessionHandlerInterface, SessionIdInterface, SessionUpdateTimestampHandlerInterface, ActiveSessionRegistry
{
    private array $owners = [];
    private array $roles = [];
    private array $roleVersions = [];
    private array $authorizationVersions = [];
    private array $tenantRoles = [];
    private array $tenantRoleVersions = [];

    public function __construct(
        private readonly object $redis,
        private readonly string $prefix = 'gframe:session:',
        private readonly int $lifetime = 1800
    ) {
    }

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $hash = $this->hash($id);
        $owner = $this->redis->get($this->ownerKey($hash));
        if ($owner !== false) {
            $fields = explode(':', (string)$owner);
            if (count($fields) === 7) unset($fields[1]); // Sesiones creadas antes de retirar generation.
            [$userID, $roleID, $roleVersion, $authorizationVersion, $tenantRoleID, $tenantRoleVersion] = array_pad(array_values($fields), 6, '0');
            $this->owners[$hash] = (int)$userID;
            $this->roles[$hash] = (int)$roleID;
            $this->roleVersions[$hash] = (int)$roleVersion;
            $this->authorizationVersions[$hash] = (int)$authorizationVersion;
            $this->tenantRoles[$hash] = (int)$tenantRoleID;
            $this->tenantRoleVersions[$hash] = (int)$tenantRoleVersion;
            $currentRoleVersion = (int)($this->redis->get($this->roleVersionKey((int)$roleID)) ?: $roleVersion);
            if ((int)$roleID > 0 && (int)$roleVersion !== $currentRoleVersion) SessionRuntime::markAuthorizationStale();
            $currentAuthorizationVersion = (int)($this->redis->get($this->authorizationVersionKey((int)$userID)) ?: $authorizationVersion);
            if ((int)$userID > 0 && (int)$authorizationVersion !== $currentAuthorizationVersion) SessionRuntime::markAuthorizationStale();
            $currentTenantRoleVersion = (int)($this->redis->get($this->roleVersionKey((int)$tenantRoleID)) ?: $tenantRoleVersion);
            if ((int)$tenantRoleID > 0 && (int)$tenantRoleVersion !== $currentTenantRoleVersion) SessionRuntime::markAuthorizationStale();
        }
        $payload = $this->redis->get($this->dataKey($hash));
        return $payload === false ? '' : (string)$payload;
    }

    public function write(string $id, string $data): bool
    {
        $hash = $this->hash($id);
        $userID = (int)($this->owners[$hash] ?? 0);
        $script = <<<'LUA'
local owner = tonumber(ARGV[3]) or 0
local role = tonumber(ARGV[5]) or 0
local roleVersion = tonumber(ARGV[6]) or 1
local authorizationVersion = tonumber(ARGV[7]) or 1
local tenantRole = tonumber(ARGV[8]) or 0
local tenantRoleVersion = tonumber(ARGV[9]) or 1
if owner > 0 and (redis.call('EXISTS', KEYS[1]) == 0 or redis.call('EXISTS', KEYS[3]) == 0 or redis.call('EXISTS', KEYS[2]) == 1) then
    redis.call('DEL', KEYS[1], KEYS[3])
    redis.call('SREM', KEYS[4], ARGV[4])
    return 2
end
redis.call('SETEX', KEYS[1], ARGV[1], ARGV[2])
if owner > 0 then
    redis.call('SETEX', KEYS[3], ARGV[1], owner .. ':' .. role .. ':' .. roleVersion .. ':' .. authorizationVersion .. ':' .. tenantRole .. ':' .. tenantRoleVersion)
    redis.call('SADD', KEYS[4], ARGV[4])
    redis.call('EXPIRE', KEYS[4], ARGV[1])
end
return 1
LUA;
        $result = $this->redis->eval($script, [$this->dataKey($hash), $this->blockedKey($userID), $this->ownerKey($hash), $this->userKey($userID), $this->lifetime, $data, $userID, $hash, (int)($this->roles[$hash] ?? 0), (int)($this->roleVersions[$hash] ?? 1), (int)($this->authorizationVersions[$hash] ?? 1), (int)($this->tenantRoles[$hash] ?? 0), (int)($this->tenantRoleVersions[$hash] ?? 1)], 4);
        return in_array((int)$result, [1, 2], true);
    }

    public function destroy(string $id): bool
    {
        $hash = $this->hash($id);
        $storedOwner = (string)($this->redis->get($this->ownerKey($hash)) ?: '0:0');
        $userID = (int)($this->owners[$hash] ?? explode(':', $storedOwner, 2)[0]);
        $this->redis->del($this->dataKey($hash), $this->ownerKey($hash));
        if ($userID > 0) $this->redis->sRem($this->userKey($userID), $hash);
        unset($this->owners[$hash]);
        unset($this->roles[$hash], $this->roleVersions[$hash]);
        unset($this->authorizationVersions[$hash]);
        unset($this->tenantRoles[$hash], $this->tenantRoleVersions[$hash]);
        return true;
    }

    public function gc(int $max_lifetime): int|false { return 0; }
    public function create_sid(): string { return bin2hex(random_bytes(32)); }
    public function validateId(string $id): bool { return (bool)$this->redis->exists($this->dataKey($this->hash($id))); }

    public function updateTimestamp(string $id, string $data): bool
    {
        $hash = $this->hash($id);
        $userID = (int)($this->owners[$hash] ?? 0);
        if ($userID <= 0) {
            $this->redis->expire($this->dataKey($hash), $this->lifetime);
            return true;
        }
        $script = <<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 0 or redis.call('EXISTS', KEYS[2]) == 0 or redis.call('EXISTS', KEYS[4]) == 1 then return 0 end
redis.call('EXPIRE', KEYS[1], ARGV[1])
redis.call('EXPIRE', KEYS[2], ARGV[1])
redis.call('EXPIRE', KEYS[3], ARGV[1])
return 1
LUA;
        $this->redis->eval($script, [$this->dataKey($hash), $this->ownerKey($hash), $this->userKey($userID), $this->blockedKey($userID), $this->lifetime], 4);
        return true;
    }

    public function register(int $userID, string $sessionID, array $authorization = []): void
    {
        if ($userID <= 0 || $sessionID === '') throw new RuntimeException('No se puede registrar una sesión sin usuario e identificador.');
        $database = \DatabaseManager::connection();
        if (!$database instanceof PDO) throw new RuntimeException('No se pudo comprobar el estado de la cuenta.');
        $account = $database->prepare('SELECT status FROM users WHERE user_id = ? LIMIT 1');
        $account->execute([$userID]);
        if ((string)$account->fetchColumn() !== 'verify') throw new RuntimeException('La cuenta no admite nuevas sesiones.');
        $payload = session_status() === PHP_SESSION_ACTIVE ? session_encode() : '';
        if ($payload === false) throw new RuntimeException('No se pudo serializar la sesión.');
        $hash = $this->hash($sessionID);
        $script = <<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 1 then return 0 end
local role = tonumber(ARGV[4]) or 0
local roleVersion = tonumber(ARGV[5]) or 1
local authorizationVersion = tonumber(ARGV[6]) or 1
local currentRoleVersion = tonumber(redis.call('GET', KEYS[5]) or '0')
if role > 0 and roleVersion > currentRoleVersion then redis.call('SET', KEYS[5], roleVersion) end
local currentAuthorizationVersion = tonumber(redis.call('GET', KEYS[6]) or '0')
if authorizationVersion > currentAuthorizationVersion then redis.call('SET', KEYS[6], authorizationVersion) end
redis.call('SADD', KEYS[2], ARGV[1])
redis.call('SETEX', KEYS[3], ARGV[2], ARGV[3] .. ':' .. role .. ':' .. roleVersion .. ':' .. authorizationVersion .. ':0:1')
redis.call('SETEX', KEYS[4], ARGV[2], ARGV[7])
redis.call('EXPIRE', KEYS[2], ARGV[2])
return 1
LUA;
        $roleID = (int)($authorization['role_id'] ?? 0);
        $roleVersion = max(1, (int)($authorization['role_version'] ?? 1));
        $authorizationVersion = max(1, (int)($authorization['authorization_version'] ?? 1));
        $result = $this->redis->eval($script, [$this->blockedKey($userID), $this->userKey($userID), $this->ownerKey($hash), $this->dataKey($hash), $this->roleVersionKey($roleID), $this->authorizationVersionKey($userID), $hash, $this->lifetime, $userID, $roleID, $roleVersion, $authorizationVersion, $payload], 6);
        if ((int)$result <= 0) throw new RuntimeException('La cuenta no admite nuevas sesiones.');
        $this->owners[$hash] = $userID;
        $this->roles[$hash] = $roleID;
        $this->roleVersions[$hash] = $roleVersion;
        $this->authorizationVersions[$hash] = $authorizationVersion;
        $this->tenantRoles[$hash] = 0;
        $this->tenantRoleVersions[$hash] = 1;
    }

    public function unregister(int $userID, string $sessionID): void { $this->destroy($sessionID); }

    public function revokeUser(int $userID, bool $block = false): int
    {
        if ($userID <= 0) return 0;
        $script = <<<'LUA'
if ARGV[1] == '1' then redis.call('SET', KEYS[1], '1') end
local sessions = redis.call('SMEMBERS', KEYS[2])
for _, hash in ipairs(sessions) do
    redis.call('DEL', ARGV[2] .. 'data:' .. hash, ARGV[2] .. 'owner:' .. hash)
end
redis.call('DEL', KEYS[2])
return #sessions
LUA;
        return (int)$this->redis->eval($script, [$this->blockedKey($userID), $this->userKey($userID), $block ? '1' : '0', $this->prefix], 2);
    }

    public function allowUser(int $userID): void
    {
        if ($userID > 0) $this->redis->del($this->blockedKey($userID));
    }

    public function publishRoleVersion(int $roleID, int $version): void
    {
        if ($roleID > 0) $this->redis->set($this->roleVersionKey($roleID), max(1, $version));
    }

    public function publishUserAuthorizationVersion(int $userID, int $version): void
    {
        if ($userID > 0) $this->redis->set($this->authorizationVersionKey($userID), max(1, $version));
    }

    public function updateAuthorization(int $userID, string $sessionID, int $roleID, int $roleVersion, int $authorizationVersion = 1): void
    {
        if ($userID <= 0 || $sessionID === '') return;
        $hash = $this->hash($sessionID);
        $roleVersion = max(1, $roleVersion);
        $authorizationVersion = max(1, $authorizationVersion);
        $this->owners[$hash] = $userID;
        $this->roles[$hash] = $roleID;
        $this->roleVersions[$hash] = $roleVersion;
        $this->authorizationVersions[$hash] = $authorizationVersion;
        $this->tenantRoles[$hash] = 0;
        $this->tenantRoleVersions[$hash] = 1;
        $this->refreshOwner($hash, $userID);
    }

    public function updateTenantAuthorization(int $userID, string $sessionID, int $roleID, int $roleVersion): void
    {
        if ($userID <= 0 || $sessionID === '') return;
        $hash = $this->hash($sessionID);
        $this->tenantRoles[$hash] = $roleID;
        $this->tenantRoleVersions[$hash] = max(1, $roleVersion);
        $this->refreshOwner($hash, $userID);
    }

    private function refreshOwner(string $hash, int $userID): void
    {
        $owner = $userID . ':' . (int)($this->roles[$hash] ?? 0) . ':' . (int)($this->roleVersions[$hash] ?? 1) . ':' . (int)($this->authorizationVersions[$hash] ?? 1) . ':' . (int)($this->tenantRoles[$hash] ?? 0) . ':' . (int)($this->tenantRoleVersions[$hash] ?? 1);
        $script = <<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 0 or redis.call('EXISTS', KEYS[2]) == 0 then return 0 end
redis.call('SETEX', KEYS[2], ARGV[1], ARGV[2])
return 1
LUA;
        $this->redis->eval($script, [$this->dataKey($hash), $this->ownerKey($hash), $this->lifetime, $owner], 2);
    }

    private function hash(string $id): string { return hash('sha256', $id); }
    private function dataKey(string $hash): string { return $this->prefix . 'data:' . $hash; }
    private function ownerKey(string $hash): string { return $this->prefix . 'owner:' . $hash; }
    private function userKey(int $userID): string { return $this->prefix . 'user:' . $userID; }
    private function blockedKey(int $userID): string { return $this->prefix . 'blocked:' . $userID; }
    private function roleVersionKey(int $roleID): string { return $this->prefix . 'role-version:' . $roleID; }
    private function authorizationVersionKey(int $userID): string { return $this->prefix . 'authorization-version:' . $userID; }
}
