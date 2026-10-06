<?php

namespace GFrame\Tests;

use GFrame\Auth\AuthModel;
use GFrame\Auth\RolePermissionService;
use GFrame\Config\ConfigRepository;
use GFrame\Headless\WordPressClient;
use GFrame\Install\MigrationRunner;
use GFrame\Media\MediaScopeResolver;
use GFrame\Security\Encryption;
use GFrame\Session\DatabaseSessionHandler;
use GFrame\Tests\Support\InMemoryRoleModel;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AuditRegressionTest extends TestCase
{
    public function testEncryptionRejectsShortTagsWrongIvVersionsAndTypes(): void
    {
        $encryption = new Encryption('audit-test');
        $payload = json_decode(base64_decode($encryption->encrypt('private')), true);
        foreach ([['tag', ''], ['tag', base64_encode(substr(base64_decode($payload['tag']), 0, 1))],
            ['iv', base64_encode('short')], ['v', '1'], ['iv', []]] as [$key, $value]) {
            $modified = $payload; $modified[$key] = $value;
            try {
                $encryption->decrypt(base64_encode(json_encode($modified)));
                self::fail('El payload inválido debe rechazarse.');
            } catch (\InvalidArgumentException $exception) {
                self::assertNotEmpty($exception->getMessage());
            }
        }
        $payload['data'] = base64_encode('tampered');
        $this->expectException(\RuntimeException::class);
        $encryption->decrypt(base64_encode(json_encode($payload)));
    }

    public function testWordPressUsesTheDecodedTransportEnvelopeIncludingNestedObjects(): void
    {
        $body = '{"status":"success","code":"content_loaded","data":{"items":[{"id":7}]},"meta":{"contract_version":"2.0"}}';
        $client = new WordPressClient('https://cms.example.test', 'test', static fn(): array => ['ok' => true, 'status' => 200, 'json' => json_decode($body)]);
        self::assertSame([['id' => 7]], $client->contents()['data']['items']);
    }

    #[RunInSeparateProcess]
    public function testGuardsFailClosedWithUnrelatedOrIncompleteContext(): void
    {
        define('site_url', 'https://example.test/');
        $server = $_SERVER; $get = $_GET;
        try {
            $_SERVER = ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json']; $_GET = [];
            $middleware = new \Middleware();
            $webhook = new \ReflectionMethod($middleware, 'webhook_guard');
            foreach ([['methods' => ['POST']], ['require_header' => 'X-Test'], ['require_query' => 'key'], ['validate_hmac' => true]] as $context) {
                self::assertSame('unauthorized', $webhook->invoke($middleware, ['context' => $context])['status']);
            }
            $_SERVER['HTTP_X_TEST'] = 'secret';
            self::assertSame('success', $webhook->invoke($middleware, ['context' => ['require_header' => 'X-Test', 'expected_value' => 'secret']])['status']);
            $_SERVER['HTTP_X_TEST'] = 'wrong';
            self::assertSame('unauthorized', $webhook->invoke($middleware, ['context' => ['require_header' => 'X-Test', 'expected_value' => 'secret']])['status']);
            $_SERVER = ['REQUEST_METHOD' => 'GET']; $_GET = ['token' => 'arbitrary'];
            $sse = new \ReflectionMethod($middleware, 'sse_guard');
            self::assertSame('unauthorized', $sse->invoke($middleware, ['context' => ['require_token' => true]])['status']);
            self::assertSame('success', $sse->invoke($middleware, ['context' => ['require_token' => true, 'expected' => 'arbitrary']])['status']);
            self::assertSame('unauthorized', $sse->invoke($middleware, ['context' => ['require_token' => true, 'verify' => static fn(): bool => false]])['status']);
            self::assertSame('unauthorized', $sse->invoke($middleware, ['context' => ['require_token' => true, 'expected' => '0']])['status']);
            $_GET['token'] = ['invalid'];
            self::assertSame('sse_token_missing', $sse->invoke($middleware, ['context' => ['require_token' => true, 'expected' => 'secret']])['code']);
        } finally { $_SERVER = $server; $_GET = $get; }
    }

    #[RunInSeparateProcess]
    public function testTenantAuthorizationAndMediaCannotUseDifferentTenants(): void
    {
        define('TENANT', 'company_id'); define('TENANT_TABLE', 'companies');
        ConfigRepository::replace(['media' => ['scope' => 'tenant'], 'tenancy' => ['key' => 'company_id']]);
        $_SESSION = ['company_id' => 1, 'auth' => ['id' => 2, 'role' => 'registered']];
        $_POST = []; $_GET = []; $_REQUEST = ['tenant_id' => 2];
        $roles = new InMemoryRoleModel();
        $roles->permissions[2] = ['media.view']; $roles->tenantRoles[2][2] = 2;
        $middleware = new \Middleware(new RolePermissionService($roles));
        $check = new \ReflectionMethod($middleware, 'checkPermission');
        self::assertSame('unauthorized', $check->invoke($middleware, 'media.view', [])['status']);
        try { (new MediaScopeResolver())->resolve(); self::fail('El ámbito debe rechazar el tenant diferente.'); }
        catch (\RuntimeException $exception) { self::assertNotEmpty($exception->getMessage()); }
        $_SESSION['company_id'] = 2;
        self::assertSame('success', $check->invoke($middleware, 'media.view', [])['status']);
        self::assertSame(2, (new MediaScopeResolver())->resolve()->id());
        $_SESSION['auth']['tenant_id'] = 2;
        $service = new RolePermissionService($roles);
        self::assertSame('success', $service->authorize(2, 'media.view')['status']);
        self::assertSame(2, (new MediaScopeResolver())->resolve()->id());
        \GFrame\Session\SessionRuntime::markAuthorizationStale();
        self::assertSame('success', $service->authorize(2, 'media.view')['status']);
        self::assertSame(2, (new MediaScopeResolver())->resolve()->id());
    }

    #[RunInSeparateProcess]
    public function testResetRevokesAllManagedSessionsAndKeepsOtherUsers(): void
    {
        $pdo = $this->database();
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/database/schema/sqlite/auth.sql'));
        $registry = new DatabaseSessionHandler($pdo);
        $auth = new AuthModel(sessions: $registry);
        $registered = $auth->registerAcount('audit@example.test', 'Password-123');
        $id = $registered['data']['user_id'];
        $auth->validateAcount($registered['data']['token']);
        $other = $auth->registerAcount('other@example.test', 'Password-123');
        $auth->validateAcount($other['data']['token']);
        $registry->register($other['data']['user_id'], 'other-device');
        $registry->register($id, 'device-a'); $registry->register($id, 'device-b');
        self::assertTrue($registry->validateId('device-a'));
        $token = $auth->recoveryAcount('audit@example.test')['data']['token'];
        self::assertSame('password_reset', $auth->resetPassword($token, 'New-password-456')['code']);
        self::assertFalse($registry->validateId('device-a')); self::assertFalse($registry->validateId('device-b'));
        self::assertTrue($registry->validateId('other-device'));
        self::assertSame('invalid_token', $auth->resetPassword($token, 'Other-password-456')['code']);
    }

    public function testHistoricalAuthMigrationsResumeWithoutChangingPublishedSql(): void
    {
        $runner = MigrationRunner::frameworkDefault();
        foreach ([false, true] as $partiallyApplied) {
            $pdo = new \PDO('sqlite::memory:');
            $pdo->exec('CREATE TABLE users (user_id INTEGER PRIMARY KEY); CREATE TABLE roles (role_id INTEGER PRIMARY KEY)');
            if ($partiallyApplied) {
                $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/auth-ui/database/migrations/sqlite/202609290001_managed_sessions.sql'));
                $pdo->exec('ALTER TABLE users ADD COLUMN authorization_version INTEGER NOT NULL DEFAULT 1');
            }
            $result = $runner->migrate($pdo, 'sqlite', ['auth-ui']);
            self::assertGreaterThan(0, $result['count']);
            self::assertSame(0, $runner->migrate($pdo, 'sqlite', ['auth-ui'])['count']);
            self::assertContains('tenant_role_version', array_column($pdo->query('PRAGMA table_info(gframe_sessions)')->fetchAll(\PDO::FETCH_ASSOC), 'name'));
        }
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (user_id INTEGER PRIMARY KEY, authorization_version TEXT NULL); CREATE TABLE roles (role_id INTEGER PRIMARY KEY)');
        $this->expectException(\RuntimeException::class);
        $runner->migrate($pdo, 'sqlite', ['auth-ui']);
    }

    private function database(): \PDO
    {
        define('DB_DEFAULT_CONNECTION', 'audit_regression');
        define('DB_CONNECTIONS', ['audit_regression' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        return \DatabaseManager::connection('audit_regression');
    }

    #[RunInSeparateProcess]
    public function testRoutesEscapeLiteralPunctuationWhileKeepingPlaceholders(): void
    {
        define('APP_LANG', 'es');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $routes = new \ReflectionProperty(\RouteBuilder::class, 'routes');
        $routes->setValue(null, []);
        foreach (['audit.orders', 'auditXorders', 'file(1)#.xml', 'files/{id}.xml', 'files/{id}Xxml'] as $pattern) {
            \RouteBuilder::get($pattern, 'audit/AuditController@index')->registerFinal();
        }
        $router = new \Router();
        $type = new \ReflectionProperty($router, 'intendedType'); $type->setValue($router, 'web');
        $match = new \ReflectionMethod($router, 'getRouteParamsFromDeclarative');
        self::assertSame('auditXorders', $match->invoke($router, ['auditXorders'])['uri']);
        self::assertSame('file(1)#.xml', $match->invoke($router, ['file(1)#.xml'])['uri']);
        self::assertSame(['id' => '7'], $match->invoke($router, ['files', '7.xml'])['params']);
        self::assertSame('files/{id}Xxml', $match->invoke($router, ['files', '7Xxml'])['uri']);
    }
}
