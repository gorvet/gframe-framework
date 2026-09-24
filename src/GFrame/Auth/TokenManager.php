<?php

namespace GFrame\Auth;

final class TokenManager
{
    public function __construct(private readonly int $lifetimeSeconds = 86400)
    {
    }

    public function issue(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function issuedAt(): string
    {
        return date('Y-m-d H:i:s');
    }

    public function isValidTimestamp(?string $issuedAt): bool
    {
        $timestamp = strtotime(trim((string)$issuedAt));
        if ($timestamp === false) {
            return false;
        }

        $age = time() - $timestamp;
        return $age >= 0 && $age <= $this->lifetimeSeconds;
    }
}
