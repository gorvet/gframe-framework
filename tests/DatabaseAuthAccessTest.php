<?php

namespace GFrame\Tests;

use GFrame\Auth\AuthInstallationService;
use GFrame\Auth\AuthModel;
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

        $auth = new AuthModel();
        $registered = $auth->registerAcount('person@example.com', 'Password-456');
        self::assertSame('account_registered', $registered['code']);
        self::assertNull((new RoleModel())->findTenantMembership((int)$registered['data']['user_id'], 42));
        self::assertSame('account_verified', $auth->validateAcount($registered['data']['token'])['code']);

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
                (int)$registered['data']['user_id'],
                (int)$role['role_id']
            )['code']
        );

        self::assertSame(
            'success',
            $access->authorize((int)$registered['data']['user_id'], 'admin.access')['status']
        );
        self::assertSame(
            'administrator',
            $auth->login('person@example.com', 'Password-456')['data']['user']['role']
        );

        $individual = new UserPermissionService($roles);
        self::assertSame('tenant_role_assigned', $individual->assignTenantRole((int)$owner['user_id'], (int)$registered['data']['user_id'], 42, (int)$role['role_id'])['code']);
        self::assertSame(['admin.access'], $roles->authorizationForUser((int)$registered['data']['user_id'], 42)['permissions']);
        self::assertSame('permission_override_updated', $individual->setOverride((int)$owner['user_id'], (int)$registered['data']['user_id'], 'admin.access', 'deny', 42)['code']);
        self::assertSame([], $roles->authorizationForUser((int)$registered['data']['user_id'], 42)['permissions']);
        self::assertSame('tenant_membership_deactivated', $individual->deactivateTenantMembership((int)$owner['user_id'], (int)$registered['data']['user_id'], 42)['code']);
        self::assertNull($roles->authorizationForUser((int)$registered['data']['user_id'], 42));
    }

    public function testTenantOwnerManagesGestoresButCannotTransferOwnershipThroughMembershipAssignment(): void
    {
        $users = new UserModel();
        $auth = new AuthModel();
        $tenantOwner = $auth->registerAcount('tenant-owner@example.com', 'Password-123');
        $gestor = $auth->registerAcount('gestor@example.com', 'Password-123');
        $outsider = $auth->registerAcount('outsider@example.com', 'Password-123');
        $roles = new RoleModel();
        $ownerRole = $roles->createRole('Dueño', 'owner');
        $gestorRole = $roles->createRole('Gestor', 'gestor');
        $roles->assignTenantRole((int)$tenantOwner['data']['user_id'], 84, $ownerRole);
        $roles->incrementUserAuthorizationVersion((int)$tenantOwner['data']['user_id']);
        $permissions = new UserPermissionService($roles);

        self::assertSame('forbidden', $permissions->assignTenantRole((int)$outsider['data']['user_id'], (int)$gestor['data']['user_id'], 84, $gestorRole)['code']);
        self::assertSame('tenant_role_assigned', $permissions->assignTenantRole((int)$tenantOwner['data']['user_id'], (int)$gestor['data']['user_id'], 84, $gestorRole)['code']);
        self::assertSame('owner_role_requires_tenant_creation', $permissions->assignTenantRole((int)$tenantOwner['data']['user_id'], (int)$gestor['data']['user_id'], 84, $ownerRole)['code']);
        self::assertSame('owner_membership_protected', $permissions->deactivateTenantMembership((int)$tenantOwner['data']['user_id'], (int)$tenantOwner['data']['user_id'], 84)['code']);
        self::assertSame('tenant_membership_deactivated', $permissions->deactivateTenantMembership((int)$gestor['data']['user_id'], (int)$gestor['data']['user_id'], 84)['code']);
        self::assertNull($roles->authorizationForUser((int)$gestor['data']['user_id'], 84));
    }

    public function testModerationPersistenceAndDeletionRollback(): void
    {
        $users = new UserModel();
        $userID = $users->createActiveUser('moderation@example.test', password_hash('Password-123', PASSWORD_DEFAULT), 2);
        $pdo = \DatabaseManager::connection('gframe_auth_test');
        $pdo->exec("INSERT INTO tenant_memberships (user_id, tenant_id, role_id) VALUES ($userID, 999, 2)");
        $users->setAccountStatus($userID, 'suspended');
        self::assertSame('suspended', $users->findUserByID($userID)['status']);
        $users->setAccountStatus($userID, 'verify');
        self::assertSame('verify', $users->findUserByID($userID)['status']);
        $pdo->exec('CREATE TABLE moderation_related (user_id INTEGER REFERENCES users(user_id))');
        $pdo->exec("INSERT INTO moderation_related VALUES ($userID)");
        try {
            $users->deleteAccount($userID);
            self::fail('La relación debe impedir el borrado.');
        } catch (\PDOException $exception) {
            self::assertNotNull($users->findUserByID($userID));
            self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM tenant_memberships WHERE user_id = $userID")->fetchColumn());
        }
        $pdo->exec('DROP TABLE moderation_related');
        $users->deleteAccount($userID);
        self::assertNull($users->findUserByID($userID));
        self::assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM tenant_memberships WHERE user_id = $userID")->fetchColumn());
    }
}
