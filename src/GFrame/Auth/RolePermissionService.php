<?php

namespace GFrame\Auth;

use Exception;
use GFrame\Session\ActiveSessionRegistry;
use GFrame\Session\SessionRuntime;

final class RolePermissionService
{
    private ?ActiveSessionRegistry $sessions;

    public function __construct(private readonly RoleModel $roles, ?ActiveSessionRegistry $sessions = null)
    {
        $this->sessions = $sessions ?? SessionRuntime::registry();
    }

    public function authorize(int $userID, string $permission, int $tenantID = 0): array
    {
        $permission = $this->normalizeSlug($permission);
        if ($userID <= 0 || $permission === '') {
            return $this->denied();
        }

        try {
            $cached = $this->sessionAuthorization($userID, max(0, $tenantID));
            if ($cached !== null) {
                if (!empty($cached['bypass'])) return $this->allowed($cached, true);
                return in_array($permission, (array)$cached['permissions'], true)
                    ? $this->allowed($cached)
                    : $this->denied();
            }
            $authorization = $this->roles->authorizationForUser($userID, max(0, $tenantID));
            if ($authorization === null) {
                return $this->denied();
            }

            if (!empty($authorization['bypass'])) {
                return $this->allowed($authorization, true);
            }

            if (!in_array($permission, (array)($authorization['permissions'] ?? []), true)) {
                return $this->denied();
            }

            return $this->allowed($authorization);
        } catch (Exception $exception) {
            return $this->failure($exception, 'authorization_failed');
        }
    }

    public function role(int $userID): array
    {
        if ($userID <= 0) {
            return $this->denied();
        }

        try {
            $cached = $this->sessionAuthorization($userID);
            if ($cached !== null) return $this->allowed($cached, !empty($cached['bypass']));
            $role = $this->roles->findUserRole($userID);
            if ($role === null) {
                return $this->denied();
            }

            return $this->allowed($role, $this->isSuperadministratorRole($role));
        } catch (Exception $exception) {
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
        } catch (Exception $exception) {
            return $this->failure($exception, 'role_create_failed');
        }
    }

    public function grantPermission(int $actorID, int $roleID, string $permission): array
    {
        $permission = $this->normalizeSlug($permission);
        if ($permission === '') return ['status' => 'error', 'code' => 'invalid_permission'];
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

            $this->roles->setRolePermission($roleID, $permission, true);
            $version = $this->roles->incrementSecurityVersion($roleID);
            $this->sessions?->publishRoleVersion($roleID, $version);
            return ['status' => 'success', 'code' => 'permission_granted'];
        } catch (Exception $exception) {
            return $this->failure($exception, 'permission_grant_failed');
        }
    }

    public function revokePermission(int $actorID, int $roleID, string $permission): array
    {
        $permission = $this->normalizeSlug($permission);
        if ($permission === '') return ['status' => 'error', 'code' => 'invalid_permission'];
        if (!$this->actorIsSuperadministrator($actorID)) return $this->denied();
        try {
            $role = $this->roles->findRoleByID($roleID);
            if ($role === null) return ['status' => 'error', 'code' => 'role_not_found'];
            if ($this->isSuperadministratorRole($role)) return ['status' => 'success', 'code' => 'permission_not_required'];
            $this->roles->setRolePermission($roleID, $permission, false);
            $version = $this->roles->incrementSecurityVersion($roleID);
            $this->sessions?->publishRoleVersion($roleID, $version);
            return ['status' => 'success', 'code' => 'permission_revoked'];
        } catch (Exception $exception) {
            return $this->failure($exception, 'permission_revoke_failed');
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
            $this->sessions?->revokeUser($userID);
            return ['status' => 'success', 'code' => 'role_assigned'];
        } catch (Exception $exception) {
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
        } catch (Exception $exception) {
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

    private function sessionAuthorization(int $userID, int $tenantID = 0): ?array
    {
        $identity = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        if ((int)($identity['id'] ?? 0) !== $userID) return null;
        if (SessionRuntime::authorizationStale()) {
            $authorization = $this->roles->authorizationForUser($userID);
            if ($authorization === null) return null;
            unset($authorization['tenant_id']);
            unset($identity['tenant_authorization']);
            $_SESSION['auth'] = array_replace($identity, $authorization);
            $this->sessions?->updateAuthorization($userID, session_id(), (int)$authorization['role_id'], (int)$authorization['role_version'], (int)$authorization['authorization_version']);
            SessionRuntime::clearAuthorizationStale();
            $identity = $_SESSION['auth'];
        }

        if ($tenantID > 0) {
            $cached = $identity['tenant_authorization'] ?? null;
            if (is_array($cached) && (int)($cached['tenant_id'] ?? 0) === $tenantID) return $cached;
            $authorization = $this->roles->authorizationForUser($userID, $tenantID);
            if ($authorization === null) {
                unset($_SESSION['auth']['tenant_authorization']);
                $this->sessions?->updateTenantAuthorization($userID, session_id(), 0, 1);
                return null;
            }
            $_SESSION['auth']['tenant_authorization'] = $authorization;
            $this->sessions?->updateTenantAuthorization($userID, session_id(), (int)$authorization['role_id'], (int)$authorization['role_version']);
            return $authorization;
        }

        if (array_key_exists('permissions', $identity)) return $identity;

        $authorization = $this->roles->authorizationForUser($userID);
        if ($authorization === null) return null;
        unset($authorization['tenant_id']);
        $_SESSION['auth'] = array_replace($identity, $authorization);
        $this->sessions?->updateAuthorization(
            $userID,
            session_id(),
            (int)$authorization['role_id'],
            (int)$authorization['role_version'],
            (int)$authorization['authorization_version']
        );
        SessionRuntime::clearAuthorizationStale();
        return $_SESSION['auth'];
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
                'role' => (string)($role['slug'] ?? $role['role'] ?? ''),
                'is_system' => !empty($role['is_system']),
                'bypass' => $bypass,
            ],
        ];
    }

    private function denied(): array
    {
        return ['status' => 'unauthorized', 'code' => 'forbidden'];
    }

    private function failure(Exception $exception, string $code): array
    {
        error_log('[GFrame Access] ' . $exception->getMessage());
        return ['status' => 'error', 'code' => $code];
    }
}
