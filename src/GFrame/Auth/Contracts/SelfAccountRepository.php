<?php

namespace GFrame\Auth\Contracts;

interface SelfAccountRepository
{
    public function findAccountById(int $userID): ?array;

    public function updateAccountName(int $userID, string $name): void;

    public function updateAccountPassword(int $userID, string $passwordHash): void;

    public function deactivateAccount(int $userID): void;
}
