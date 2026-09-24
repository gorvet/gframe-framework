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
            'userID' => 1,
            'userRole' => 'registrado',
            'isSuperAdmin' => true,
            'lastActivity' => time(),
        ];

        $result = (new \Middleware())->handle(['middleware' => ['admin']]);

        self::assertSame('success', $result['status']);
        self::assertSame('super_admin', $result['context']['role']);
    }

    public function testConfiguredAdministratorPassesWithoutBecomingSuperAdministrator(): void
    {
        $_SESSION = [
            'userID' => 8,
            'userRole' => 'admin',
            'isSuperAdmin' => false,
            'lastActivity' => time(),
        ];

        $result = (new \Middleware())->handle(['middleware' => ['admin']]);

        self::assertSame('success', $result['status']);
        self::assertSame('admin', $result['context']['role']);
    }

    public function testOrdinaryUserCannotPassAdminMiddleware(): void
    {
        $_SESSION = [
            'userID' => 9,
            'userRole' => 'registrado',
            'isSuperAdmin' => false,
            'lastActivity' => time(),
        ];

        $result = (new \Middleware())->handle(['middleware' => ['admin']]);

        self::assertSame('unauthorized', $result['status']);
        self::assertSame('forbidden', $result['code']);
    }
}
