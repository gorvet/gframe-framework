<?php

namespace GFrame\Tests;

use GFrame\Auth\SelfAccountService;
use GFrame\Auth\UserModel;
use PHPUnit\Framework\TestCase;

final class SelfAccountServiceTest extends TestCase
{
    public function testProfileDoesNotExposeCredentials(): void
    {
        $model = new InMemorySelfAccountModel();
        $service = new SelfAccountService($model);

        $profile = $service->profile(2);
        self::assertSame('success', $profile['status']);
        self::assertArrayNotHasKey('password', $profile['data']);
        self::assertArrayNotHasKey('token', $profile['data']);
    }

    public function testPasswordChangeValidatesCurrentPasswordAndConfirmation(): void
    {
        $model = new InMemorySelfAccountModel();
        $service = new SelfAccountService($model);

        self::assertSame('invalid_current_password', $service->changePassword(2, 'incorrecta', 'Nueva-clave-123', 'Nueva-clave-123')['code']);
        self::assertSame('password_mismatch', $service->changePassword(2, 'Clave-actual-123', 'Nueva-clave-123', 'Otra-clave-123')['code']);
        self::assertSame('password_updated', $service->changePassword(2, 'Clave-actual-123', 'Nueva-clave-123', 'Nueva-clave-123')['code']);
        self::assertTrue(password_verify('Nueva-clave-123', $model->accounts[2]['password']));
    }

    public function testProtectedAccountCannotBeDeactivated(): void
    {
        $model = new InMemorySelfAccountModel();
        $service = new SelfAccountService($model);

        self::assertSame('protected_account', $service->deactivate(1, 'Clave-actual-123')['code']);
        self::assertSame('verify', $model->accounts[1]['status']);

        self::assertSame('account_deactivated', $service->deactivate(2, 'Clave-actual-123')['code']);
        self::assertSame('disabled', $model->accounts[2]['status']);
    }
}

final class InMemorySelfAccountModel extends UserModel
{
    public array $accounts;

    public function __construct()
    {
        $password = password_hash('Clave-actual-123', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->accounts = [
            1 => ['user_id' => 1, 'email' => 'owner@example.com', 'password' => $password, 'token' => 'one', 'status' => 'verify', 'role' => 'superadministrator'],
            2 => ['user_id' => 2, 'email' => 'person@example.com', 'password' => $password, 'token' => 'two', 'status' => 'verify', 'role' => 'registered'],
        ];
    }

    public function findAccountByID(int $userID): ?array
    {
        return $this->accounts[$userID] ?? null;
    }

    public function updateAccountPassword(int $userID, string $passwordHash): void
    {
        $this->accounts[$userID]['password'] = $passwordHash;
    }

    public function deactivateAccount(int $userID): void
    {
        $this->accounts[$userID]['status'] = 'disabled';
    }
}
