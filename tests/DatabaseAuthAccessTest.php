<?php

namespace GFrame\Tests;

use GFrame\Auth\AuthInstallationService;
use GFrame\Auth\AuthService;
use GFrame\Auth\UserModel;
use GFrame\Auth\RoleModel;
use GFrame\Auth\RolePermissionService;
use GFrame\Auth\UserPermissionService;
use PHPUnit\Framework\TestCase;

final class DatabaseAuthAccessTest extends TestCase
{
    private static string $databasePath;

    public static function setUpBeforeClass(): void
    {
        self::$databasePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gframe-auth-' . getmypid() . '.sqlite';
        if (is_file(self::$databasePath)) {
            unlink(self::$databasePath);
        }

        if (!defined('DB_DEFAULT_CONNECTION')) {
            define('DB_DEFAULT_CONNECTION', 'gframe_auth_test');
        }
        if (!defined('DB_CONNECTIONS')) {
            define('DB_CONNECTIONS', [
                'gframe_auth_test' => [
                    'driver' => 'sqlite',
                    'path' => self::$databasePath,
                    'foreign_keys' => true,
                ],
            ]);
        }

        \ORM::disconnect();
        $pdo = \DatabaseManager::connection('gframe_auth_test');
        self::assertInstanceOf(\PDO::class, $pdo);
        $schema = file_get_contents(dirname(__DIR__) . '/resources/database/schema/sqlite/auth.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);
    }

    public static function tearDownAfterClass(): void
    {
        \ORM::disconnect();
        gc_collect_cycles();
        if (is_file(self::$databasePath)) {
            unlink(self::$databasePath);
        }
    }

    public function testStandardRepositoriesSupportInstallationRegistrationAndPermissions(): void
    {
        $users = new UserModel();
        $installation = new AuthInstallationService($users);

        $owner = $installation->createFirstUser('owner@example.com', 'Password-123');
        self::assertSame('superadministrator_created', $owner['code']);

        $auth = new AuthService($users);
        $registered = $auth->register('person@example.com', 'Password-456');
        self::assertSame('account_registered', $registered['code']);
        self::assertNull((new RoleModel())->findTenantMembership((int)$registered['user_id'], 42));
        self::assertSame('account_verified', $auth->verify($registered['token'])['code']);

        $roles = new RoleModel();
        $access = new RolePermissionService($roles);
        $role = $access->createRole((int)$owner['user_id'], 'Administrador', 'administrator');
        self::assertSame('role_created', $role['code']);

        self::assertSame(
            'permission_granted',
            $access->grantPermission(
                (int)$owner['user_id'],
                (int)$role['role_id'],
                'admin.access'
            )['code']
        );
        self::assertSame(
            'role_assigned',
            $access->assignRole(
                (int)$owner['user_id'],
                (int)$registered['user_id'],
                (int)$role['role_id']
            )['code']
        );

        self::assertSame(
            'success',
            $access->authorize((int)$registered['user_id'], 'admin.access')['status']
        );
        self::assertSame(
            'administrator',
            $auth->authenticate('person@example.com', 'Password-456')['user']['role']
        );

        $individual = new UserPermissionService($roles);
        self::assertSame('tenant_role_assigned', $individual->assignTenantRole((int)$owner['user_id'], (int)$registered['user_id'], 42, (int)$role['role_id'])['code']);
        self::assertSame(['admin.access'], $roles->authorizationForUser((int)$registered['user_id'], 42)['permissions']);
        self::assertSame('permission_override_updated', $individual->setOverride((int)$owner['user_id'], (int)$registered['user_id'], 'admin.access', 'deny', 42)['code']);
        self::assertSame([], $roles->authorizationForUser((int)$registered['user_id'], 42)['permissions']);
        self::assertSame('tenant_membership_deactivated', $individual->deactivateTenantMembership((int)$owner['user_id'], (int)$registered['user_id'], 42)['code']);
        self::assertNull($roles->authorizationForUser((int)$registered['user_id'], 42));
    }

    public function testTenantOwnerManagesGestoresButCannotTransferOwnershipThroughMembershipAssignment(): void
    {
        $users = new UserModel();
        $auth = new AuthService($users);
        $tenantOwner = $auth->register('tenant-owner@example.com', 'Password-123');
        $gestor = $auth->register('gestor@example.com', 'Password-123');
        $outsider = $auth->register('outsider@example.com', 'Password-123');
        $roles = new RoleModel();
        $ownerRole = $roles->createRole('Dueño', 'owner');
        $gestorRole = $roles->createRole('Gestor', 'gestor');
        $roles->assignTenantRole((int)$tenantOwner['user_id'], 84, $ownerRole);
        $roles->incrementUserAuthorizationVersion((int)$tenantOwner['user_id']);
        $permissions = new UserPermissionService($roles);

        self::assertSame('forbidden', $permissions->assignTenantRole((int)$outsider['user_id'], (int)$gestor['user_id'], 84, $gestorRole)['code']);
        self::assertSame('tenant_role_assigned', $permissions->assignTenantRole((int)$tenantOwner['user_id'], (int)$gestor['user_id'], 84, $gestorRole)['code']);
        self::assertSame('owner_role_requires_tenant_creation', $permissions->assignTenantRole((int)$tenantOwner['user_id'], (int)$gestor['user_id'], 84, $ownerRole)['code']);
        self::assertSame('owner_membership_protected', $permissions->deactivateTenantMembership((int)$tenantOwner['user_id'], (int)$tenantOwner['user_id'], 84)['code']);
        self::assertSame('tenant_membership_deactivated', $permissions->deactivateTenantMembership((int)$gestor['user_id'], (int)$gestor['user_id'], 84)['code']);
        self::assertNull($roles->authorizationForUser((int)$gestor['user_id'], 84));
    }
}
