<?php

namespace GFrame\Tests;

use GFrame\Auth\AuthService;
use GFrame\Auth\Contracts\AuthUserRepository;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    public function testRegistrationVerificationAndAuthentication(): void
    {
        $repository = new InMemoryAuthUserRepository();
        $auth = new AuthService($repository);

        $registered = $auth->register('Persona@Example.com', 'Password-123');
        self::assertSame('success', $registered['status']);
        self::assertSame('persona@example.com', $repository->users[1]['email']);
        self::assertSame('unverify', $repository->users[1]['status']);

        $pendingLogin = $auth->authenticate('persona@example.com', 'Password-123');
        self::assertSame('unverified_account', $pendingLogin['code']);

        $verified = $auth->verify($registered['token']);
        self::assertSame('account_verified', $verified['code']);

        $login = $auth->authenticate('persona@example.com', 'Password-123');
        self::assertSame('success', $login['status']);
        self::assertSame('Persona', $login['user']['name']);
        self::assertArrayNotHasKey('password', $login['user']);
    }

    public function testRecoveryDoesNotRevealUnknownAccountsAndCanResetPassword(): void
    {
        $repository = new InMemoryAuthUserRepository();
        $auth = new AuthService($repository);
        $registered = $auth->register('persona@example.com', 'Password-123');
        $auth->verify($registered['token']);

        $unknown = $auth->requestRecovery('unknown@example.com');
        self::assertSame('success', $unknown['status']);
        self::assertArrayNotHasKey('token', $unknown);

        $recovery = $auth->requestRecovery('persona@example.com');
        self::assertNotEmpty($recovery['token']);
        self::assertSame('password_reset', $auth->resetPassword($recovery['token'], 'New-password-456')['code']);
        self::assertSame('success', $auth->authenticate('persona@example.com', 'New-password-456')['status']);
    }

    public function testPasswordPolicyIsAppliedDuringRegistrationAndReset(): void
    {
        $auth = new AuthService(new InMemoryAuthUserRepository());

        self::assertSame('invalid_password', $auth->register('persona@example.com', 'short')['code']);
        self::assertSame('invalid_email', $auth->register('invalid-email', 'Password-123')['code']);
    }
}

final class InMemoryAuthUserRepository implements AuthUserRepository
{
    public array $users = [];

    public function emailExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function createPendingUser(string $email, string $passwordHash, string $displayName, string $token, string $issuedAt): int
    {
        $userID = count($this->users) + 1;
        $this->users[$userID] = [
            'user_id' => $userID,
            'email' => $email,
            'password' => $passwordHash,
            'name' => $displayName,
            'role' => 'registered',
            'status' => 'unverify',
            'token' => $token,
            'token_updated_at' => $issuedAt,
            'last_login' => null,
        ];
        return $userID;
    }

    public function findByEmail(string $email): ?array
    {
        foreach ($this->users as $user) {
            if ($user['email'] === $email) {
                return $user;
            }
        }
        return null;
    }

    public function findByToken(string $token): ?array
    {
        foreach ($this->users as $user) {
            if ($user['token'] === $token) {
                return $user;
            }
        }
        return null;
    }

    public function updateAuthUser(int $userID, array $attributes): void
    {
        $this->users[$userID] = array_replace($this->users[$userID], $attributes);
    }
}
