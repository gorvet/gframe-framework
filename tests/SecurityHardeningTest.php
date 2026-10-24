<?php

namespace GFrame\Tests;

use GFrame\Auth\AuthRateLimiter;
use GFrame\Config\ConfigRepository;
use GFrame\Http\RequestSecurity;
use GFrame\Http\SecurityHeaders;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SecurityHardeningTest extends TestCase
{
    private array $configuration;
    private array $server;

    protected function setUp(): void { $this->configuration = ConfigRepository::get(); $this->server = $_SERVER; }
    protected function tearDown(): void { ConfigRepository::replace($this->configuration); $_SERVER = $this->server; }

    public function testForwardedHeadersNeedATrustedPeerAndIpChainStopsAtFirstUntrustedHop(): void
    {
        ConfigRepository::replace(['security' => ['trusted_proxies' => ['10.0.0.0/8', '2001:db8::/32']]]);
        $_SERVER = ['REMOTE_ADDR' => '192.0.2.1', 'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_FOR' => '198.51.100.1', 'SERVER_PORT' => 80];
        self::assertFalse(RequestSecurity::isSecure());
        self::assertSame('192.0.2.1', RequestSecurity::clientAddress());
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.99, 198.51.100.1, 10.0.0.3';
        self::assertTrue(RequestSecurity::isSecure());
        self::assertSame('198.51.100.1', RequestSecurity::clientAddress());
        self::assertTrue(RequestSecurity::trustedProxy('2001:db8::1'));
        self::assertFalse(RequestSecurity::trustedProxy('2001:db9::1'));
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https,http';
        self::assertFalse(RequestSecurity::isSecure());
    }

    #[RunInSeparateProcess]
    public function testSameOriginGuardIgnoresForwardedHostFromAnUntrustedVisitor(): void
    {
        putenv('APP_URL'); unset($_ENV['APP_URL'], $_SERVER['APP_URL']);
        ConfigRepository::replace([]);
        $_SERVER = ['REMOTE_ADDR' => '192.0.2.2', 'HTTP_HOST' => 'app.test', 'SERVER_PORT' => 80,
            'HTTP_ORIGIN' => 'https://attacker.test', 'HTTP_X_FORWARDED_HOST' => 'attacker.test', 'HTTP_X_FORWARDED_PROTO' => 'https'];
        $guard = new \ReflectionMethod(\Middleware::class, 'is_same_origin');
        $middleware = new \Middleware();
        self::assertFalse($guard->invoke($middleware));
        ConfigRepository::replace(['security' => ['trusted_proxies' => ['10.0.0.0/8']]]);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'app.test';
        $_SERVER['HTTP_ORIGIN'] = 'https://app.test';
        self::assertTrue($guard->invoke($middleware));
        ConfigRepository::merge(['app' => ['url' => 'https://canonical.test']]);
        self::assertFalse($guard->invoke($middleware));
    }

    #[RunInSeparateProcess]
    public function testCookiesPreserveExistingStrictPolicyAndIgnoreSpoofedHttps(): void
    {
        session_set_cookie_params(['secure' => true, 'httponly' => true, 'samesite' => 'Strict', 'path' => '/app/', 'domain' => 'example.test', 'lifetime' => 123]);
        ConfigRepository::replace(require dirname(__DIR__) . '/config/defaults.php');
        $_SERVER = ['REMOTE_ADDR' => '192.0.2.2', 'HTTP_X_FORWARDED_PROTO' => 'https'];
        $method = new \ReflectionMethod(\GFrame\Session\SessionRuntime::class, 'applyCookieParams');
        $method->invoke(null);
        $cookie = session_get_cookie_params();
        self::assertTrue($cookie['secure']);
        self::assertSame('Strict', $cookie['samesite']);
        self::assertSame('/app/', $cookie['path']);
        self::assertSame('example.test', $cookie['domain']);
        self::assertSame(123, $cookie['lifetime']);
        session_set_cookie_params(['secure' => false, 'samesite' => '']);
        $method->invoke(null);
        self::assertFalse(session_get_cookie_params()['secure']);
        self::assertSame('Lax', session_get_cookie_params()['samesite']);
    }

    public function testHstsAndEmbeddingPoliciesAreExplicitAndHttpsIsTrusted(): void
    {
        ConfigRepository::replace([]);
        $_SERVER = ['REMOTE_ADDR' => '192.0.2.2', 'HTTP_X_FORWARDED_PROTO' => 'https'];
        self::assertSame('nosniff', SecurityHeaders::values()['X-Content-Type-Options']);
        self::assertArrayNotHasKey('X-Frame-Options', SecurityHeaders::values());
        ConfigRepository::merge(['security' => ['headers' => ['hsts_max_age' => 300, 'frame_options' => 'SAMEORIGIN']]]);
        self::assertArrayNotHasKey('Strict-Transport-Security', SecurityHeaders::values());
        $_SERVER['HTTPS'] = 'on';
        self::assertSame('max-age=300', SecurityHeaders::values()['Strict-Transport-Security']);
        ConfigRepository::merge(['security' => ['headers' => ['enabled' => false]]]);
        self::assertSame([], SecurityHeaders::values());
    }

    public function testRateLimitReservesBeforeWorkExpiresAndFailsClosedOnCorruption(): void
    {
        $directory = sys_get_temp_dir() . '/gframe-auth-rate-' . bin2hex(random_bytes(5));
        $now = 1000;
        ConfigRepository::replace(['auth' => ['rate_limit' => ['login_attempts' => 1, 'mail_attempts' => 1, 'window_seconds' => 10]]]);
        $limiter = new AuthRateLimiter($directory, static function () use (&$now): int { return $now; });
        try {
            self::assertSame('success', $limiter->reserve('login', 'USER@example.test', '192.0.2.1')['status']);
            $denied = $limiter->reserve('login', 'user@example.test', '192.0.2.2');
            self::assertSame('auth_rate_limited', $denied['code']);
            self::assertSame(10, $denied['data']['retry_after']);
            self::assertSame('success', $limiter->reserve('recovery', 'user@example.test', '192.0.2.1')['status']);
            self::assertSame('auth_rate_limited', $limiter->reserve('resend', 'user@example.test', '192.0.2.1')['code']);
            $now += 11;
            self::assertSame('success', $limiter->reserve('login', 'other@example.test', '192.0.2.1')['status']);
            self::assertCount(2, json_decode(file_get_contents($directory . '/attempts.json'), true));
            self::assertStringNotContainsString('example.test', file_get_contents($directory . '/attempts.json'));
            file_put_contents($directory . '/attempts.json', 'broken');
            self::assertSame('auth_rate_limit_unavailable', $limiter->reserve('login', 'x', 'y')['code']);
        } finally { @unlink($directory . '/attempts.json'); @rmdir($directory); }
    }

    public function testConcurrentRequestsCannotShareTheLastSlot(): void
    {
        $directory = sys_get_temp_dir() . '/gframe-auth-race-' . bin2hex(random_bytes(5));
        mkdir($directory);
        $processes = [];
        try {
            for ($i = 0; $i < 8; $i++) {
                $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/auth-rate-worker.php', $directory], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                self::assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = [$process, $pipes];
            }
            file_put_contents($directory . '/go', '1');
            $accepted = 0;
            foreach ($processes as [$process, $pipes]) {
                $result = json_decode(stream_get_contents($pipes[1]), true);
                self::assertIsArray($result, stream_get_contents($pipes[2]));
                if ($result['status'] === 'success') $accepted++;
                else self::assertSame('auth_rate_limited', $result['code']);
                fclose($pipes[1]); fclose($pipes[2]);
                self::assertSame(0, proc_close($process));
            }
            self::assertSame(1, $accepted);
        } finally {
            foreach ($processes as [$process, $pipes]) if (is_resource($process)) { proc_terminate($process); proc_close($process); }
            @unlink($directory . '/go'); @unlink($directory . '/attempts.json'); @rmdir($directory);
        }
    }

    #[RunInSeparateProcess]
    public function testControllerStopsAtTheQuotaBeforeCallingTheAuthenticationModel(): void
    {
        require_once dirname(__DIR__) . '/resources/modules/auth-ui/application/app/controllers/auth-ui/AuthController.php';
        $root = sys_get_temp_dir() . '/gframe-controller-rate-' . bin2hex(random_bytes(5));
        define('ABSPATH', $root . '/');
        ConfigRepository::replace(['auth' => ['rate_limit' => ['login_attempts' => 1]]]);
        $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
        $_POST = ['login_email' => 'user@example.test', 'login_password' => 'Password-123'];
        $auth = new class extends \GFrame\Auth\AuthModel {
            public int $calls = 0;
            public function login(string $email, string $password): array { $this->calls++; return ['status' => 'error', 'code' => 'invalid_user']; }
        };
        $controller = new \GFrame\Modules\AuthUi\Controllers\AuthController($auth);
        try {
            $first = $controller->login();
            self::assertSame('invalid_user', $first['code']);
            self::assertSame('El correo o la contraseña no son correctos.', $first['message']);
            self::assertSame('auth_rate_limited', $controller->login()['code']);
            self::assertSame(1, $auth->calls);
        } finally { @unlink($root . '/storage/auth-rate/attempts.json'); @rmdir($root . '/storage/auth-rate'); @rmdir($root . '/storage'); @rmdir($root); }
    }

    public function testMysqlSilentServerCannotHoldPreflightPastItsBudget(): void
    {
        if (!extension_loaded('pdo_mysql')) self::markTestSkipped('Requiere pdo_mysql.');
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)substr(strrchr(stream_socket_get_name($listener, false), ':'), 1);
        try {
            $start = microtime(true);
            $result = (new \GFrame\Install\DatabasePreflight())->check(sys_get_temp_dir(), ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => $port, 'database' => 'test', 'username' => 'test', 'password' => 'not-a-secret']);
            self::assertSame('database_connection_timeout', $result['code']);
            self::assertLessThan(7.0, microtime(true) - $start);
        } finally { fclose($listener); }
    }

    #[RunInSeparateProcess]
    public function testRouteChannelMismatchStopsBeforeControllerAndSystemUsesWeb(): void
    {
        define('APP_LANG', 'es');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $routes = new \ReflectionProperty(\RouteBuilder::class, 'routes');
        $router = new \Router();
        $type = new \ReflectionProperty(\Router::class, 'intendedType');
        $resolve = new \ReflectionMethod(\Router::class, 'getRouteParamsFromDeclarative');
        $route = ['type' => 'web', 'controller' => 'TestController', 'action' => 'index'];
        $routes->setValue(null, ['GET' => ['api/items' => $route]]);
        $type->setValue($router, 'api');
        try { $resolve->invoke($router, ['api', 'items']); self::fail('Debe rechazar la incoherencia.'); }
        catch (\LogicException $error) { self::assertStringContainsString('api/items', $error->getMessage()); }
        $route['type'] = 'system';
        $routes->setValue(null, ['GET' => ['sitemap.xml' => $route]]);
        $type->setValue($router, 'web');
        self::assertSame('web', $resolve->invoke($router, ['sitemap.xml'])['type']);
        $routes->setValue(null, ['GET' => ['ajax/heartbeat' => $route]]);
        $type->setValue($router, 'ajax');
        self::assertSame('ajax', $resolve->invoke($router, ['ajax', 'heartbeat'])['type']);
        $routes->setValue(null, ['GET' => ['api/heartbeat' => $route]]);
        $type->setValue($router, 'api');
        $this->expectException(\LogicException::class);
        $resolve->invoke($router, ['api', 'heartbeat']);
    }

    public function testHttpClientRejectsLocalAndOtherProtocols(): void
    {
        foreach (['file:///etc/passwd', 'ftp://example.test/file', '//example.test/file', '/relative'] as $url) {
            $response = \HttpClient::request(['url' => $url]);
            self::assertFalse($response['ok']);
            self::assertSame(0, $response['status']);
            self::assertSame('', $response['body']);
        }
    }

    public function testHttpRedirectsAllowHttpButCannotReadFiles(): void
    {
        if (!extension_loaded('curl')) self::markTestSkipped('Requiere cURL.');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $server = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/fixtures/http-protocol-router.php'], [0 => ['pipe', 'r'], 1 => ['file', $null, 'a'], 2 => ['file', $null, 'a']], $pipes);
        self::assertIsResource($server);
        fclose($pipes[0]);
        try {
            $ready = false;
            for ($i = 0; $i < 40; $i++) {
                $connection = @stream_socket_client('tcp://' . $address, $number, $message, 0.1);
                if ($connection) { fclose($connection); $ready = true; break; }
                usleep(100000);
            }
            self::assertTrue($ready);
            $response = \HttpClient::request(['url' => 'http://' . $address . '/redirect-http', 'timeout' => 3]);
            self::assertTrue($response['ok']);
            self::assertSame(200, $response['status']);
            $blocked = \HttpClient::request(['url' => 'http://' . $address . '/redirect-file', 'timeout' => 3]);
            self::assertFalse($blocked['ok']);
            self::assertNotSame('', $blocked['error']);
            self::assertStringNotContainsString('<?php', $blocked['body']);
        } finally { proc_terminate($server); proc_close($server); }
    }
}
