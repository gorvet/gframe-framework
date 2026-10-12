<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class RouteSessionPolicyTest extends TestCase
{
    private array $previousRoutes;

    protected function setUp(): void
    {
        $property = new \ReflectionProperty(\RouteBuilder::class, 'routes');
        $this->previousRoutes = $property->getValue();
        $property->setValue(null, []);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(\RouteBuilder::class, 'routes'))->setValue(null, $this->previousRoutes);
    }

    public function testSessionRemainsEnabledUnlessExplicitlyDisabled(): void
    {
        \RouteBuilder::get('login', 'auth/LoginController@index')->registerFinal();
        \RouteBuilder::get('worker.js', 'resources/WorkerController@index')->noSession()->registerFinal();
        \RouteBuilder::get('poll', 'poll/PollController@index')->noRefreshSession()->registerFinal();
        self::assertTrue(\RouteBuilder::all()['GET']['login']['sessionEnabled']);
        self::assertFalse(\RouteBuilder::all()['GET']['worker.js']['sessionEnabled']);
        self::assertTrue(\RouteBuilder::all()['GET']['poll']['sessionEnabled']);
    }

    public static function incompatibleMiddleware(): array
    {
        return array_map(static fn(string $name): array => [$name], ['auth', 'guest', 'admin', 'CSRF', 'role:editor', 'can:users.read', 'custom']);
    }

    #[DataProvider('incompatibleMiddleware')]
    public function testRoutesWithoutSessionRejectMiddleware(string $middleware): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \RouteBuilder::get('worker.js', 'resources/WorkerController@index')->middleware([$middleware])->noSession()->registerFinal();
    }

    public function testRoutesWithoutSessionRejectPost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \RouteBuilder::post('resource', 'resources/WorkerController@index')->noSession()->registerFinal();
    }

    public function testRoutesWithoutSessionRejectNonWebDeclarations(): void
    {
        $route = \RouteBuilder::get('api/resource', 'resources/WorkerController@index')->noSession();
        (new \ReflectionProperty(\RouteBuilder::class, 'type'))->setValue($route, 'api');
        $this->expectException(\InvalidArgumentException::class);
        $route->registerFinal();
    }

    #[RunInSeparateProcess]
    public function testRoutesWithoutSessionRejectAnActiveSessionWithoutClosingIt(): void
    {
        $this->prepareRequest('/worker.js');
        ini_set('session.use_cookies', '0');
        self::assertTrue(session_start());
        $_SESSION['auth'] = ['id' => 42];
        \RouteBuilder::get('worker.js', 'resources/WorkerController@index')->noSession()->registerFinal();
        $this->expectException(\LogicException::class);
        try {
            (new \Router())->route();
        } finally {
            self::assertSame(PHP_SESSION_ACTIVE, session_status());
            self::assertSame(42, $_SESSION['auth']['id']);
            session_destroy();
        }
    }

    #[RunInSeparateProcess]
    public function testRoutesWithoutSessionCannotBypassAutomaticAjaxMiddleware(): void
    {
        $this->prepareRequest('/ajax/worker.js');
        \RouteBuilder::get('ajax/worker.js', 'resources/WorkerController@index')->noSession()->registerFinal();
        $this->expectException(\LogicException::class);
        (new \Router())->route();
    }

    private function prepareRequest(string $uri): void
    {
        define('SUPPORTED_LANGS', ['es', 'en']);
        define('site_url', 'http://127.0.0.1/');
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
}
