<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class ResponseDocumentationTest extends TestCase
{
    public function testResponseExamplesHaveValidPhpSyntax(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/docs/respuestas.md');
        preg_match_all('/```php\s*\n(.*?)\n```/s', $source, $blocks);
        self::assertCount(3, $blocks[1]);
        foreach ($blocks[1] as $code) {
            self::assertNotEmpty(token_get_all('<?php ' . $code, TOKEN_PARSE));
        }
    }

    public function testStatusRatherThanPresenceOfCodeIdentifiesErrors(): void
    {
        $method = new \ReflectionMethod(\Router::class, 'isErrorResponse');
        $method->setAccessible(true);
        $router = new \Router();
        self::assertFalse($method->invoke($router, ['status' => 'success', 'code' => 'saved']));
        self::assertFalse($method->invoke($router, ['code' => 'not_found']));
        self::assertTrue($method->invoke($router, ['status' => 'error', 'code' => 'invalid_param']));
        self::assertTrue($method->invoke($router, (object)['status' => 'unauthorized', 'code' => 'permission']));
        $responder = new \ErrorResponder();
        self::assertSame(400, $responder->resolveHttpCode('invalid_param', 400));
        self::assertSame(403, $responder->resolveHttpCode('permission', 400));
        self::assertSame(404, $responder->resolveHttpCode('not_found', 400));
        self::assertSame(400, $responder->resolveHttpCode('custom_error', 400));
    }
}
