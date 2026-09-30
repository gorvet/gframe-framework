<?php

namespace GFrame\Tests;

use GFrame\Auth\Contracts\RoleAdministrationRepository;
use GFrame\Auth\Contracts\UserAdministrationRepository;
use GFrame\Auth\UserAdministrationService;
use GFrame\Session\ActiveSessionRegistry;
use PHPUnit\Framework\TestCase;

final class UserAdministrationServiceTest extends TestCase
{
    public function testPermissionsControlViewingAndManagement(): void
    {
        $users = new InMemoryUserAdministrationRepository();
        $roles = new InMemoryRoleAdministrationRepository();
        $service = new UserAdministrationService($users, $roles);

        self::assertSame('unauthorized', $service->paginate(4)['status']);
        self::assertSame('success', $service->paginate(2)['status']);
        self::assertSame('success', $service->paginate(3)['status']);
        self::assertFalse($service->capabilities(2)['data']['manage']);
        self::assertTrue($service->capabilities(3)['data']['manage']);
        self::assertSame('unauthorized', $service->setActive(2, 5, false)['status']);
        self::assertSame('success', $service->setActive(3, 5, false)['status']);
    }

    public function testFiltersAreNormalizedAndPassedToTheRepository(): void
    {
        $users = new InMemoryUserAdministrationRepository();
        $service = new UserAdministrationService($users, new InMemoryRoleAdministrationRepository());

        $response = $service->paginate(1, 0, 500, str_repeat('a', 140), 'EDITOR', 'VERIFY');

        self::assertSame('success', $response['status']);
        self::assertSame(1, $users->lastFilters['page']);
        self::assertSame(100, $users->lastFilters['per_page']);
        self::assertSame(120, mb_strlen($users->lastFilters['search'], 'UTF-8'));
        self::assertSame('editor', $users->lastFilters['role']);
        self::assertSame('verify', $users->lastFilters['status']);
    }

    public function testMissingProtectedAndCurrentUsersCannotBeModified(): void
    {
        $service = new UserAdministrationService(
            new InMemoryUserAdministrationRepository(),
            new InMemoryRoleAdministrationRepository()
        );

        self::assertSame('user_not_found', $service->setActive(1, 99, false)['code']);
        self::assertSame('self_protection', $service->setActive(1, 1, false)['code']);
        self::assertSame('protected_user', $service->setActive(3, 1, false)['code']);
        self::assertSame('protected_user', $service->setActive(3, 6, false)['code']);
        self::assertSame('self_protection', $service->assignRole(3, 3, 4)['code']);
        self::assertSame('invalid_role_assignment', $service->assignRole(3, 5, 1)['code']);
        self::assertSame('invalid_role_assignment', $service->assignRole(3, 5, 6)['code']);
        self::assertSame('success', $service->assignRole(1, 5, 6)['status']);
    }

    public function testRepositoryExceptionsBecomeStableContracts(): void
    {
        $service = new UserAdministrationService(
            new FailingUserAdministrationRepository(),
            new InMemoryRoleAdministrationRepository()
        );

        self::assertSame('users_list_failed', $service->paginate(1)['code']);
        self::assertSame('user_status_update_failed', $service->setActive(1, 5, false)['code']);
        self::assertSame('role_assignment_failed', $service->assignRole(1, 5, 4)['code']);
    }

    public function testChangingStatusUpdatesTheSessionRegistry(): void
    {
        $registry = new UserAdministrationRegistry();
        $service = new UserAdministrationService(
            new InMemoryUserAdministrationRepository(),
            new InMemoryRoleAdministrationRepository(),
            $registry
        );

        self::assertSame('success', $service->setActive(1, 5, false)['status']);
        self::assertSame([[5, true]], $registry->revoked);
        self::assertSame('success', $service->setActive(1, 5, true)['status']);
        self::assertSame([5], $registry->allowed);
    }
}

