<?php

namespace GFrame\Auth;

use Exception;
use GFrame\Auth\Contracts\RoleAdministrationRepository;
use GFrame\Auth\Contracts\UserAdministrationRepository;
use GFrame\Session\ActiveSessionRegistry;
use GFrame\Session\SessionRuntime;

final class UserAdministrationService
{
    private const ALLOWED_STATUSES = ['verify', 'unverify', 'disabled', 'suspended'];

    private ?ActiveSessionRegistry $sessions;

    public function __construct(
        private readonly UserAdministrationRepository $users,
        private readonly RoleAdministrationRepository $roles,
        ?ActiveSessionRegistry $sessions = null
    ) {
        $this->sessions = $sessions ?? SessionRuntime::registry();
    }

    public function paginate(int $actorID, int $page = 1, int $perPage = 20, string $search = '', string $role = '', string $status = ''): array
    {
        try {
            if (!$this->canViewUsers($actorID)) {
                return $this->denied();
            }
            $role = $this->normalizeSlug($role);
            $status = $this->normalizeStatus($status);
            $result = $this->users->paginateUsers(
                max(1, $page),
                max(1, min(100, $perPage)),
                mb_substr(trim($search), 0, 120, 'UTF-8'),
                $role,
                $status
            );
            return ['status' => 'success', 'code' => 'users_loaded'] + $result;
        } catch (Exception $exception) {
            return $this->failure($exception, 'users_list_failed');
        }
    }

    public function assignableRoles(int $actorID): array
    {
        try {
            if (!$this->canViewUsers($actorID)) {
                return $this->denied();
            }
            return ['status' => 'success', 'code' => 'roles_loaded', 'data' => $this->roles->assignableRoles()];
        } catch (Exception $exception) {
            return $this->failure($exception, 'roles_list_failed');
        }
    }

    public function capabilities(int $actorID): array
    {
        try {
            if (!$this->canViewUsers($actorID)) {
                return $this->denied();
            }
            return [
                'status' => 'success',
                'code' => 'capabilities_loaded',
                'data' => ['view' => true, 'manage' => $this->canManageUsers($actorID)],
            ];
        } catch (Exception $exception) {
            return $this->failure($exception, 'capabilities_lookup_failed');
        }
    }

    public function setActive(int $actorID, int $userID, bool $active): array
    {
        try {
            if (!$this->canManageUsers($actorID)) {
                return $this->denied();
            }
            $target = $userID > 0 ? $this->users->findUserByID($userID) : null;
            if ($target === null) {
                return $this->error('user_not_found');
            }
            if ($userID === $actorID) {
                return $this->error('self_protection');
            }
            if ($this->isSuperadministratorRole($target)) {
                return $this->error('protected_user');
            }
            if (!$this->actorIsSuperadministrator($actorID) && $this->isAdministratorRole($target)) {
                return $this->error('protected_user');
            }
            $this->users->setActive($userID, $active);
            if ($this->sessions !== null) {
                if ($active) {
                    $this->sessions->allowUser($userID);
                } else {
                    $this->sessions->revokeUser($userID, true);
                }
            }
            return ['status' => 'success', 'code' => $active ? 'user_activated' : 'user_deactivated'];
        } catch (Exception $exception) {
            return $this->failure($exception, 'user_status_update_failed');
        }
    }

    public function assignRole(int $actorID, int $userID, int $roleID): array
    {
        try {
            if (!$this->canManageUsers($actorID)) {
                return $this->denied();
            }
            if ($userID <= 0 || $roleID <= 0) {
                return $this->error('invalid_role_assignment');
            }
            if ($userID === $actorID) {
                return $this->error('self_protection');
            }
            $target = $this->users->findUserByID($userID);
            if ($target === null) {
                return $this->error('user_not_found');
            }
            if ($this->isSuperadministratorRole($target)) {
                return $this->error('protected_user');
            }
            $targetRole = $this->roles->findRoleByID($roleID);
            if ($targetRole === null || $this->isSuperadministratorRole($targetRole)) {
                return $this->error('invalid_role_assignment');
            }
            if (
                !$this->actorIsSuperadministrator($actorID)
                && ($this->isAdministratorRole($target) || $this->isAdministratorRole($targetRole))
            ) {
                return $this->error('invalid_role_assignment');
            }
            $this->users->assignRole($userID, $roleID);
            if ($this->sessions !== null) {
                $this->sessions->revokeUser($userID);
            }
            return ['status' => 'success', 'code' => 'role_assigned'];
        } catch (Exception $exception) {
            return $this->failure($exception, 'role_assignment_failed');
        }
    }

    private function canViewUsers(int $actorID): bool
    {
        return $this->hasPermission($actorID, 'users.view') || $this->hasPermission($actorID, 'users.manage');
    }

    private function canManageUsers(int $actorID): bool
    {
        return $this->hasPermission($actorID, 'users.manage');
    }

    private function hasPermission(int $actorID, string $permission): bool
    {
        $role = $actorID > 0 ? $this->roles->findUserRole($actorID) : null;
        if ($role === null) {
            return false;
        }
        return $this->isSuperadministratorRole($role)
            || $this->roles->roleHasPermission((int)($role['role_id'] ?? 0), $permission);
    }

    private function isSuperadministratorRole(array $role): bool
    {
        return $this->normalizeSlug((string)($role['slug'] ?? $role['role'] ?? '')) === SystemRole::SUPERADMINISTRATOR;
    }

    private function actorIsSuperadministrator(int $actorID): bool
    {
        $role = $actorID > 0 ? $this->roles->findUserRole($actorID) : null;
        return $role !== null && $this->isSuperadministratorRole($role);
    }

    private function isAdministratorRole(array $role): bool
    {
        return $this->roles->roleHasPermission((int)($role['role_id'] ?? 0), 'admin.access');
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = mb_strtolower(trim($slug), 'UTF-8');
        return preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $slug) === 1 ? $slug : '';
    }

    private function normalizeStatus(string $status): string
    {
        $status = mb_strtolower(trim($status), 'UTF-8');
        return in_array($status, self::ALLOWED_STATUSES, true) ? $status : '';
    }

    private function denied(): array
    {
        return ['status' => 'unauthorized', 'code' => 'forbidden'];
    }

    private function error(string $code): array
    {
        return ['status' => 'error', 'code' => $code];
    }

    private function failure(Exception $exception, string $code): array
    {
        error_log('[GFrame User Admin] ' . $exception->getMessage());
        return $this->error($code);
    }
}
