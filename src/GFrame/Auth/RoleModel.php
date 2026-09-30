<?php

namespace GFrame\Auth;

use GFrame\Auth\Contracts\RoleAdministrationRepository;
use RuntimeException;

class RoleModel extends \ORM implements RoleAdministrationRepository
{
    protected $table = 'roles';
    protected $primaryKey = 'role_id';
    protected $fillable = ['role_id', 'user_id', 'tenant_id', 'is_active', 'permission_overrides_json', 'permissions_json', 'name', 'slug', 'is_system', 'security_version'];

    public function findUserRole(int $userID): ?array
    {
        if ($userID <= 0) {
            return null;
        }
        $rows = self::queryTable('users')
            ->reset()->useStrictComparison(false)
            ->select('roles.role_id', 'roles.name', 'roles.slug', 'roles.is_system', 'roles.security_version', 'roles.permissions_json', 'users.authorization_version', 'users.permission_overrides_json')
            ->join('roles', 'roles.role_id', '=', 'users.role_id')
            ->where('users.user_id', '=', $userID)->limit(1)->get();
        return isset($rows[0]) ? (array)$rows[0] : null;
    }

    public function findRoleByID(int $roleID): ?array
    {
        if ($roleID <= 0) {
            return null;
        }
        $rows = $this->reset()->useStrictComparison(false)
            ->select('role_id', 'name', 'slug', 'is_system', 'security_version', 'permissions_json')
            ->where('role_id', '=', $roleID)->limit(1)->get();
        return isset($rows[0]) ? (array)$rows[0] : null;
    }

    public function findRoleBySlug(string $slug): ?array
    {
        $slug = mb_strtolower(trim($slug), 'UTF-8');
        if ($slug === '') {
            return null;
        }
        $rows = $this->reset()->useStrictComparison(false)
            ->select('role_id', 'name', 'slug', 'is_system', 'security_version', 'permissions_json')
            ->where('slug', '=', $slug)->limit(1)->get();
        return isset($rows[0]) ? (array)$rows[0] : null;
    }

    public function assignableRoles(): array
    {
        return $this->reset()
            ->useStrictComparison(false)
            ->select('role_id', 'name', 'slug', 'is_system')
            ->where('slug', '<>', SystemRole::SUPERADMINISTRATOR)
            ->orderBy('name', 'ASC')
            ->get();
    }

    public function roleHasPermission(int $roleID, string $permission): bool
    {
        if ($roleID <= 0 || trim($permission) === '') {
            return false;
        }
        $role = $this->findRoleByID($roleID);
        return in_array(mb_strtolower(trim($permission), 'UTF-8'), $this->enabledPermissions((string)($role['permissions_json'] ?? '')), true);
    }

    public function authorizationForUser(int $userID, int $tenantID = 0): ?array
    {
        $role = $tenantID > 0 ? $this->findTenantRole($userID, $tenantID) : $this->findUserRole($userID);
        if ($role === null) return null;
        $authorization = $this->authorizationForRole((int)$role['role_id'], $role);
        if ($authorization === null) return null;
        $permissions = array_fill_keys((array)$authorization['permissions'], true);
        foreach ($this->decodeMap((string)($role['permission_overrides_json'] ?? '')) as $slug => $allowed) $permissions[$slug] = $allowed;
        $authorization['permissions'] = array_keys(array_filter($permissions));
        $authorization['authorization_version'] = max(1, (int)($role['authorization_version'] ?? 1));
        $authorization['tenant_id'] = $tenantID;
        return $authorization;
    }

    public function setPermissionOverride(int $userID, string $permission, string $effect, int $tenantID = 0): void
    {
        $table = $tenantID > 0 ? 'tenant_memberships' : 'users';
        $query = self::queryTable($table)->reset()->useStrictComparison(false)->where('user_id', '=', $userID);
        if ($tenantID > 0) $query->where('tenant_id', '=', $tenantID);
        $rows = $query->select('permission_overrides_json')->limit(1)->get();
        if (!isset($rows[0])) throw new RuntimeException('No existe el usuario o la membresía indicada.');
        $map = $this->decodeMap((string)($rows[0]['permission_overrides_json'] ?? ''));
        $map[$permission] = $effect === 'allow';
        $query->reset()->queryTable($table)->where('user_id', '=', $userID);
        if ($tenantID > 0) $query->where('tenant_id', '=', $tenantID);
        $query->update(['permission_overrides_json' => $this->encodeMap($map)]);
    }

    public function removePermissionOverride(int $userID, string $permission, int $tenantID = 0): void
    {
        $table = $tenantID > 0 ? 'tenant_memberships' : 'users';
        $query = self::queryTable($table)->reset()->useStrictComparison(false)->where('user_id', '=', $userID);
        if ($tenantID > 0) $query->where('tenant_id', '=', $tenantID);
        $rows = $query->select('permission_overrides_json')->limit(1)->get();
        if (!isset($rows[0])) throw new RuntimeException('No existe el usuario o la membresía indicada.');
        $map = $this->decodeMap((string)($rows[0]['permission_overrides_json'] ?? ''));
        unset($map[$permission]);
        $query->reset()->queryTable($table)->where('user_id', '=', $userID);
        if ($tenantID > 0) $query->where('tenant_id', '=', $tenantID);
        $query->update(['permission_overrides_json' => $this->encodeMap($map)]);
    }

