<?php

namespace GFrame\Auth;

final class UserAdministrationService
{
    public function __construct(
        private readonly UserModel $users,
        private readonly RoleModel $roles
    ) {
    }

    public function paginate(int $actorID, int $page = 1, int $perPage = 20, string $search = ''): array
    {
        if (!$this->canManageUsers($actorID)) {
            return ['status' => 'unauthorized', 'code' => 'forbidden'];
        }

        $result = $this->users->paginateUsers(max(1, $page), max(1, min(100, $perPage)), trim($search));
        return ['status' => 'success'] + $result;
    }

    public function setActive(int $actorID, int $userID, bool $active): array
    {
        if (!$this->canManageUsers($actorID)) {
            return ['status' => 'unauthorized', 'code' => 'forbidden'];
        }
        if ($userID <= 0 || $this->isSuperadministrator($userID)) {
            return ['status' => 'error', 'code' => 'protected_user'];
        }

        $this->users->setActive($userID, $active);
        return ['status' => 'success', 'code' => $active ? 'user_activated' : 'user_deactivated'];
    }

    public function assignRole(int $actorID, int $userID, int $roleID): array
    {
        if (!$this->isSuperadministrator($actorID)) {
            return ['status' => 'unauthorized', 'code' => 'forbidden'];
        }
        if ($userID <= 0 || $roleID <= 0 || $this->isSuperadministrator($userID)) {
            return ['status' => 'error', 'code' => 'protected_user'];
        }

        $targetRole = $this->roles->findRoleByID($roleID);
        if ($targetRole === null || $this->isSuperadministratorRole($targetRole)) {
            return ['status' => 'error', 'code' => 'invalid_role_assignment'];
        }

        $this->users->assignRole($userID, $roleID);
        return ['status' => 'success', 'code' => 'role_assigned'];
    }

    private function canManageUsers(int $actorID): bool
    {
        return $this->isSuperadministrator($actorID);
    }

    private function isSuperadministrator(int $userID): bool
    {
        $role = $this->roles->findUserRole($userID);
        return $role !== null && $this->isSuperadministratorRole($role);
    }

    private function isSuperadministratorRole(array $role): bool
    {
        return mb_strtolower(trim((string)($role['slug'] ?? '')), 'UTF-8') === SystemRole::SUPERADMINISTRATOR;
    }
}
