<?php

namespace GFrame\Auth\Contracts;

interface AuthUserRepository
{
    public function emailExists(string $email): bool;

    public function createPendingUser(
        string $email,
        string $passwordHash,
        string $displayName,
        string $token,
        string $issuedAt
    ): int;

    public function findByEmail(string $email): ?array;

    public function findByToken(string $token): ?array;

    public function updateAuthUser(int $userID, array $attributes): void;
}
