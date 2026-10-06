<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class MiddlewareDocumentationTest extends TestCase
{
    private array $previousServer;
    private array $previousPost;
    private mixed $previousSession;

    protected function setUp(): void
    {
        $this->previousServer = $_SERVER;
        $this->previousPost = $_POST;
        $this->previousSession = $_SESSION ?? null;
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->previousServer;
        $_POST = $this->previousPost;
        if ($this->previousSession === null) unset($_SESSION); else $_SESSION = $this->previousSession;
    }

    public function testChainStopsAtFirstFailureAndRejectsUnknownNames(): void
    {
        $handler = new \Middleware();
        self::assertSame('login_required', $handler->handle(['middleware' => ['auth', 'custom']])['code']);
        self::assertSame('unknown_middleware', $handler->handle(['middleware' => ['custom']])['code']);
        self::assertSame('success', $handler->handle(['middleware' => ['GUEST']])['status']);
    }

    public function testCsrfAndHoneypotUseDocumentedPostFields(): void
    {
        $handler = new \Middleware();
        self::assertSame('fail_csrf', $handler->handle(['middleware' => ['CSRF']])['code']);
        $_SESSION = ['csrfToken' => 'test-token', 'csrfTimestamp' => 100];
        $_POST = ['csrfToken' => 'test-token', 'csrfTimestamp' => '100'];
        self::assertSame('success', $handler->handle(['middleware' => ['CSRF', 'honeypot']])['status']);
        $_POST['middle_name'] = 'bot';
        self::assertSame('is_bot', $handler->handle(['middleware' => ['CSRF', 'honeypot']])['code']);
        $_POST['csrfTimestamp'] = '99';
        self::assertSame('to_reload', $handler->handle(['middleware' => ['CSRF']])['code']);
    }

    public function testAutomaticGuardOrderAndExclusions(): void
    {
        $router = new \Router();
        $type = new \ReflectionProperty($router, 'intendedType');
        $type->setAccessible(true);
        $type->setValue($router, 'ajax');
        $method = new \ReflectionMethod($router, 'injectAutoMiddlewares');
        $method->setAccessible(true);
        self::assertSame(['block_external_ajax', 'CSRF', 'auth'], $method->invoke($router, ['auth']));
        self::assertSame(['block_external_ajax', 'auth'], $method->invoke($router, ['auth'], ['CSRF']));
        self::assertSame(['block_external_ajax', 'auth', 'CSRF'], $method->invoke($router, ['auth', 'CSRF'], ['CSRF']));
    }

    public function testPhpExamplesHaveValidSyntax(): void
    {
        preg_match_all('/```php\s*\n(.*?)\n```/s', file_get_contents(dirname(__DIR__) . '/docs/middleware.md'), $blocks);
        self::assertCount(4, $blocks[1]);
        foreach ($blocks[1] as $code) {
            if (str_starts_with(trim($code), 'public function')) $code = 'class Example {' . $code . '}';
            self::assertNotEmpty(token_get_all('<?php ' . $code, TOKEN_PARSE));
        }
    }
}
