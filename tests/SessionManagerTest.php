<?php

namespace GFrame\Tests;

use GFrame\Auth\SessionManager;
use PHPUnit\Framework\TestCase;

final class SessionManagerTest extends TestCase
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

    public function testUpdateIdentityNormalizesTheFrameworkSessionContract(): void
    {
        (new SessionManager())->updateIdentity([
            'id' => '27',
            'email' => ' usuario@example.com ',
            'name' => ' Juana ',
            'role' => ' ADMIN ',
            'is_super_admin' => 'false',
        ]);

        self::assertSame([
            'id' => 27,
            'email' => 'usuario@example.com',
            'name' => 'Juana',
            'role' => 'admin',
            'is_super_admin' => false,
        ], $_SESSION['auth']);
    }

    public function testUpdateIdentityPreservesUnchangedNormalizedFields(): void
    {
        $_SESSION['auth'] = [
            'id' => 27,
            'email' => 'usuario@example.com',
            'name' => 'Juana',
            'role' => 'admin',
            'is_super_admin' => true,
        ];

        (new SessionManager())->updateIdentity(['name' => 'Ana']);

        self::assertSame(27, $_SESSION['auth']['id']);
        self::assertSame('Ana', $_SESSION['auth']['name']);
        self::assertTrue($_SESSION['auth']['is_super_admin']);
    }

    public function testLoginCreatesTheNormalizedIdentityAndSecurityTokens(): void
    {
        (new SessionManager())->login([
            'id' => 31,
            'email' => 'usuario@example.com',
            'name' => 'Usuario',
            'role' => 'ADMIN',
            'is_super_admin' => false,
        ]);

        self::assertSame(31, $_SESSION['auth']['id']);
        self::assertSame('admin', $_SESSION['auth']['role']);
        self::assertFalse($_SESSION['auth']['is_super_admin']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $_SESSION['csrfToken']);
        self::assertIsInt($_SESSION['lastActivity']);
        self::assertIsInt($_SESSION['csrfTimestamp']);
    }

    public function testLogoutClearsTheSessionIdentity(): void
    {
        $_SESSION = [
            'auth' => [
                'id' => 31,
                'email' => 'usuario@example.com',
                'name' => 'Usuario',
                'role' => 'admin',
                'is_super_admin' => false,
            ],
        ];

        (new SessionManager())->logout();

        self::assertSame([], $_SESSION);
    }
}
