<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class LanguageDocumentationTest extends TestCase
{
    public static function urls(): array
    {
        return [['/demo/about', 'es', ['about']], ['/demo/en/about', 'en', ['about']],
            ['/demo/es/about', 'es', ['about']], ['/demo/fr/about', 'es', ['fr', 'about']],
            ['/demo/en/ajax/catalogo/list?page=2', 'en', ['ajax', 'catalogo', 'list']]];
    }

    #[DataProvider('urls')]
    #[RunInSeparateProcess]
    public function testRouterRecognizesDocumentedLanguagePrefixes(string $url, string $language, array $segments): void
    {
        define('SUPPORTED_LANGS', ['es', 'en']);
        $_SERVER['SCRIPT_NAME'] = '/demo/index.php';
        $_SERVER['REQUEST_URI'] = $url;
        $method = new \ReflectionMethod(\Router::class, 'getLanguageAndUri');
        $method->setAccessible(true);
        self::assertSame($segments, $method->invoke(new \Router()));
        self::assertSame($language, APP_LANG);
    }

    #[RunInSeparateProcess]
    public function testDocumentedControllerViewAndMetaUseTheSameLanguage(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/docs/multilenguaje.md');
        preg_match_all('/```php\s*\n(.*?)\n```/s', $source, $blocks);
        self::assertCount(6, $blocks[1]);
        foreach ($blocks[1] as $code) {
            self::assertNotEmpty(token_get_all(str_starts_with(trim($code), '<?php') ? $code : '<?php ' . $code, TOKEN_PARSE));
        }
        eval(preg_replace('/^\s*<\?php\s*/', '', $blocks[1][2]));
        foreach (['es' => 'Acerca del proyecto', 'en' => 'About the project'] as $language => $title) {
            $routeParams = ['lang' => $language];
            $data = (new \LanguageExampleController())->index($routeParams);
            self::assertSame($title, $data['texts']['title']);
            $meta = eval(preg_replace('/^\s*<\?php\s*/', '', $blocks[1][4]));
            self::assertSame($title, $meta['metaTags']['title']);
            self::assertSame($language, $meta['schema']['lang']);
            ob_start();
            eval('?>' . $blocks[1][3]);
            $html = ob_get_clean();
            self::assertStringContainsString('<h1>' . $title . '</h1>', $html);
        }
    }
}
