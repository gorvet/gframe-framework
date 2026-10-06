<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Config\LegacyConfigBridge;
use GFrame\Seo\SeoPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SeoPolicyTest extends TestCase
{
    public static function settings(): array
    {
        return [[true, true, false, true, true], [true, false, false, false, true],
            [false, true, false, false, false], [true, true, true, false, false]];
    }

    #[DataProvider('settings')]
    #[RunInSeparateProcess]
    public function testGlobalPolicy(bool $enabled, bool $indexing, bool $debug, bool $publish, bool $schema): void
    {
        define('ABSPATH', __DIR__ . '/fixtures/meta-project/');
        ConfigRepository::replace(['app' => ['url' => 'https://example.test/', 'debug' => $debug],
            'seo' => ['enabled' => $enabled, 'allow_indexing' => $indexing,
                'sitemap' => true, 'robots' => true, 'llms' => true]]);
        LegacyConfigBridge::defineConstants();
        self::assertSame($publish, SeoPolicy::allowsIndexing());
        self::assertSame($publish, SEO_ENABLE_SITEMAP_XML);
        self::assertSame($enabled, SEO_ENABLE_ROBOTS_TXT);
        require dirname(__DIR__) . '/resources/skeleton/config/routes/routes_system.php';
        $routes = \RouteBuilder::all()['GET'] ?? [];
        self::assertSame($enabled, isset($routes['robots.txt']));
        self::assertSame($publish, isset($routes['sitemap.xml']));
        self::assertSame($publish, isset($routes['llms.txt']));
        $meta = new \Meta();
        $meta->setMetaTags(['robots' => 'index,follow']);
        self::assertSame($publish ? 'index,follow' : 'noindex,nofollow,noarchive', $meta->getMetaTag('robots'));
        self::assertSame($schema, $meta->renderSchema() !== '');
        $robots = new \ReflectionMethod(\Robots::class, 'render');
        $robots->setAccessible(true);
        $text = $robots->invoke(new \Robots());
        if ($publish) self::assertStringContainsString('Sitemap:', $text);
        else self::assertSame("User-agent: *\nDisallow: /\n", $text);
    }

    #[RunInSeparateProcess]
    public function testIndividualEndpointSwitchesRemainEffective(): void
    {
        define('ABSPATH', __DIR__ . '/fixtures/meta-project/');
        ConfigRepository::replace(['app' => ['url' => 'https://example.test/'],
            'seo' => ['enabled' => true, 'allow_indexing' => true,
                'sitemap' => false, 'robots' => false, 'llms' => false]]);
        LegacyConfigBridge::defineConstants();
        self::assertTrue(SeoPolicy::allowsIndexing());
        require dirname(__DIR__) . '/resources/skeleton/config/routes/routes_system.php';
        $routes = \RouteBuilder::all()['GET'] ?? [];
        foreach (['sitemap.xml', 'robots.txt', 'llms.txt'] as $uri) self::assertArrayNotHasKey($uri, $routes);
        $sitemap = new \ReflectionMethod(\Sitemap::class, 'collectPublicGetRoutes');
        self::assertSame([], $sitemap->invoke(new \Sitemap(), ['GET' => ['publica' => ['controller' => 'home/HomeController']]]));
        self::assertFalse((new \ReflectionMethod(\Llms::class, 'isEnabled'))->invoke(new \Llms()));
        self::assertFalse((new \ReflectionMethod(\Llms::class, 'isSitemapEnabled'))->invoke(new \Llms()));
        self::assertFalse((new \ReflectionMethod(\Llms::class, 'isRobotsEnabled'))->invoke(new \Llms()));
    }

    #[RunInSeparateProcess]
    public function testLegacyIndexExclusionsKeepTheirOriginalScope(): void
    {
        define('ABSPATH', __DIR__ . '/fixtures/meta-project/');
        define('site_url', 'https://example.test/');
        define('SEO_ALLOW_INDEXING', true);
        $public = ['type' => 'web', 'controller' => 'home/HomeController', 'action' => 'index', 'view' => 'homeIndex'];
        $llmsOnly = $public + ['context' => ['llms' => ['include' => false]]];
        $bothIndexes = $public + ['context' => ['sitemap' => ['include' => false]]];
        $sitemap = new \ReflectionMethod(\Sitemap::class, 'collectPublicGetRoutes');
        $items = $sitemap->invoke(new \Sitemap(), ['GET' => ['solo-llms' => $llmsOnly, 'ambos' => $bothIndexes]]);
        self::assertCount(1, $items);
        self::assertSame('https://example.test/solo-llms', $items[0]['loc']);
        $llms = new \ReflectionMethod(\Llms::class, 'isPublicRoute');
        $meta = new \Meta();
        foreach ([$llmsOnly, $bothIndexes] as $route) {
            self::assertFalse($llms->invoke(new \Llms(), 'publica', $route));
            $meta->setRouteParams($route);
            self::assertSame('index,follow', $meta->getMetaTag('robots'));
        }
    }

    #[RunInSeparateProcess]
    public function testPrivateAndErrorRoutesAreExcludedAcrossHtmlAndIndexes(): void
    {
        define('ABSPATH', __DIR__ . '/fixtures/meta-project/');
        define('site_url', 'https://example.test/');
        define('SEO_ALLOW_INDEXING', true);
        $public = ['type' => 'web', 'controller' => 'home/HomeController', 'action' => 'index', 'view' => 'homeIndex'];
        $cases = [['isProtected' => true], ['permission' => 'reports.view'], ['httpCode' => 404],
            ['middleware' => ['auth']], ['middleware' => ['admin']], ['middleware' => ['role:editor']],
            ['middleware' => ['can:manage']], ['uri' => 'admin/panel'], ['uri' => 'core/config'],
            ['uri' => 'app/controllers'], ['uri' => 'storage/secret'], ['uri' => 'packages/autoload'],
            ['uri' => 'vendor/autoload'], ['uri' => 'robots.txt']];
        $sitemap = new \ReflectionMethod(\Sitemap::class, 'collectPublicGetRoutes');
        $llms = new \ReflectionMethod(\Llms::class, 'isPublicRoute');
        $meta = new \Meta();
        foreach ($cases as $case) {
            $route = $public + $case;
            $uri = $route['uri'] ?? 'privada';
            $meta->setRouteParams($route);
            self::assertSame('noindex,nofollow,noarchive', $meta->getMetaTag('robots'), json_encode($case));
            self::assertSame([], $sitemap->invoke(new \Sitemap(), ['GET' => [$uri => $route]]));
            self::assertFalse($llms->invoke(new \Llms(), $uri, $route));
        }
        $meta->setRouteParams($public + ['uri' => 'publica', 'relativePath' => 'admin']);
        self::assertSame('index,follow', $meta->getMetaTag('robots'));
    }

    #[RunInSeparateProcess]
    public function testAuthenticationPagesDeclareNoindexOnRoutes(): void
    {
        require dirname(__DIR__) . '/resources/modules/auth-ui/application/routes/routes_auth.php';
        $routes = \RouteBuilder::all()['GET'];
        foreach (['login', 'login/register', 'login/lostpassword', 'login/resetpassword', 'login/verify'] as $uri) {
            self::assertFalse($routes[$uri]['context']['seo']['indexable'], $uri);
        }
    }

    #[RunInSeparateProcess]
    public function testRouteControlsHtmlAndBothIndexesWithoutMetaOverride(): void
    {
        define('ABSPATH', __DIR__ . '/fixtures/meta-project/');
        define('site_url', 'https://example.test/');
        define('SEO_ALLOW_INDEXING', true);
        $public = ['controller' => 'home/HomeController', 'action' => 'index', 'view' => 'homeIndex', 'type' => 'web'];
        $hidden = $public + ['context' => ['seo' => ['indexable' => false]]];
        $meta = new \Meta();
        $meta->setRouteParams($hidden);
        $meta->setMetaTags(['robots' => 'index,follow']);
        self::assertSame('noindex,nofollow,noarchive', $meta->getMetaTag('robots'));
        $meta->setRouteParams($public);
        $meta->applyMetaConfig(['metaTags' => ['robots' => 'noindex,nofollow']]);
        self::assertSame('index,follow', $meta->getMetaTag('robots'));
        $sitemap = new \ReflectionMethod(\Sitemap::class, 'collectPublicGetRoutes');
        $sitemap->setAccessible(true);
        $items = $sitemap->invoke(new \Sitemap(), ['GET' => ['publica' => $public, 'oculta' => $hidden]]);
        self::assertCount(1, $items);
        self::assertSame('https://example.test/publica', $items[0]['loc']);
        $llms = new \ReflectionMethod(\Llms::class, 'isPublicRoute');
        $llms->setAccessible(true);
        self::assertTrue($llms->invoke(new \Llms(), 'publica', $public));
        self::assertFalse($llms->invoke(new \Llms(), 'oculta', $hidden));
        foreach (['auth', 'admin', 'role:editor', 'can:manage'] as $middleware) {
            self::assertFalse(SeoPolicy::routeIsIndexable($public + ['middleware' => [$middleware]]));
        }
        self::assertFalse(SeoPolicy::routeIsIndexable($public, 'admin/panel'));
        self::assertFalse(SeoPolicy::routeIsIndexable($public + ['relativePath' => 'admin/users']));
        self::assertFalse(SeoPolicy::routeIsIndexable($public + ['uri' => 'login', 'relativePath' => 'home']));
        self::assertFalse(SeoPolicy::routeIsIndexable($public + ['httpCode' => 404]));
        self::assertFalse(SeoPolicy::routeIsIndexable($public + ['context' => ['sitemap' => ['include' => false]]], '', 'sitemap'));
        self::assertFalse(SeoPolicy::routeIsIndexable($public + ['context' => ['llms' => ['include' => false]]], '', 'llms'));
    }
}
