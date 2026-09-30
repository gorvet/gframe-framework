<?php

namespace GFrame\Auth\Contracts;

interface RoleAdministrationRepository
{
    public function findUserRole(int $userID): ?array;
    public function findRoleByID(int $roleID): ?array;
    public function assignableRoles(): array;
    public function roleHasPermission(int $roleID, string $permission): bool;
}
