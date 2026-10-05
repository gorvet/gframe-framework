<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MetaSeoTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testMetaLoadsGlobalConfigurationAndDeduplicatesAssets(): void
    {
        define('ABSPATH', __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'meta-project' . DIRECTORY_SEPARATOR);
        define('SEO_ALLOW_INDEXING', true);

        $meta = new \Meta();
        $meta->setCssLinks(['common.css', 'view.css']);
        $meta->setJsScripts(['common.js', 'view.js']);

        self::assertSame('Proyecto de prueba', $meta->getMetaTag('title'));
        self::assertSame(['common.css', 'view.css'], $meta->getCssLinks());
        self::assertSame(['common.js', 'view.js'], $meta->getJsScripts());
        self::assertSame(['head.js'], $meta->getHeaderJsScripts());
    }

    #[RunInSeparateProcess]
    public function testFooterAreasResolveViewGroupAndGlobalTemplates(): void
    {
        define('ABSPATH', __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'meta-project' . DIRECTORY_SEPARATOR);
        $render = new \Render();

        self::assertSame('Contenido de la vista', trim($render->renderFooterArea('content', ['relativePath' => 'home', 'view' => 'homeIndex'])));
        self::assertSame('Contenido del grupo', trim($render->renderFooterArea('content', ['relativePath' => 'home', 'view' => 'otherView'])));
        self::assertSame('Copyright general', trim($render->renderFooterArea('copyright', ['relativePath' => 'home', 'view' => 'homeIndex'])));
        self::assertSame('', $render->renderFooterArea('credits', ['relativePath' => 'home', 'view' => 'homeIndex']));
        self::assertSame('', $render->renderFooterArea('invalid', ['relativePath' => 'home', 'view' => 'homeIndex']));
    }

    #[RunInSeparateProcess]
    public function testTemplateAndModuleMetaLoadBeforeNestedViewMeta(): void
    {
        define('ABSPATH', __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'meta-project' . DIRECTORY_SEPARATOR);
        $render = new \Render();
        $method = (new ReflectionClass(\Render::class))->getMethod('loadView');
        $method->setAccessible(true);
        $html = $method->invoke($render, [
            'view' => 'usersIndex',
            'relativePath' => 'admin/users',
            'templateName' => 'admin',
        ], []);

        self::assertSame('Vista de usuarios', trim($html));
        self::assertSame(['common.css', 'admin.css', 'users.css'], \Meta::getInstance()->getCssLinks());
        self::assertSame(['common.js', 'admin.js', 'notifications.js'], \Meta::getInstance()->getJsScripts());
    }

    #[RunInSeparateProcess]
    public function testInitialSeoRoutesAreRegisteredWhenEnabled(): void
    {
        define('SEO_ALLOW_INDEXING', true);
        define('SEO_ENABLE_SITEMAP_XML', true);
        define('SEO_ENABLE_ROBOTS_TXT', true);
        define('SEO_ENABLE_LLMS_TXT', true);
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'skeleton' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'routes_system.php';

        $routes = \RouteBuilder::all()['GET'];
        self::assertSame('seo/Sitemap', $routes['sitemap.xml']['controller']);
        self::assertSame('seo/Robots', $routes['robots.txt']['controller']);
        self::assertSame('seo/Llms', $routes['llms.txt']['controller']);
        self::assertTrue($routes['sitemap.xml']['context']['sitemap']['build']);
    }

    #[RunInSeparateProcess]
    public function testOnlyRobotsRouteRemainsWhenIndexingIsDisabled(): void
    {
        define('SEO_ALLOW_INDEXING', false);
        define('SEO_ENABLE_SITEMAP_XML', false);
        define('SEO_ENABLE_ROBOTS_TXT', true);
        define('SEO_ENABLE_LLMS_TXT', false);
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'skeleton' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'routes_system.php';

        $routes = \RouteBuilder::all()['GET'];
        self::assertArrayHasKey('robots.txt', $routes);
        self::assertArrayNotHasKey('sitemap.xml', $routes);
        self::assertArrayNotHasKey('llms.txt', $routes);

        $method = (new ReflectionClass(\Robots::class))->getMethod('render');
        $method->setAccessible(true);
        self::assertSame("User-agent: *\nDisallow: /\n", $method->invoke(new \Robots()));
    }

    #[RunInSeparateProcess]
    public function testRobotsReferencesTheGeneratedSitemap(): void
    {
        if (!defined('site_url')) define('site_url', 'https://example.test/');
        define('SEO_ALLOW_INDEXING', true);
        define('SEO_ENABLE_SITEMAP_XML', true);
        $method = (new ReflectionClass(\Robots::class))->getMethod('render');
        $method->setAccessible(true);
        $content = $method->invoke(new \Robots());

        self::assertStringContainsString('Disallow: /api/', $content);
        self::assertStringContainsString('Sitemap: https://example.test/sitemap.xml', $content);
    }

    #[RunInSeparateProcess]
    public function testRobotsDoesNotReferenceSitemapWhenSitemapIsDisabled(): void
    {
        define('site_url', 'https://example.test/');
        define('SEO_ALLOW_INDEXING', true);
        define('SEO_ENABLE_SITEMAP_XML', false);
        $method = (new ReflectionClass(\Robots::class))->getMethod('render');
        $method->setAccessible(true);
        $content = $method->invoke(new \Robots());

        self::assertStringContainsString('Disallow: /api/', $content);
        self::assertStringNotContainsString('Sitemap:', $content);
    }
}
