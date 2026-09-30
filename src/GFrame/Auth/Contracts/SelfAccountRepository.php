<?php

namespace GFrame\Auth\Contracts;

interface SelfAccountRepository
{
    public function findAccountByID(int $userID): ?array;

    public function updateAccountPassword(int $userID, string $passwordHash): void;

    public function deactivateAccount(int $userID): void;
}
