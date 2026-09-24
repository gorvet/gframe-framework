<?php

namespace GFrame\Auth;

final class PasswordPolicy
{
    public function __construct(
        private readonly int $minimumLength = 8,
        private readonly int $maximumLength = 72
    ) {
    }

    public function accepts(string $password): bool
    {
        $length = strlen($password);
        return $length >= $this->minimumLength && $length <= $this->maximumLength;
    }

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
