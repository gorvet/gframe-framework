<?php

namespace GFrame\Tests;

use GFrame\Auth\Contracts\AccountDeactivationPolicy;
use GFrame\Auth\Contracts\SelfAccountRepository;
use GFrame\Auth\SelfAccountService;
use PHPUnit\Framework\TestCase;

final class SelfAccountServiceTest extends TestCase
{
    public function testProfileAndNameUpdateDoNotExposeCredentials(): void
    {
        $repository = new InMemorySelfAccountRepository();
        $service = new SelfAccountService($repository);

        $profile = $service->profile(2);
        self::assertSame('success', $profile['status']);
        self::assertArrayNotHasKey('password', $profile['data']);

        self::assertSame('profile_updated', $service->updateProfile(2, 'Nombre actualizado')['code']);
        self::assertSame('Nombre actualizado', $repository->accounts[2]['name']);
    }

    public function testPasswordChangeValidatesCurrentPasswordAndConfirmation(): void
    {
        $repository = new InMemorySelfAccountRepository();
        $service = new SelfAccountService($repository);

        self::assertSame('invalid_current_password', $service->changePassword(2, 'incorrecta', 'Nueva-clave-123', 'Nueva-clave-123')['code']);
        self::assertSame('password_mismatch', $service->changePassword(2, 'Clave-actual-123', 'Nueva-clave-123', 'Otra-clave-123')['code']);
        self::assertSame('password_updated', $service->changePassword(2, 'Clave-actual-123', 'Nueva-clave-123', 'Nueva-clave-123')['code']);
        self::assertTrue(password_verify('Nueva-clave-123', $repository->accounts[2]['password']));
    }

    public function testProtectedAccountCannotBeDeactivated(): void
    {
        $repository = new InMemorySelfAccountRepository();
        $service = new SelfAccountService($repository, $repository);

        self::assertSame('protected_account', $service->deactivate(1, 'Clave-actual-123')['code']);
        self::assertSame('verify', $repository->accounts[1]['status']);

        self::assertSame('account_deactivated', $service->deactivate(2, 'Clave-actual-123')['code']);
        self::assertSame('suspended', $repository->accounts[2]['status']);
    }
}

final class InMemorySelfAccountRepository implements SelfAccountRepository, AccountDeactivationPolicy
{
    public array $accounts;

    public function __construct()
    {
        $password = password_hash('Clave-actual-123', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->accounts = [
            1 => ['user_id' => 1, 'name' => 'Principal', 'password' => $password, 'status' => 'verify', 'protected' => true],
            2 => ['user_id' => 2, 'name' => 'Persona', 'password' => $password, 'status' => 'verify', 'protected' => false],
        ];
    }

    public function findAccountById(int $userID): ?array
    {
        return $this->accounts[$userID] ?? null;
    }

    public function updateAccountName(int $userID, string $name): void
    {
        $this->accounts[$userID]['name'] = $name;
    }

    public function updateAccountPassword(int $userID, string $passwordHash): void
    {
        $this->accounts[$userID]['password'] = $passwordHash;
    }

    public function deactivateAccount(int $userID): void
    {
        $this->accounts[$userID]['status'] = 'suspended';
    }

    public function canDeactivateAccount(array $account): bool
    {
        return empty($account['protected']);
    }
}
