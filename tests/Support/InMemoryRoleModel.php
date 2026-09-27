<?php

namespace GFrame\Tests\Support;

use GFrame\Auth\RoleModel;

final class InMemoryRoleModel extends RoleModel
{
    public array $roles = [
        1 => ['role_id' => 1, 'name' => 'Superadministrador', 'slug' => 'superadministrator', 'is_system' => 1],
        2 => ['role_id' => 2, 'name' => 'Usuario registrado', 'slug' => 'registered', 'is_system' => 1],
    ];
    public array $users = [1 => 1, 2 => 2];
    public array $permissions = [];
    private int $nextRoleID = 3;
    private int $nextPermissionID = 1;

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

    public function createPermission(string $name, string $slug): int
    {
        return $this->nextPermissionID++;
    }

    public function assignPermission(int $roleID, int $permissionID): void
    {
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
