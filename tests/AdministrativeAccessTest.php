<?php

namespace GFrame\Tests;

require_once dirname(__DIR__) . '/src/utils/PermissionHelper.php';

use GFrame\Auth\RolePermissionService;
use GFrame\Tests\Support\InMemoryRoleModel;
use PHPUnit\Framework\TestCase;

final class AdministrativeAccessTest extends TestCase
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

    public function testSuperadministratorAlwaysPassesAdminAndPermissionMiddleware(): void
    {
        $_SESSION = [
            'auth' => ['id' => 1, 'role_id' => 1, 'role' => 'superadministrator'],
            'lastActivity' => time(),
        ];
        $middleware = $this->middleware();

        $admin = $middleware->handle(['middleware' => ['admin']]);
        $permission = $middleware->handle(['middleware' => ['can:users.manage']]);

        self::assertSame('success', $admin['status']);
        self::assertSame('superadministrator', $admin['context']['role']);
        self::assertSame('success', $permission['status']);
    }

    public function testRoleWithAdminAccessPassesAdminWithoutBecomingSuperadministrator(): void
    {
        $model = new InMemoryRoleModel();
        $model->permissions[2] = ['admin.access'];
        $_SESSION = [
            'auth' => ['id' => 2, 'role_id' => 2, 'role' => 'registered'],
            'lastActivity' => time(),
        ];

        $result = $this->middleware($model)->handle(['middleware' => ['admin']]);

        self::assertSame('success', $result['status']);
        self::assertSame('registered', $result['context']['role']);
    }

    public function testRoleWithoutAdminAccessCannotPassAdminMiddleware(): void
    {
        $_SESSION = [
            'auth' => ['id' => 2, 'role_id' => 2, 'role' => 'registered'],
            'lastActivity' => time(),
        ];

        $result = $this->middleware()->handle(['middleware' => ['admin']]);

        self::assertSame('unauthorized', $result['status']);
        self::assertSame('forbidden', $result['code']);
    }

    public function testSessionRoleCannotOverrideTheStoredRole(): void
    {
        $_SESSION = [
            'auth' => ['id' => 2, 'role_id' => 1, 'role' => 'superadministrator'],
            'lastActivity' => time(),
        ];

        $result = $this->middleware()->handle(['middleware' => ['admin']]);

        self::assertSame('unauthorized', $result['status']);
        self::assertSame('forbidden', $result['code']);
    }

    public function testNormalizedIdentityPassesAuthAndIsRejectedByGuest(): void
    {
        $_SESSION = [
            'auth' => ['id' => 2, 'role_id' => 2, 'role' => 'registered'],
            'lastActivity' => time(),
        ];
        $middleware = $this->middleware();

        self::assertSame('success', $middleware->handle(['middleware' => ['auth']])['status']);
        self::assertSame('already_logged', $middleware->handle(['middleware' => ['guest']])['code']);
    }

    public function testLegacyTopLevelSessionKeysDoNotAuthenticate(): void
    {
        $_SESSION = [
            'userID' => 1,
            'userRole' => 'superadministrator',
            'lastActivity' => time(),
        ];

        $result = $this->middleware()->handle(['middleware' => ['auth']]);

        self::assertSame('unauthorized', $result['status']);
        self::assertSame('login_required', $result['code']);
    }

    public function testRoleMiddlewareUsesTheNormalizedRoleSlug(): void
    {
        $_SESSION = [
            'auth' => ['id' => 2, 'role_id' => 2, 'role' => 'registered'],
            'lastActivity' => time(),
        ];
        $middleware = $this->middleware();

        self::assertSame('success', $middleware->handle(['middleware' => ['role:registered']])['status']);
        self::assertSame('forbidden', $middleware->handle(['middleware' => ['role:admin']])['code']);
    }

    private function middleware(?InMemoryRoleModel $model = null): \Middleware
    {
        return new \Middleware(new RolePermissionService(
            $model ?? new InMemoryRoleModel()
        ));
    }
}
