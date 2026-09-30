<?php

namespace GFrame\Tests\Support;

use GFrame\Auth\RoleModel;

final class InMemoryRoleModel extends RoleModel
{
    public array $roles = [
        1 => ['role_id' => 1, 'name' => 'Superadministrador', 'slug' => 'superadministrator', 'is_system' => 1, 'security_version' => 1],
        2 => ['role_id' => 2, 'name' => 'Usuario registrado', 'slug' => 'registered', 'is_system' => 1, 'security_version' => 1],
    ];
    public array $users = [1 => 1, 2 => 2];
    public array $permissions = [];
    public array $authorizationVersions = [1 => 1, 2 => 1];
    public array $userOverrides = [];
    public array $tenantRoles = [];
    public array $inactiveTenantMemberships = [];
    public int $authorizationReads = 0;
    private int $nextRoleID = 3;

    public function findUserRole(int $userID): ?array
    {
        $roleID = $this->users[$userID] ?? 0;
        return $this->roles[$roleID] ?? null;
    }

    public function findRoleByID(int $roleID): ?array
    {
        return $this->roles[$roleID] ?? null;
    }

    public function findRoleBySlug(string $slug): ?array
    {
        foreach ($this->roles as $role) {
            if ($role['slug'] === $slug) {
                return $role;
            }
        }
        return null;
    }

    public function roleHasPermission(int $roleID, string $permission): bool
    {
        return in_array($permission, $this->permissions[$roleID] ?? [], true);
    }

    public function authorizationForUser(int $userID, int $tenantID = 0): ?array
    {
        $this->authorizationReads++;
        $roleID = $tenantID > 0 ? ($this->tenantRoles[$userID][$tenantID] ?? 0) : ($this->users[$userID] ?? 0);
        $role = $this->roles[$roleID] ?? null;
        if ($role === null) return null;
        $authorization = $this->authorizationForRole((int)$role['role_id'], $role);
        $authorization['authorization_version'] = $this->authorizationVersions[$userID] ?? 1;
        $permissions = array_fill_keys($authorization['permissions'], true);
        foreach ($this->userOverrides[$userID][$tenantID] ?? [] as $permission => $allowed) {
            $permissions[$permission] = $allowed;
        }
        $authorization['permissions'] = array_keys(array_filter($permissions));
        $authorization['tenant_id'] = $tenantID;
        return $authorization;
    }

    public function authorizationForRole(int $roleID, ?array $role = null): ?array
    {
        $role ??= $this->findRoleByID($roleID);
        if ($role === null) return null;
        return [
            'role_id' => $roleID,
            'role' => $role['slug'],
            'role_version' => (int)($role['security_version'] ?? 1),
            'authorization_version' => 1,
            'permissions' => $this->permissions[$roleID] ?? [],
            'bypass' => $role['slug'] === 'superadministrator',
        ];
    }

    public function incrementSecurityVersion(int $roleID): int
    {
        $this->roles[$roleID]['security_version'] = (int)($this->roles[$roleID]['security_version'] ?? 1) + 1;
        return $this->roles[$roleID]['security_version'];
    }

    public function countUsersWithRole(int $roleID): int
    {
        return count(array_filter($this->users, static fn(int $assigned): bool => $assigned === $roleID));
    }

    public function createRole(string $name, string $slug, bool $isSystem = false): int
    {
        $roleID = $this->nextRoleID++;
        $this->roles[$roleID] = [
            'role_id' => $roleID,
            'name' => $name,
            'slug' => $slug,
            'is_system' => $isSystem ? 1 : 0,
        ];
        return $roleID;
    }

    public function setRolePermission(int $roleID, string $permission, bool $allowed): void
    {
        $permissions = array_fill_keys($this->permissions[$roleID] ?? [], true);
        $permissions[$permission] = $allowed;
        $this->permissions[$roleID] = array_keys(array_filter($permissions));
    }

    public function setPermissionOverride(int $userID, string $permission, string $effect, int $tenantID = 0): void
    {
        $this->userOverrides[$userID][$tenantID][$permission] = $effect === 'allow';
    }

    public function removePermissionOverride(int $userID, string $permission, int $tenantID = 0): void
    {
        unset($this->userOverrides[$userID][$tenantID][$permission]);
    }

    public function assignTenantRole(int $userID, int $tenantID, int $roleID): void
    {
        $this->tenantRoles[$userID][$tenantID] = $roleID;
        unset($this->inactiveTenantMemberships[$userID][$tenantID]);
    }

    public function findTenantMembership(int $userID, int $tenantID): ?array
    {
        $roleID = $this->tenantRoles[$userID][$tenantID] ?? 0;
        if ($roleID === 0) return null;
        return [
            'user_id' => $userID,
            'tenant_id' => $tenantID,
            'role_id' => $roleID,
            'slug' => $this->roles[$roleID]['slug'] ?? '',
            'is_active' => isset($this->inactiveTenantMemberships[$userID][$tenantID]) ? 0 : 1,
        ];
    }

    public function deactivateTenantMembership(int $userID, int $tenantID): void
    {
        $this->inactiveTenantMemberships[$userID][$tenantID] = true;
    }

    public function incrementUserAuthorizationVersion(int $userID): int
    {
        return $this->authorizationVersions[$userID] = ($this->authorizationVersions[$userID] ?? 1) + 1;
    }

    public function assignRole(int $userID, int $roleID): void
    {
        $this->users[$userID] = $roleID;
    }

    public function deleteRole(int $roleID): void
    {
        unset($this->roles[$roleID]);
    }
}