final class UserAdministrationRegistry implements ActiveSessionRegistry
{
    public array $revoked = [];
    public array $allowed = [];
    public function register(int $userID, string $sessionID, array $authorization = []): void {}
    public function unregister(int $userID, string $sessionID): void {}
    public function revokeUser(int $userID, bool $block = false): int { $this->revoked[] = [$userID, $block]; return 1; }
    public function allowUser(int $userID): void { $this->allowed[] = $userID; }
    public function publishRoleVersion(int $roleID, int $version): void {}
    public function publishUserAuthorizationVersion(int $userID, int $version): void {}
    public function updateAuthorization(int $userID, string $sessionID, int $roleID, int $roleVersion, int $authorizationVersion = 1): void {}
    public function updateTenantAuthorization(int $userID, string $sessionID, int $roleID, int $roleVersion): void {}
}

class InMemoryUserAdministrationRepository implements UserAdministrationRepository
{
    public array $lastFilters = [];
    public array $users = [
        1 => ['user_id' => 1, 'email' => 'root@example.com', 'role_id' => 1, 'role' => 'superadministrator', 'status' => 'verify'],
        2 => ['user_id' => 2, 'email' => 'reader@example.com', 'role_id' => 2, 'role' => 'viewer', 'status' => 'verify'],
        3 => ['user_id' => 3, 'email' => 'manager@example.com', 'role_id' => 3, 'role' => 'manager', 'status' => 'verify'],
        5 => ['user_id' => 5, 'email' => 'user@example.com', 'role_id' => 4, 'role' => 'registered', 'status' => 'verify'],
        6 => ['user_id' => 6, 'email' => 'admin@example.com', 'role_id' => 6, 'role' => 'administrator', 'status' => 'verify'],
    ];

    public function paginateUsers(int $page, int $perPage, string $search = '', string $role = '', string $status = ''): array
    {
        $this->lastFilters = compact('page', 'perPage', 'search', 'role', 'status');
        $this->lastFilters['per_page'] = $this->lastFilters['perPage'];
        return ['data' => array_values($this->users), 'meta' => $this->lastFilters];
    }

    public function findUserByID(int $userID): ?array { return $this->users[$userID] ?? null; }
    public function setActive(int $userID, bool $active): void { $this->users[$userID]['status'] = $active ? 'verify' : 'disabled'; }
    public function assignRole(int $userID, int $roleID): void { $this->users[$userID]['role_id'] = $roleID; }
}

final class FailingUserAdministrationRepository extends InMemoryUserAdministrationRepository
{
    public function paginateUsers(int $page, int $perPage, string $search = '', string $role = '', string $status = ''): array
    {
        throw new \RuntimeException('Fallo de prueba');
    }

    public function findUserByID(int $userID): ?array
    {
        throw new \RuntimeException('Fallo de prueba');
    }
}

final class InMemoryRoleAdministrationRepository implements RoleAdministrationRepository
{
    private array $roles = [
        1 => ['role_id' => 1, 'slug' => 'superadministrator', 'name' => 'Superadministrador'],
        2 => ['role_id' => 2, 'slug' => 'viewer', 'name' => 'Consulta'],
        3 => ['role_id' => 3, 'slug' => 'manager', 'name' => 'Gestor'],
        4 => ['role_id' => 4, 'slug' => 'registered', 'name' => 'Usuario'],
        6 => ['role_id' => 6, 'slug' => 'administrator', 'name' => 'Administrador'],
    ];

    public function findUserRole(int $userID): ?array { return $this->roles[$userID] ?? null; }
    public function findRoleByID(int $roleID): ?array { return $this->roles[$roleID] ?? null; }
    public function assignableRoles(): array { return array_values(array_slice($this->roles, 1, null, true)); }
    public function roleHasPermission(int $roleID, string $permission): bool
    {
        return ($roleID === 2 && $permission === 'users.view')
            || ($roleID === 3 && in_array($permission, ['users.view', 'users.manage'], true))
            || ($roleID === 6 && $permission === 'admin.access');
    }
}
