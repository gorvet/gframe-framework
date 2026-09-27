<?php

namespace GFrame\Auth;

use Throwable;

final class RolePermissionService
{
    public function __construct(private readonly RoleModel $roles)
    {
    }

    public function authorize(int $userID, string $permission): array
    {
        $permission = $this->normalizeSlug($permission);
        if ($userID <= 0 || $permission === '') {
            return $this->denied();
        }

        try {
            $role = $this->roles->findUserRole($userID);
            if ($role === null) {
                return $this->denied();
            }

            if ($this->isSuperadministratorRole($role)) {
                return $this->allowed($role, true);
            }

            if (!$this->roles->roleHasPermission((int)($role['role_id'] ?? 0), $permission)) {
                return $this->denied();
            }

            return $this->allowed($role);
        } catch (Throwable $exception) {
            return $this->failure($exception, 'authorization_failed');
        }
    }

    public function role(int $userID): array
    {
        if ($userID <= 0) {
            return $this->denied();
        }

        try {
            $role = $this->roles->findUserRole($userID);
            if ($role === null) {
                return $this->denied();
            }

            return $this->allowed($role, $this->isSuperadministratorRole($role));
        } catch (Throwable $exception) {
            return $this->failure($exception, 'role_lookup_failed');
        }
    }

    public function createRole(int $actorID, string $name, string $slug): array
    {
        $name = trim($name);
        $slug = $this->normalizeSlug($slug);
        if ($name === '' || $slug === '' || $slug === SystemRole::SUPERADMINISTRATOR) {
            return ['status' => 'error', 'code' => 'invalid_role'];
        }
        if (!$this->actorIsSuperadministrator($actorID)) {
            return $this->denied();
        }

        try {
            if ($this->roles->findRoleBySlug($slug) !== null) {
                return ['status' => 'error', 'code' => 'role_exists'];
            }

            $roleID = $this->roles->createRole($name, $slug);
            return ['status' => 'success', 'code' => 'role_created', 'role_id' => $roleID];
        } catch (Throwable $exception) {
            return $this->failure($exception, 'role_create_failed');
        }
    }

    public function createPermission(int $actorID, string $name, string $slug): array
    {
        $name = trim($name);
        $slug = $this->normalizeSlug($slug);
        if ($name === '' || $slug === '') {
            return ['status' => 'error', 'code' => 'invalid_permission'];
        }
        if (!$this->actorIsSuperadministrator($actorID)) {
            return $this->denied();
        }

        try {
            $permissionID = $this->roles->createPermission($name, $slug);
            return ['status' => 'success', 'code' => 'permission_created', 'permission_id' => $permissionID];
        } catch (Throwable $exception) {
            return $this->failure($exception, 'permission_create_failed');
        }
    }

    public function grantPermission(int $actorID, int $roleID, int $permissionID): array
    {
        if (!$this->actorIsSuperadministrator($actorID)) {
            return $this->denied();
        }

        try {
            $role = $this->roles->findRoleByID($roleID);
            if ($role === null) {
                return ['status' => 'error', 'code' => 'role_not_found'];
            }
            if ($this->isSuperadministratorRole($role)) {
                return ['status' => 'success', 'code' => 'permission_not_required'];
            }

            $this->roles->assignPermission($roleID, $permissionID);
            return ['status' => 'success', 'code' => 'permission_granted'];
        } catch (Throwable $exception) {
            return $this->failure($exception, 'permission_grant_failed');
        }
    }

    public function assignRole(int $actorID, int $userID, int $roleID): array
    {
        if ($userID <= 0 || $roleID <= 0) {
            return ['status' => 'error', 'code' => 'invalid_role_assignment'];
        }
        if (!$this->actorIsSuperadministrator($actorID)) {
            return $this->denied();
        }

        try {
            $currentRole = $this->roles->findUserRole($userID);
            $targetRole = $this->roles->findRoleByID($roleID);
            if ($targetRole === null) {
                return ['status' => 'error', 'code' => 'role_not_found'];
            }

            if ($this->isSuperadministratorRole($targetRole)) {
                if ($currentRole !== null && $this->isSuperadministratorRole($currentRole)) {
                    return ['status' => 'success', 'code' => 'role_unchanged'];
                }
                return ['status' => 'error', 'code' => 'superadministrator_role_protected'];
            }

            if ($currentRole !== null && $this->isSuperadministratorRole($currentRole)) {
                return ['status' => 'error', 'code' => 'superadministrator_role_protected'];
            }

            $this->roles->assignRole($userID, $roleID);
            return ['status' => 'success', 'code' => 'role_assigned'];
        } catch (Throwable $exception) {
            return $this->failure($exception, 'role_assignment_failed');
        }
    }

    public function deleteRole(int $actorID, int $roleID): array
    {
        if (!$this->actorIsSuperadministrator($actorID)) {
            return $this->denied();
        }

        try {
            $role = $this->roles->findRoleByID($roleID);
            if ($role === null) {
                return ['status' => 'error', 'code' => 'role_not_found'];
            }
            if (!empty($role['is_system']) || $this->isSuperadministratorRole($role)) {
                return ['status' => 'error', 'code' => 'system_role_protected'];
            }
            if ($this->roles->countUsersWithRole($roleID) > 0) {
                return ['status' => 'error', 'code' => 'role_in_use'];
            }

            $this->roles->deleteRole($roleID);
            return ['status' => 'success', 'code' => 'role_deleted'];
        } catch (Throwable $exception) {
            return $this->failure($exception, 'role_delete_failed');
        }
    }

    private function actorIsSuperadministrator(int $actorID): bool
    {
        $role = $actorID > 0 ? $this->roles->findUserRole($actorID) : null;
        return $role !== null && $this->isSuperadministratorRole($role);
    }

    private function isSuperadministratorRole(array $role): bool
    {
        return $this->normalizeSlug((string)($role['slug'] ?? '')) === SystemRole::SUPERADMINISTRATOR;
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = mb_strtolower(trim($slug), 'UTF-8');
        return preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $slug) === 1 ? $slug : '';
    }

    private function allowed(array $role, bool $bypass = false): array
    {
        return [
            'status' => 'success',
            'data' => [
                'role_id' => (int)($role['role_id'] ?? 0),
                'role' => (string)($role['slug'] ?? ''),
                'is_system' => !empty($role['is_system']),
                'bypass' => $bypass,
            ],
        ];
    }

    private function denied(): array
    {
        return ['status' => 'unauthorized', 'code' => 'forbidden'];
    }

    private function failure(Throwable $exception, string $code): array
    {
        error_log('[GFrame Access] ' . $exception->getMessage());
        return ['status' => 'error', 'code' => $code];
    }
}
