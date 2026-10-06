<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Config\LegacyConfigBridge;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SeoIndexabilityTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testGlobalIndexingBlockDisablesSitemapAndLlmsButKeepsRobots(): void
    {
        ConfigRepository::replace([
            'app' => [
                'url' => 'https://example.test',
                'name' => 'Example',
                'debug' => false,
                'timezone' => 'UTC',
                'supported_languages' => ['es'],
            ],
            'seo' => [
                'enabled' => true,
                'allow_indexing' => false,
                'sitemap' => true,
                'robots' => true,
                'llms' => true,
            ],
        ]);

        LegacyConfigBridge::defineConstants();

        self::assertTrue(SEO_ENABLED);
        self::assertFalse(SEO_ALLOW_INDEXING);
        self::assertFalse(SEO_ENABLE_SITEMAP_XML);
        self::assertTrue(SEO_ENABLE_ROBOTS_TXT);
        self::assertFalse(SEO_ENABLE_LLMS_TXT);
    }

    #[RunInSeparateProcess]
    public function testDebugDisablesSeoOutputExceptRobotsPolicyEndpoint(): void
    {
        ConfigRepository::replace([
            'app' => [
                'url' => 'https://example.test',
                'name' => 'Example',
                'debug' => true,
                'timezone' => 'UTC',
                'supported_languages' => ['es'],
            ],
            'seo' => [
                'enabled' => true,
                'allow_indexing' => true,
                'sitemap' => true,
                'robots' => true,
                'llms' => true,
            ],
        ]);

        LegacyConfigBridge::defineConstants();

        self::assertFalse(SEO_ENABLED);
        self::assertFalse(SEO_ALLOW_INDEXING);
        self::assertFalse(SEO_ENABLE_SITEMAP_XML);
        self::assertTrue(SEO_ENABLE_ROBOTS_TXT);
        self::assertFalse(SEO_ENABLE_LLMS_TXT);
    }

    #[RunInSeparateProcess]
    public function testSitemapExcludesNoindexAndProtectedRoutes(): void
    {
        define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
        define('site_url', 'https://example.test/');

        $routes = [
            'GET' => [
                'publica' => [
                    'type' => 'web',
                    'controller' => 'home/HomeController@index',
                    'action' => 'index',
                    'middleware' => [],
                    'context' => [],
                ],
                'noindex' => [
                    'type' => 'web',
                    'controller' => 'home/HomeController@index',
                    'action' => 'index',
                    'middleware' => [],
                    'context' => ['seo' => ['indexable' => false]],
                ],
                'permiso' => [
                    'type' => 'web',
                    'controller' => 'home/HomeController@index',
                    'action' => 'index',
                    'middleware' => ['can:reports.view'],
                    'context' => [],
                ],
            ],
        ];

        $method = (new ReflectionClass(\Sitemap::class))->getMethod('collectPublicGetRoutes');
        $method->setAccessible(true);
        $items = $method->invoke(new \Sitemap(), $routes);

        self::assertCount(1, $items);
        self::assertSame('https://example.test/publica', $items[0]['loc']);
    }

    #[RunInSeparateProcess]
    public function testLlmsExcludesNoindexAndProtectedRoutes(): void
    {
        define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
        define('site_url', 'https://example.test/');

        $method = (new ReflectionClass(\Llms::class))->getMethod('isPublicRoute');
        $method->setAccessible(true);
        $llms = new \Llms();

        self::assertTrue($method->invoke($llms, 'publica', [
            'type' => 'web',
            'middleware' => [],
            'context' => [],
        ]));
        self::assertFalse($method->invoke($llms, 'noindex', [
            'type' => 'web',
            'middleware' => [],
            'context' => ['seo' => ['indexable' => false]],
        ]));
        self::assertFalse($method->invoke($llms, 'roles', [
            'type' => 'web',
            'middleware' => ['role:manager'],
            'context' => [],
        ]));
        self::assertFalse($method->invoke($llms, 'admin-area', [
            'type' => 'web',
            'middleware' => ['admin'],
            'context' => [],
        ]));
    }

    #[RunInSeparateProcess]
    public function testRouteNoindexCannotBeOverriddenByViewMeta(): void
    {
        define('ABSPATH', __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'meta-project' . DIRECTORY_SEPARATOR);
        define('SEO_ALLOW_INDEXING', true);

        $meta = new \Meta();
        $meta->setMetaTags(['robots' => 'index,follow']);
        $meta->setRouteParams(['context' => ['seo' => ['indexable' => false]]]);

        self::assertSame('noindex,nofollow,noarchive', $meta->getMetaTag('robots'));
    }

    #[RunInSeparateProcess]
    public function testGlobalNoindexCannotBeOverriddenByViewMeta(): void
    {
        define('ABSPATH', __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'meta-project' . DIRECTORY_SEPARATOR);
        define('SEO_ALLOW_INDEXING', false);

        $meta = new \Meta();
        $meta->setMetaTags(['robots' => 'index,follow']);

        self::assertSame('noindex,nofollow,noarchive', $meta->getMetaTag('robots'));
    }

    #[RunInSeparateProcess]
    public function testViewRobotsMetaCannotBlockIndexableRoute(): void
    {
        define('ABSPATH', __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'meta-project' . DIRECTORY_SEPARATOR);
        define('SEO_ALLOW_INDEXING', true);

        $meta = new \Meta();
        $meta->setMetaTags(['robots' => 'noindex,nofollow']);
        $meta->setRouteParams(['context' => ['seo' => ['indexable' => true]]]);

        self::assertSame('index,follow', $meta->getMetaTag('robots'));
    }
}