    public function assignTenantRole(int $userID, int $tenantID, int $roleID): void
    {
        $query = self::queryTable('tenant_memberships')->reset()->useStrictComparison(false)
            ->where('user_id', '=', $userID)->where('tenant_id', '=', $tenantID);
        if ($query->exists()) $query->update(['role_id' => $roleID, 'is_active' => 1]);
        else (new static(['user_id' => $userID, 'tenant_id' => $tenantID, 'role_id' => $roleID, 'is_active' => 1, 'permission_overrides_json' => '{}']))->fromTable('tenant_memberships')->insert();
    }

    public function findTenantMembership(int $userID, int $tenantID): ?array
    {
        $rows = self::queryTable('tenant_memberships')->reset()->useStrictComparison(false)
            ->select('tenant_memberships.user_id', 'tenant_memberships.tenant_id', 'tenant_memberships.role_id', 'tenant_memberships.is_active', 'roles.slug')
            ->join('roles', 'roles.role_id', '=', 'tenant_memberships.role_id')
            ->where('tenant_memberships.user_id', '=', $userID)
            ->where('tenant_memberships.tenant_id', '=', $tenantID)->limit(1)->get();
        return isset($rows[0]) ? (array)$rows[0] : null;
    }

    public function deactivateTenantMembership(int $userID, int $tenantID): void
    {
        self::queryTable('tenant_memberships')->reset()->useStrictComparison(false)
            ->where('user_id', '=', $userID)->where('tenant_id', '=', $tenantID)->update(['is_active' => 0]);
    }

    public function incrementUserAuthorizationVersion(int $userID): int
    {
        self::queryTable('users')->reset()->useStrictComparison(false)->where('user_id', '=', $userID)
            ->updateRaw('authorization_version = authorization_version + 1');
        $rows = self::queryTable('users')->reset()->useStrictComparison(false)->select('authorization_version')->where('user_id', '=', $userID)->limit(1)->get();
        return max(1, (int)($rows[0]['authorization_version'] ?? 1));
    }

    public function authorizationForRole(int $roleID, ?array $role = null): ?array
    {
        $role ??= $this->findRoleByID($roleID);
        if ($role === null) return null;
        return [
            'role_id' => $roleID,
            'role' => (string)($role['slug'] ?? ''),
            'role_version' => max(1, (int)($role['security_version'] ?? 1)),
            'permissions' => $this->enabledPermissions((string)($role['permissions_json'] ?? '')),
            'bypass' => $this->normalizeRoleSlug((string)($role['slug'] ?? '')) === SystemRole::SUPERADMINISTRATOR,
        ];
    }

    public function incrementSecurityVersion(int $roleID): int
    {
        self::queryTable('roles')->reset()->useStrictComparison(false)
            ->where('role_id', '=', $roleID)
            ->updateRaw('security_version = security_version + 1');
        $role = $this->findRoleByID($roleID);
        return max(1, (int)($role['security_version'] ?? 1));
    }

    public function countUsersWithRole(int $roleID): int
    {
        if ($roleID <= 0) return 0;
        $global = (int)self::queryTable('users')->reset()->useStrictComparison(false)
            ->where('role_id', '=', $roleID)->count('*');
        $memberships = (int)self::queryTable('tenant_memberships')->reset()->useStrictComparison(false)
            ->where('role_id', '=', $roleID)->count('*');
        return $global + $memberships;
    }

    public function createRole(string $name, string $slug, bool $isSystem = false): int
    {
        return (int)(new static([
            'name' => trim($name),
            'slug' => mb_strtolower(trim($slug), 'UTF-8'),
            'is_system' => $isSystem ? 1 : 0,
        ]))->insert();
    }

    public function setRolePermission(int $roleID, string $permission, bool $allowed): void
    {
        $role = $this->findRoleByID($roleID);
        $map = $this->decodeMap((string)($role['permissions_json'] ?? ''));
        $map[$permission] = $allowed;
        $this->reset()->useStrictComparison(false)->where('role_id', '=', $roleID)->update(['permissions_json' => $this->encodeMap($map)]);
    }

    public function assignRole(int $userID, int $roleID): void
    {
        self::queryTable('users')->reset()->useStrictComparison(false)
            ->where('user_id', '=', $userID)->update(['role_id' => $roleID]);
    }

    public function deleteRole(int $roleID): void
    {
        $this->reset()->useStrictComparison(false)->where('role_id', '=', $roleID)->deleteWhere();
    }

    private function normalizeRoleSlug(string $slug): string
    {
        return mb_strtolower(trim($slug), 'UTF-8');
    }

    private function findTenantRole(int $userID, int $tenantID): ?array
    {
        $rows = self::queryTable('tenant_memberships')->reset()->useStrictComparison(false)
            ->select('roles.role_id', 'roles.name', 'roles.slug', 'roles.is_system', 'roles.security_version', 'roles.permissions_json', 'users.authorization_version', 'tenant_memberships.permission_overrides_json')
            ->join('roles', 'roles.role_id', '=', 'tenant_memberships.role_id')
            ->join('users', 'users.user_id', '=', 'tenant_memberships.user_id')
            ->where('tenant_memberships.user_id', '=', $userID)
            ->where('tenant_memberships.tenant_id', '=', $tenantID)
            ->where('tenant_memberships.is_active', '=', 1)->limit(1)->get();
        return isset($rows[0]) ? (array)$rows[0] : null;
    }

    private function decodeMap(string $json): array
    {
        $map = json_decode($json, true);
        return is_array($map) ? array_map(static fn($value): bool => $value === true, $map) : [];
    }

    private function encodeMap(array $map): string { return json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'; }
    private function enabledPermissions(string $json): array { return array_keys(array_filter($this->decodeMap($json))); }
}
