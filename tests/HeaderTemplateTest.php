<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class HeaderTemplateTest extends TestCase
{
    public function testFooterPreservesAreasScriptOrderAndSessionNotification(): void
    {
        if (!defined('site_url')) define('site_url', 'https://example.test/');
        $renderer = new class {
            public object $metasController;
            public function renderFooterArea(string $area, array $routeParams, array $data): string { return 'area-' . $area; }
            public function render(): string
            {
                $this->metasController = new class {
                    public function getJsScripts(): array { return ['https://example.test/first.js', 'https://example.test/second.js', 'https://example.test/first.js']; }
                };
                $routeParams = ['isProtected' => true, 'expired' => true];
                ob_start();
                include dirname(__DIR__) . '/resources/skeleton/app/views/templates/footer.php';
                return (string)ob_get_clean();
            }
        };
        $html = $renderer->render();
        foreach (['area-content', 'area-copyright', 'area-credits', 'id="toastBox"', 'window.is_protected = true', 'session_expired_', 'try { localStorage.setItem'] as $part) {
            self::assertStringContainsString($part, $html);
        }
        self::assertSame(1, substr_count($html, 'src="https://example.test/first.js"'));
        self::assertLessThan(strpos($html, 'first.js'), strpos($html, 'window.site_url ='));
        self::assertLessThan(strpos($html, 'second.js'), strpos($html, 'first.js'));
        self::assertStringContainsString('</body>', $html);
        self::assertStringContainsString('</html>', $html);
    }

    public function testHeaderRendersDynamicSocialMetadataAndEarlyScripts(): void
    {
        if (!defined('site_url')) define('site_url', 'https://example.test/');
        $renderer = new class {
            public object $metasController;
            public function render(array $tags): string
            {
                $this->metasController = new class($tags) {
                    public function __construct(private array $tags) {}
                    public function getMetaTag(string $key): string { return htmlspecialchars($this->tags[$key] ?? '', ENT_QUOTES, 'UTF-8'); }
                    public function getHeaderJsScripts(): array { return ['https://example.test/preload.js']; }
                    public function getCssLinks(): array { return ['https://example.test/style.css']; }
                    public function renderSchema(): string { return ''; }
                };
                $bodyClass = 'test';
                ob_start();
                include dirname(__DIR__) . '/resources/skeleton/app/views/templates/header.php';
                return (string)ob_get_clean();
            }
        };
        $html = $renderer->render([
            'title' => 'Título "especial"', 'description' => 'A & B', 'robots' => 'noindex,nofollow',
            'canonical' => 'https://example.test/page', 'ogurl' => 'https://example.test/social',
            'ogimage' => 'https://example.test/og.png', 'ogimagew' => '1200', 'ogimageh' => '630',
            'xcard' => 'summary_large_image', 'ximage' => 'https://example.test/x.png',
            'ximagealt' => 'Imagen alternativa', 'xsite' => '@site', 'xcreator' => '@author',
        ]);
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        foreach (['og:title' => 'Título "especial"', 'og:description' => 'A & B',
            'og:url' => 'https://example.test/social', 'og:image:width' => '1200',
            'twitter:card' => 'summary_large_image', 'twitter:image' => 'https://example.test/x.png',
            'twitter:site' => '@site', 'twitter:creator' => '@author'] as $tag => $value) {
            self::assertSame($value, $xpath->evaluate('string(//meta[@name="' . $tag . '" or @property="' . $tag . '"]/@content)'));
        }
        self::assertStringContainsString('noindex,nofollow, max-image-preview:large', $html);
        self::assertLessThan(strpos($html, 'style.css'), strpos($html, 'preload.js'));
        self::assertLessThan(strpos($html, '<body'), strpos($html, 'preload.js'));
        $fallback = $renderer->render(['ogimage' => 'https://example.test/fallback.png']);
        self::assertStringContainsString('name="twitter:image" content="https://example.test/fallback.png"', $fallback);
        self::assertStringContainsString('name="twitter:card" content="summary"', $fallback);
        self::assertDoesNotMatchRegularExpression('/<meta (?:name="twitter:[^"]+"|property="og:[^"]+") content=""/', $fallback);
    }
}
