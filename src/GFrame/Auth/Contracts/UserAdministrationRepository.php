<?php

namespace GFrame\Auth\Contracts;

interface UserAdministrationRepository
{
    public function paginateUsers(int $page, int $perPage, string $search = '', string $role = '', string $status = ''): array;
    public function findUserByID(int $userID): ?array;
    /** Desactivar debe invalidar los tokens de correo; una escritura fallida no debe completar sin error. */
    public function setActive(int $userID, bool $active): void;
    public function assignRole(int $userID, int $roleID): void;
}
