<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class RenderDocumentationTest extends TestCase
{
    protected function setUp(): void
    {
        define('ABSPATH', __DIR__ . '/fixtures/meta-project/');
    }

    public function testPhpExamplesHaveValidSyntax(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/docs/render.md');
        preg_match_all('/```php\s*\n(.*?)\n```/s', $source, $blocks);
        self::assertGreaterThanOrEqual(5, count($blocks[1]));
        foreach ($blocks[1] as $code) {
            if (str_starts_with(trim($code), 'public function')) $code = 'class Example {' . $code . '}';
            if (!str_starts_with(trim($code), '<?php') && !str_starts_with(trim($code), '<')) $code = '<?php ' . $code;
            self::assertNotEmpty(token_get_all($code, TOKEN_PARSE));
        }
    }

    public function testDocumentedViewReceivesDataAndEscapesText(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/docs/render.md');
        $fixture = file_get_contents(ABSPATH . 'app/views/home/homeAbout.php');
        self::assertStringContainsString(trim($fixture), $source);
        $render = new \Render();
        $method = new \ReflectionMethod($render, 'loadView');
        $method->setAccessible(true);
        $html = $method->invoke($render, ['relativePath' => 'home', 'view' => 'homeAbout', 'templateName' => 'home'], [
            'title' => '<Título>', 'description' => 'Texto & ejemplo',
        ]);
        self::assertStringContainsString('<h1>&lt;Título&gt;</h1>', $html);
        self::assertStringContainsString('<p>Texto &amp; ejemplo</p>', $html);
    }

    public function testResponseDataRemainsNestedAndConstructorReceivesRouteArray(): void
    {
        $render = new \Render();
        $method = new \ReflectionMethod($render, 'interpretDataResponse');
        $method->setAccessible(true);
        $data = ['status' => 'success', 'data' => ['title' => 'Acerca']];
        self::assertSame($data, $method->invoke($render, $data));
        $constructor = new \ReflectionMethod($render, 'instantiateController');
        $constructor->setAccessible(true);
        $params = ['lang' => 'es', 'params' => ['id' => '25']];
        $instance = $constructor->invoke($render, RenderDocumentationController::class, $params);
        self::assertSame($params, $instance->params);
    }
}

final class RenderDocumentationController
{
    public function __construct(public array $params) {}
}
