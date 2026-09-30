<?php

namespace GFrame\Tests;

use GFrame\Auth\AuthService;
use GFrame\Auth\PasswordPolicy;
use GFrame\Auth\UserModel;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    public function testPasswordPolicyUsesUtf8ByteLength(): void
    {
        $policy = new PasswordPolicy();

        self::assertFalse($policy->accepts('1234567'));
        self::assertTrue($policy->accepts('12345678'));
        self::assertTrue($policy->accepts(str_repeat('á', 36)));
        self::assertFalse($policy->accepts(str_repeat('á', 37)));
    }

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

    public function testSuspensionAfterRecoveryBlocksPasswordReset(): void
    {
        foreach (['suspended', 'disabled'] as $status) {
            $users = new InMemoryUserModel();
            $auth = new AuthService($users);
            $registered = $auth->register('user@example.test', 'Password-123');
            $auth->verify($registered['token']);
            $recovery = $auth->requestRecovery('user@example.test');
            $hash = $users->users[1]['password'];
            $users->users[1]['status'] = $status;
            self::assertSame('suspended_account', $auth->resetPassword($recovery['token'], 'New-password-123')['code']);
            self::assertSame('suspended_account', $auth->verify($recovery['token'])['code']);
            self::assertArrayNotHasKey('token', $auth->requestRecovery('user@example.test'));
            self::assertSame($hash, $users->users[1]['password']);
        }
    }

    public function testFailedWritesDoNotReportSuccess(): void
    {
        $users = new class extends UserModel {
            public function findByToken(string $token): ?array {
                return ['user_id' => 1, 'status' => 'verify', 'token_updated_at' => date('Y-m-d H:i:s')];
            }
            public function findByEmail(string $email): ?array { return $this->findByToken(''); }
            public function updateAuthUser(int $userID, array $attributes): void {
                throw new \RuntimeException('Usuario eliminado durante la operación');
            }
        };
        $auth = new AuthService($users);
        self::assertSame('verification_failed', $auth->verify('token')['code']);
        self::assertSame('password_reset_failed', $auth->resetPassword('token', 'New-password-123')['code']);
        self::assertSame('recovery_failed', $auth->requestRecovery('user@example.test')['code']);
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

    public function testRepositoryExceptionsBecomeStableErrorContracts(): void
    {
        $auth = new AuthService(new FailingAuthUserModel());

        self::assertSame(
            ['status' => 'error', 'code' => 'authentication_failed'],
            $auth->authenticate('persona@example.com', 'Password-123')
        );
        self::assertSame(
            ['status' => 'error', 'code' => 'register_failed'],
            $auth->register('persona@example.com', 'Password-123')
        );
        self::assertSame(
            ['status' => 'error', 'code' => 'recovery_failed'],
            $auth->requestRecovery('persona@example.com')
        );
    }
}

final class FailingAuthUserModel extends UserModel
{
    public function findByEmail(string $email): ?array
    {
        throw new \RuntimeException('Fallo de prueba');
    }

    public function emailExists(string $email): bool
    {
        throw new \RuntimeException('Fallo de prueba');
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
