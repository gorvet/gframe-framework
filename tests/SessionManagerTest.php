<?php

namespace GFrame\Tests;

use GFrame\Auth\SessionManager;
use GFrame\Session\ActiveSessionRegistry;
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
            'permissions' => [],
            'role_version' => 1,
            'authorization_version' => 1,
            'bypass' => false,
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
        $registry = new SessionManagerRegistry();
        (new SessionManager($registry))->login([
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
        self::assertSame([[31, session_id()]], $registry->registered);
    }

    public function testLogoutClearsTheSessionIdentity(): void
    {
        $registry = new SessionManagerRegistry();
        $_SESSION = [
            'auth' => [
                'id' => 31,
                'email' => 'usuario@example.com',
                'name' => 'Usuario',
                'role_id' => 3,
                'role' => 'admin',
            ],
        ];

        (new SessionManager($registry))->logout();

        self::assertSame([], $_SESSION);
        self::assertSame(31, $registry->unregistered[0][0]);
    }

}

final class SessionManagerRegistry implements ActiveSessionRegistry
{
    public array $registered = [];
    public array $unregistered = [];
    public function register(int $userID, string $sessionID, array $authorization = []): void { $this->registered[] = [$userID, $sessionID]; }
    public function unregister(int $userID, string $sessionID): void { $this->unregistered[] = [$userID, $sessionID]; }
    public function revokeUser(int $userID, bool $block = false): int { return 0; }
    public function allowUser(int $userID): void {}
    public function publishRoleVersion(int $roleID, int $version): void {}
    public function publishUserAuthorizationVersion(int $userID, int $version): void {}
    public function updateAuthorization(int $userID, string $sessionID, int $roleID, int $roleVersion, int $authorizationVersion = 1): void {}
    public function updateTenantAuthorization(int $userID, string $sessionID, int $roleID, int $roleVersion): void {}
}
