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
            'role_id' => '3',
            'role' => ' ADMIN ',
        ]);

        self::assertSame([
            'id' => 27,
            'email' => 'usuario@example.com',
            'name' => 'Juana',
            'role_id' => 3,
            'role' => 'admin',
        ], $_SESSION['auth']);
    }

    public function testUpdateIdentityPreservesUnchangedNormalizedFields(): void
    {
        $_SESSION['auth'] = [
            'id' => 27,
            'email' => 'usuario@example.com',
            'name' => 'Juana',
            'role_id' => 3,
            'role' => 'admin',
        ];

        (new SessionManager())->updateIdentity(['name' => 'Ana']);

        self::assertSame(27, $_SESSION['auth']['id']);
        self::assertSame('Ana', $_SESSION['auth']['name']);
        self::assertSame(3, $_SESSION['auth']['role_id']);
    }

    public function testLoginCreatesTheNormalizedIdentityAndSecurityTokens(): void
    {
        (new SessionManager())->login([
            'id' => 31,
            'email' => 'usuario@example.com',
            'name' => 'Usuario',
            'role_id' => 3,
            'role' => 'ADMIN',
        ]);

        self::assertSame(31, $_SESSION['auth']['id']);
        self::assertSame(3, $_SESSION['auth']['role_id']);
        self::assertSame('admin', $_SESSION['auth']['role']);
        self::assertArrayNotHasKey('is_super_admin', $_SESSION['auth']);
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
                'role_id' => 3,
                'role' => 'admin',
            ],
        ];

        (new SessionManager())->logout();

        self::assertSame([], $_SESSION);
    }

}
