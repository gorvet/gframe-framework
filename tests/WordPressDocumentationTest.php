<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class WordPressDocumentationTest extends TestCase
{
    public function testDocumentedTransportAndControllerResponse(): void
    {
        preg_match_all('/```php\R(.*?)\R```/s', file_get_contents(dirname(__DIR__) . '/docs/wordpress-headless.md'), $blocks);
        eval($blocks[1][count($blocks[1]) - 1]);
        self::assertSame('wordpress_content_loaded', $result['code']);
        self::assertSame(7, $result['data']['id']);
        $action = array_values(array_filter($blocks[1], static fn(string $code): bool => str_contains($code, "'code' => 'article_loaded'")))[0];
        $response = eval($action);
        self::assertSame('article_loaded', $response['code']);
        self::assertSame('<p>Contenido de prueba</p>', $response['data']['article']['html']);
        $failure = new \GFrame\Headless\WordPressClient('https://cms.example.com', 'test-token', static fn(): array => ['ok' => false, 'status' => 403]);
        $wordpress = $failure;
        $failed = eval($action);
        self::assertSame('wordpress_unauthorized', $failed['code']);
    }
}
