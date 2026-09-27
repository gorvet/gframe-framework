<?php

namespace GFrame\Tests;

use GFrame\Auth\AuthService;
use GFrame\Auth\UserModel;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    public function testRegistrationVerificationAndAuthentication(): void
    {
        $model = new InMemoryUserModel();
        $auth = new AuthService($model);

        $registered = $auth->register('Persona@Example.com', 'Password-123');
        self::assertSame('success', $registered['status']);
        self::assertSame('persona@example.com', $model->users[1]['email']);
        self::assertSame('unverify', $model->users[1]['status']);

        $pendingLogin = $auth->authenticate('persona@example.com', 'Password-123');
        self::assertSame('unverified_account', $pendingLogin['code']);

        $verified = $auth->verify($registered['token']);
        self::assertSame('account_verified', $verified['code']);

        $login = $auth->authenticate('persona@example.com', 'Password-123');
        self::assertSame('success', $login['status']);
        self::assertArrayNotHasKey('name', $login['user']);
        self::assertArrayNotHasKey('password', $login['user']);
    }

    public function testRecoveryDoesNotRevealUnknownAccountsAndCanResetPassword(): void
    {
        $model = new InMemoryUserModel();
        $auth = new AuthService($model);
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
        $auth = new AuthService(new InMemoryUserModel());

        self::assertSame('invalid_password', $auth->register('persona@example.com', 'short')['code']);
        self::assertSame('invalid_email', $auth->register('invalid-email', 'Password-123')['code']);
    }

    public function testAuthenticationRequiresTheConfiguredActiveStatus(): void
    {
        $model = new InMemoryUserModel();
        $auth = new AuthService($model);

        $registered = $auth->register('persona@example.com', 'Password-123');
        $model->updateAuthUser((int)$registered['user_id'], ['status' => 'archived']);

        $response = $auth->authenticate('persona@example.com', 'Password-123');

        self::assertSame('error', $response['status']);
        self::assertSame('invalid_user', $response['code']);
    }

    public function testPasswordExpirationCanBeEnabledWithoutChangingTheDefault(): void
    {
        $model = new InMemoryUserModel();
        $auth = new AuthService($model, passwordExpirationEnabled: true, passwordExpirationDays: 90);
        $registered = $auth->register('persona@example.com', 'Password-123');
        $auth->verify($registered['token']);
        $model->updateAuthUser((int)$registered['user_id'], [
            'password_changed_at' => date('Y-m-d H:i:s', strtotime('-91 days')),
        ]);

        $login = $auth->authenticate('persona@example.com', 'Password-123');

        self::assertSame('password_change_required', $login['code']);
        self::assertTrue($login['must_change_password']);
    }
}

final class InMemoryUserModel extends UserModel
{
    public array $users = [];

    public function emailExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function createPendingUser(string $email, string $passwordHash, string $token, string $issuedAt): int
    {
        $userID = count($this->users) + 1;
        $this->users[$userID] = [
            'user_id' => $userID,
            'email' => $email,
            'password' => $passwordHash,
            'role_id' => 2,
            'role' => 'registered',
            'status' => 'unverify',
            'token' => $token,
            'token_updated_at' => $issuedAt,
            'password_changed_at' => $issuedAt,
            'force_password_change' => false,
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

    public function usersExist(): bool
    {
        return $this->users !== [];
    }

    public function findRoleIDBySlug(string $slug): ?int
    {
        return $slug === 'superadministrator' ? 1 : ($slug === 'registered' ? 2 : null);
    }

    public function createActiveUser(string $email, string $passwordHash, int $roleID): int
    {
        $userID = count($this->users) + 1;
        $this->users[$userID] = [
            'user_id' => $userID,
            'email' => $email,
            'password' => $passwordHash,
            'role_id' => $roleID,
            'status' => 'verify',
            'token' => '',
            'token_updated_at' => date('Y-m-d H:i:s'),
            'password_changed_at' => date('Y-m-d H:i:s'),
            'force_password_change' => false,
            'last_login' => null,
        ];
        return $userID;
    }
}
