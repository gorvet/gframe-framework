<?php

namespace GFrame\Tests;

use GFrame\Auth\AuthInstallationService;
use GFrame\Auth\UserModel;
use PHPUnit\Framework\TestCase;

final class AuthInstallationServiceTest extends TestCase
{
    public function testFirstUserReceivesTheProtectedSuperadministratorRole(): void
    {
        $model = new InMemoryInstallationUserModel();
        $service = new AuthInstallationService($model);

        $result = $service->createFirstUser('Owner@Example.com', 'Password-123');

        self::assertSame('success', $result['status']);
        self::assertSame('superadministrator_created', $result['code']);
        self::assertSame(1, $model->users[1]['role_id']);
        self::assertSame('owner@example.com', $model->users[1]['email']);
        self::assertTrue(password_verify('Password-123', $model->users[1]['password']));
    }

    public function testInstallationCannotCreateASecondFirstUser(): void
    {
        $model = new InMemoryInstallationUserModel();
        $service = new AuthInstallationService($model);

        self::assertSame('success', $service->createFirstUser('owner@example.com', 'Password-123')['status']);
        self::assertSame(
            'application_already_installed',
            $service->createFirstUser('other@example.com', 'Password-123')['code']
        );
    }
}

final class InMemoryInstallationUserModel extends UserModel
{
    public array $users = [];

    public function usersExist(): bool
    {
        return $this->users !== [];
    }

    public function findRoleIDBySlug(string $slug): ?int
    {
        return $slug === 'superadministrator' ? 1 : null;
    }

    public function createActiveUser(string $email, string $passwordHash, int $roleID): int
    {
        $userID = count($this->users) + 1;
        $this->users[$userID] = [
            'email' => $email,
            'password' => $passwordHash,
            'role_id' => $roleID,
        ];
        return $userID;
    }
}
