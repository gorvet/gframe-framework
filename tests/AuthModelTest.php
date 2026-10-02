<?php

namespace GFrame\Tests;

use GFrame\Auth\AuthModel;
use GFrame\Auth\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class AuthModelTest extends TestCase
{
    public function testAuthenticationUsesTheOriginalMvcModelInsteadOfAService(): void
    {
        self::assertTrue(is_subclass_of(AuthModel::class, \ORM::class));
        self::assertFalse(is_file(dirname(__DIR__) . '/src/GFrame/Auth/AuthService.php'));
        foreach (['registerAcount', 'login', 'validateAcount', 'recoveryAcount', 'resetPassword', 'verifyAcount'] as $method) {
            self::assertTrue(method_exists(AuthModel::class, $method), $method);
            self::assertFalse(method_exists(\GFrame\Auth\UserModel::class, $method), $method);
        }
        self::assertFalse(method_exists(\GFrame\Auth\UserModel::class, 'createPendingUser'));
        self::assertFalse(method_exists(AuthModel::class, 'bootstrapNewOwner'));
        $controller = (string)file_get_contents(dirname(__DIR__) . '/resources/modules/auth-ui/application/app/controllers/auth-ui/AuthController.php');
        self::assertStringContainsString('protected AuthModel $authModel;', $controller);
        self::assertStringNotContainsString('AuthService', $controller);
        self::assertStringNotContainsString('UserModel', $controller);
    }

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
        $model = new InMemoryAuthModel();
        $auth = $model;

        $registered = $auth->registerAcount('Persona@Example.com', 'Password-123');
        self::assertSame('success', $registered['status']);
        self::assertSame(['status', 'code', 'data'], array_keys($registered));
        self::assertSame(1, $registered['data']['user_id']);
        self::assertSame('persona@example.com', $model->users[1]['email']);
        self::assertSame('unverify', $model->users[1]['status']);

        $pendingLogin = $auth->login('persona@example.com', 'Password-123');
        self::assertSame('unverified_account', $pendingLogin['code']);

        $verified = $auth->validateAcount($registered['data']['token']);
        self::assertSame('account_verified', $verified['code']);

        $login = $auth->login('persona@example.com', 'Password-123');
        self::assertSame('success', $login['status']);
        self::assertSame(['status', 'code', 'data'], array_keys($login));
        self::assertArrayNotHasKey('name', $login['data']['user']);
        self::assertArrayNotHasKey('password', $login['data']['user']);
    }

    public function testRecoveryDoesNotRevealUnknownAccountsAndCanResetPassword(): void
    {
        $model = new InMemoryAuthModel();
        $auth = $model;
        $registered = $auth->registerAcount('persona@example.com', 'Password-123');
        $auth->validateAcount($registered['data']['token']);

        $unknown = $auth->recoveryAcount('unknown@example.com');
        self::assertSame('success', $unknown['status']);
        self::assertArrayNotHasKey('token', (array)($unknown['data'] ?? []));

        $recovery = $auth->recoveryAcount('persona@example.com');
        self::assertNotEmpty($recovery['data']['token']);
        self::assertSame('password_reset', $auth->resetPassword($recovery['data']['token'], 'New-password-456')['code']);
        self::assertSame('success', $auth->login('persona@example.com', 'New-password-456')['status']);
    }

    public function testPasswordPolicyIsAppliedDuringRegistrationAndReset(): void
    {
        $auth = new InMemoryAuthModel();

        self::assertSame('invalid_password', $auth->registerAcount('persona@example.com', 'short')['code']);
        self::assertSame('invalid_email', $auth->registerAcount('invalid-email', 'Password-123')['code']);
    }

    public function testSuspensionAfterRecoveryBlocksPasswordReset(): void
    {
        foreach (['suspended', 'disabled'] as $status) {
            $users = new InMemoryAuthModel();
            $auth = $users;
            $registered = $auth->registerAcount('user@example.test', 'Password-123');
            $auth->validateAcount($registered['data']['token']);
            $recovery = $auth->recoveryAcount('user@example.test');
            $hash = $users->users[1]['password'];
            $users->users[1]['status'] = $status;
            self::assertSame('suspended_account', $auth->resetPassword($recovery['data']['token'], 'New-password-123')['code']);
            self::assertSame('suspended_account', $auth->validateAcount($recovery['data']['token'])['code']);
            self::assertArrayNotHasKey('token', (array)($auth->recoveryAcount('user@example.test')['data'] ?? []));
            self::assertSame($hash, $users->users[1]['password']);
        }
    }

    public function testFailedWritesDoNotReportSuccess(): void
    {
        $users = new class extends AuthModel {
            public function findByToken(string $token): ?array {
                return ['user_id' => 1, 'status' => 'verify', 'token_updated_at' => date('Y-m-d H:i:s')];
            }
            public function findByEmail(string $email): ?array { return $this->findByToken(''); }
            public function updateAuthUser(int $userID, array $attributes): void {
                throw new \RuntimeException('Usuario eliminado durante la operación');
            }
        };
        $auth = $users;
        self::assertSame('verification_failed', $auth->validateAcount('token')['code']);
        self::assertSame('password_reset_failed', $auth->resetPassword('token', 'New-password-123')['code']);
        self::assertSame('recovery_failed', $auth->recoveryAcount('user@example.test')['code']);
    }

    public function testAuthenticationRequiresTheConfiguredActiveStatus(): void
    {
        $model = new InMemoryAuthModel();
        $auth = $model;

        $registered = $auth->registerAcount('persona@example.com', 'Password-123');
        $model->updateAuthUser((int)$registered['data']['user_id'], ['status' => 'archived']);

        $response = $auth->login('persona@example.com', 'Password-123');

        self::assertSame('error', $response['status']);
        self::assertSame('invalid_user', $response['code']);
    }

    public function testPasswordExpirationCanBeEnabledWithoutChangingTheDefault(): void
    {
        $model = new InMemoryAuthModel(passwordExpirationEnabled: true, passwordExpirationDays: 90);
        $auth = $model;
        $registered = $auth->registerAcount('persona@example.com', 'Password-123');
        $auth->validateAcount($registered['data']['token']);
        $model->updateAuthUser((int)$registered['data']['user_id'], [
            'password_changed_at' => date('Y-m-d H:i:s', strtotime('-91 days')),
        ]);

        $login = $auth->login('persona@example.com', 'Password-123');

        self::assertSame('password_change_required', $login['code']);
        self::assertTrue($login['data']['must_change_password']);
    }

    public function testRepositoryExceptionsBecomeStableErrorContracts(): void
    {
        $auth = new FailingAuthModel();

        self::assertSame(
            ['status' => 'error', 'code' => 'authentication_failed'],
            $auth->login('persona@example.com', 'Password-123')
        );
        self::assertSame(
            ['status' => 'error', 'code' => 'register_failed'],
            $auth->registerAcount('persona@example.com', 'Password-123')
        );
        self::assertSame(
            ['status' => 'error', 'code' => 'recovery_failed'],
            $auth->recoveryAcount('persona@example.com')
        );
    }
}

final class FailingAuthModel extends AuthModel
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

final class InMemoryAuthModel extends AuthModel
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
