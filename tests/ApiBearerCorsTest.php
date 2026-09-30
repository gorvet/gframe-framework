<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ApiBearerCorsTest extends TestCase
{
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN'] = 'https://client.example.com';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer Secret-With-Case';
    }

    protected function tearDown(): void { $_SERVER = $this->server; }

    public function testApiRouteAcceptsItsConfiguredBearerAndOrigin(): void
    {
        $result = (new \Middleware())->handle($this->route([
            'api_token' => 'Secret-With-Case',
            'allowed_origins' => ['https://client.example.com'],
            'api_consumer' => 'mobile-app',
        ]));
        self::assertSame('success', $result['status']);
        self::assertTrue($result['cors_headers']);
        self::assertSame('mobile-app', $result['context']['api_consumer']);
    }

    public function testBearerTokensRemainCaseSensitive(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret-with-case';
        $result = (new \Middleware())->handle($this->route(['api_token' => 'Secret-With-Case', 'allowed_origins' => ['client.example.com']]));
        self::assertSame('invalid_token', $result['code']);
        self::assertSame(401, $result['http_code']);
    }

    public function testMultipleConsumersCanHaveDifferentOrigins(): void
    {
        $result = (new \Middleware())->handle($this->route(['api_consumers' => [
            ['name' => 'web', 'token' => 'Secret-With-Case', 'origins' => ['client.example.com']],
            ['name' => 'partner', 'token' => 'partner-secret', 'origins' => ['partner.example.com']],
        ]]));
        self::assertSame('success', $result['status']);
        self::assertSame('web', $result['context']['api_consumer']);
    }

    public function testConsumerIdentityExposesTenantAndScopes(): void
    {
        $result = (new \Middleware())->handle($this->route(['api_consumers' => [[
            'name' => 'partner',
            'token' => 'Secret-With-Case',
            'tenant_id' => 14,
            'scopes' => ['orders.read'],
            'origins' => ['client.example.com'],
        ]]]));
        self::assertSame(14, $result['context']['api_tenant_id']);
        self::assertSame(['orders.read'], $result['context']['api_scopes']);
    }

    public function testCredentialProviderCanBeReplacedByAnApplication(): void
    {
        $provider = new class implements \GFrame\Http\Contracts\ApiCredentialProvider {
            public function authenticate(string $bearer, array $context): array
            {
                return ['status' => 'success', 'data' => ['name' => 'database-client', 'tenant_id' => 8, 'scopes' => [], 'origins' => ['client.example.com']]];
            }

            public function allowsPreflightOrigin(string $origin, array $context): bool { return true; }
        };
        $result = (new \Middleware(null, $provider))->handle($this->route([]));
        self::assertSame('success', $result['status']);
        self::assertSame('database-client', $result['context']['api_consumer']);
    }

    public function testPreflightValidatesOriginWithoutRequiringBearer(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS'; unset($_SERVER['HTTP_AUTHORIZATION']);
        $result = (new \Middleware())->handle($this->route(['api_token' => 'Secret-With-Case', 'allowed_origins' => ['client.example.com']]));
        self::assertSame('preflight', $result['status']);
        self::assertSame(204, $result['http_code']);
        self::assertTrue($result['cors_headers']);
    }

    public function testOriginOutsideConsumerAllowlistIsRejected(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://other.example.com';
        $result = (new \Middleware())->handle($this->route(['api_token' => 'Secret-With-Case', 'allowed_origins' => ['client.example.com']]));
        self::assertSame('cors_denied', $result['code']);
        self::assertSame(403, $result['http_code']);
    }

    public function testOptionsCanResolveADeclaredApiRoute(): void
    {
        if (!defined('APP_LANG')) define('APP_LANG', 'es');
        require __DIR__ . '/fixtures/routes_api_bearer.php';
        $router = new \Router();
        $reflection = new ReflectionClass($router);
        $type = $reflection->getProperty('intendedType'); $type->setAccessible(true); $type->setValue($router, 'api');
        $method = $reflection->getMethod('getRouteParamsFromDeclarative'); $method->setAccessible(true);
        $route = $method->invoke($router, ['api', 'bearer-test']);
        self::assertSame('api/TestController', $route['controller']);
        self::assertContains('allow_cors_with_token', $route['middleware']);
    }

    private function route(array $context): array { return ['middleware' => ['allow_cors_with_token'], 'context' => $context, 'refreshSession' => true]; }
}
