<?php

namespace GFrame\Tests;

require_once dirname(__DIR__) . '/src/utils/PermissionHelper.php';

use GFrame\Auth\RolePermissionService;
use GFrame\Tests\Support\InMemoryRoleModel;
use PHPUnit\Framework\TestCase;

final class RolePermissionServiceTest extends TestCase
{
    private array $session = [];

    protected function setUp(): void
    {
        $this->session = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->session;
    }

    public function testSuperadministratorBypassesPermissions(): void
    {
        $service = new RolePermissionService(new InMemoryRoleModel());

        $result = $service->authorize(1, 'anything.manage');

        self::assertSame('success', $result['status']);
        self::assertTrue($result['data']['bypass']);
        self::assertSame('superadministrator', $result['data']['role']);
    }

    public function testOrdinaryRoleRequiresAnAssignedPermission(): void
    {
        $model = new InMemoryRoleModel();
        $service = new RolePermissionService($model);

        self::assertSame('unauthorized', $service->authorize(2, 'content.publish')['status']);

        $model->permissions[2][] = 'content.publish';
        self::assertSame('success', $service->authorize(2, 'content.publish')['status']);
    }

    public function testOnlySuperadministratorCanCreateAndAssignRoles(): void
    {
        $model = new InMemoryRoleModel();
        $service = new RolePermissionService($model);

        self::assertSame('forbidden', $service->createRole(2, 'Editor', 'editor')['code']);

        $created = $service->createRole(1, 'Editor', 'editor');
        self::assertSame('role_created', $created['code']);
        self::assertSame('role_assigned', $service->assignRole(1, 2, (int)$created['role_id'])['code']);
        self::assertSame('editor', $model->findUserRole(2)['slug']);
    }

    public function testSuperadministratorRoleCannotBeAssignedOrRemoved(): void
    {
        $service = new RolePermissionService(new InMemoryRoleModel());

        self::assertSame(
            'superadministrator_role_protected',
            $service->assignRole(1, 2, 1)['code']
        );
        self::assertSame(
            'system_role_protected',
            $service->deleteRole(1, 1)['code']
        );
        self::assertSame(
            'superadministrator_role_protected',
            $service->assignRole(1, 1, 2)['code']
        );
    }

    public function testMiddlewareUsesRolePermissionsWhenIdentityHasRoleID(): void
    {
        $model = new InMemoryRoleModel();
        $model->permissions[2] = ['admin.access', 'content.publish'];
        $service = new RolePermissionService($model);
        $_SESSION = [
            'auth' => ['id' => 2, 'role_id' => 2, 'role' => 'registered'],
            'lastActivity' => time(),
        ];

        $middleware = new \Middleware($service);

        self::assertSame('success', $middleware->handle(['middleware' => ['admin']])['status']);
        self::assertSame(
            'success',
            $middleware->handle(['middleware' => ['can:content.publish']])['status']
        );
        self::assertSame(
            'forbidden',
            $middleware->handle(['middleware' => ['can:users.manage']])['code']
        );
    }
}
