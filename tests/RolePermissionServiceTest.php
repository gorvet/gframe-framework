<?php

namespace GFrame\Tests;

use GFrame\Auth\RolePermissionService;
use GFrame\Session\SessionRuntime;
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
        SessionRuntime::clearAuthorizationStale();
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

    public function testStaleSessionRefreshesPermissionsWithoutLoggingOut(): void
    {
        $model = new InMemoryRoleModel();
        $model->permissions[2] = ['content.publish'];
        $_SESSION = [
            'auth' => [
                'id' => 2,
                'role_id' => 2,
                'role' => 'registered',
                'role_version' => 1,
                'permissions' => ['content.read'],
            ],
        ];
        SessionRuntime::markAuthorizationStale();

        $result = (new RolePermissionService($model))->authorize(2, 'content.publish');

        self::assertSame('success', $result['status']);
        self::assertSame(['content.publish'], $_SESSION['auth']['permissions']);
        self::assertFalse(SessionRuntime::authorizationStale());
    }

    public function testTenantRoleIsATemplateAndUserOverridesHavePrecedence(): void
    {
        $model = new InMemoryRoleModel();
        $model->roles[3] = ['role_id' => 3, 'name' => 'Editor', 'slug' => 'editor', 'is_system' => 0, 'security_version' => 1];
        $model->permissions[3] = ['content.read', 'content.publish'];
        $model->tenantRoles[2][18] = 3;
        $model->userOverrides[2][18] = ['content.publish' => false, 'content.delete' => true];
        $_SESSION['auth'] = ['id' => 2, 'role_id' => 2, 'role' => 'registered'];
        $service = new RolePermissionService($model);

        self::assertSame('success', $service->authorize(2, 'content.read', 18)['status']);
        self::assertSame('unauthorized', $service->authorize(2, 'content.publish', 18)['status']);
        self::assertSame('success', $service->authorize(2, 'content.delete', 18)['status']);
    }

    public function testOnlyActiveTenantIsCachedAndChangingTenantReloadsItsMembership(): void
    {
        $model = new InMemoryRoleModel();
        $model->tenantRoles[2][18] = 2;
        $model->tenantRoles[2][19] = 2;
        $model->permissions[2] = ['content.read'];
        $_SESSION['auth'] = ['id' => 2, 'role_id' => 2, 'role' => 'registered'];
        $service = new RolePermissionService($model);

        self::assertSame('success', $service->authorize(2, 'content.read', 18)['status']);
        $reads = $model->authorizationReads;
        self::assertSame('success', $service->authorize(2, 'content.read', 18)['status']);
        self::assertSame($reads, $model->authorizationReads);
        self::assertSame('success', $service->authorize(2, 'content.read', 19)['status']);
        self::assertGreaterThan($reads, $model->authorizationReads);
        self::assertSame(19, $_SESSION['auth']['tenant_authorization']['tenant_id']);
    }
}
