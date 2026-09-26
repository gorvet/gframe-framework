<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use PHPUnit\Framework\TestCase;

final class AdministrativeAccessTest extends TestCase
{
    private array $configuration = [];
    private array $session = [];

    protected function setUp(): void
    {
        $this->configuration = (array)ConfigRepository::get();
        $this->session = $_SESSION ?? [];
        ConfigRepository::merge(['auth' => ['administrator_roles' => ['admin']]]);
    }

    protected function tearDown(): void
    {
        ConfigRepository::replace($this->configuration);
        $_SESSION = $this->session;
    }

    public function testSuperAdministratorAlwaysPassesAdminMiddleware(): void
    {
        $_SESSION = [
            'auth' => [
                'id' => 1,
                'role' => 'registrado',
                'is_super_admin' => true,
            ],
            'lastActivity' => time(),
        ];

        $result = (new \Middleware())->handle(['middleware' => ['admin']]);

        self::assertSame('success', $result['status']);
        self::assertSame('super_admin', $result['context']['role']);
    }

    public function testConfiguredAdministratorPassesWithoutBecomingSuperAdministrator(): void
    {
        $_SESSION = [
            'auth' => [
                'id' => 8,
                'role' => 'admin',
                'is_super_admin' => false,
            ],
            'lastActivity' => time(),
        ];

        $result = (new \Middleware())->handle(['middleware' => ['admin']]);

        self::assertSame('success', $result['status']);
        self::assertSame('admin', $result['context']['role']);
    }

    public function testOrdinaryUserCannotPassAdminMiddleware(): void
    {
        $_SESSION = [
            'auth' => [
                'id' => 9,
                'role' => 'registrado',
                'is_super_admin' => false,
            ],
            'lastActivity' => time(),
        ];

        $result = (new \Middleware())->handle(['middleware' => ['admin']]);

        self::assertSame('unauthorized', $result['status']);
        self::assertSame('forbidden', $result['code']);
    }

    public function testNormalizedIdentityPassesAuthAndIsRejectedByGuest(): void
    {
        $_SESSION = [
            'auth' => [
                'id' => 12,
                'role' => 'registrado',
                'is_super_admin' => false,
            ],
            'lastActivity' => time(),
        ];

        $middleware = new \Middleware();

        self::assertSame('success', $middleware->handle(['middleware' => ['auth']])['status']);
        self::assertSame('already_logged', $middleware->handle(['middleware' => ['guest']])['code']);
    }

    public function testStringFalseDoesNotGrantSuperAdministratorAccess(): void
    {
        $_SESSION = [
            'auth' => [
                'id' => 13,
                'role' => 'registrado',
                'is_super_admin' => 'false',
            ],
            'lastActivity' => time(),
        ];

        $result = (new \Middleware())->handle(['middleware' => ['admin']]);

        self::assertSame('unauthorized', $result['status']);
        self::assertSame('forbidden', $result['code']);
    }

    public function testNormalizedIdentityTakesPrecedenceOverLegacyKeys(): void
    {
        $_SESSION = [
            'auth' => [
                'id' => 13,
                'role' => 'registrado',
                'is_super_admin' => false,
            ],
            'userID' => 99,
            'userRole' => 'admin',
            'isSuperAdmin' => true,
            'lastActivity' => time(),
        ];

        $result = (new \Middleware())->handle(['middleware' => ['admin']]);

        self::assertSame('unauthorized', $result['status']);
        self::assertSame('forbidden', $result['code']);
    }

    public function testNormalizedSuperAdministratorBypassesPermissions(): void
    {
        $_SESSION = [
            'auth' => [
                'id' => 1,
                'role' => 'registrado',
                'is_super_admin' => true,
            ],
            'lastActivity' => time(),
        ];

        $result = (new \Middleware())->handle([
            'middleware' => ['can:users.manage'],
        ]);

        self::assertSame('success', $result['status']);
        self::assertSame('super_admin', $result['context']['role']);
    }

    public function testLegacyIdentityRemainsAvailableDuringMigration(): void
    {
        $_SESSION = [
            'userID' => 14,
            'userRole' => 'admin',
            'isSuperAdmin' => false,
            'lastActivity' => time(),
        ];

        $result = (new \Middleware())->handle(['middleware' => ['admin']]);

        self::assertSame('success', $result['status']);
        self::assertSame('admin', $result['context']['role']);
    }
}
