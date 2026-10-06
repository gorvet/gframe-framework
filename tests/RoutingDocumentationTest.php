<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class RoutingDocumentationTest extends TestCase
{
    public function testCallerFileClassifiesRoutesWithoutAddingUrlPrefixes(): void
    {
        $property = new \ReflectionProperty(\RouteBuilder::class, 'routes');
        $property->setAccessible(true);
        $previous = $property->getValue();
        $directory = sys_get_temp_dir() . '/gframe-routing-doc-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $files = [];
        try {
            $property->setValue(null, []);
            foreach (['ajax', 'api', 'webhook', 'sse', 'system', 'web'] as $type) {
                $file = $directory . '/routes_' . $type . '_catalog.php';
                $files[] = $file;
                file_put_contents($file, "<?php RouteBuilder::get('catalog-$type', 'catalog/ProductController@index')->registerFinal();");
                require $file;
                $route = \RouteBuilder::all()['GET']['catalog-' . $type];
                self::assertSame($type, $route['type']);
                self::assertArrayNotHasKey($type . '/catalog-' . $type, \RouteBuilder::all()['GET']);
            }
        } finally {
            $property->setValue(null, $previous);
            foreach ($files as $file) unlink($file);
            rmdir($directory);
        }
    }

    public function testUrlPrefixSelectsTransportRatherThanAjaxHeader(): void
    {
        $previous = $_SERVER;
        $method = new \ReflectionMethod(\Router::class, 'detectIntendedType');
        $method->setAccessible(true);
        $router = new \Router();
        try {
            $_SERVER['SCRIPT_NAME'] = '/demo/index.php';
            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
            foreach (['ajax', 'api', 'webhook', 'sse', 'system', 'products'] as $prefix) {
                $_SERVER['REQUEST_URI'] = '/demo/' . $prefix . '/catalog?limit=10';
                $expected = in_array($prefix, ['ajax', 'api', 'webhook', 'sse'], true) ? $prefix : 'web';
                self::assertSame($expected, $method->invoke($router));
            }
        } finally {
            $_SERVER = $previous;
        }
    }

    public function testRoutingExamplesHaveValidPhpSyntax(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/docs/rutas.md');
        preg_match_all('/```php\s*\n(.*?)\n```/s', $source, $blocks);
        self::assertGreaterThanOrEqual(10, count($blocks[1]));
        foreach ($blocks[1] as $index => $code) {
            if (str_starts_with(trim($code), 'public function')) {
                $code = 'class RoutingExample' . $index . ' {' . $code . '}';
            }
            self::assertNotEmpty(token_get_all('<?php ' . $code, TOKEN_PARSE));
        }
    }

    public function testDocumentedRouteDeclarationsRegisterExpectedOptions(): void
    {
        require_once dirname(__DIR__) . '/src/GFrame/Config/functions.php';
        $property = new \ReflectionProperty(\RouteBuilder::class, 'routes');
        $property->setAccessible(true);
        $previous = $property->getValue();
        $property->setValue(null, []);
        try {
            $source = file_get_contents(dirname(__DIR__) . '/docs/rutas.md');
            preg_match_all('/```php\s*\n(.*?)\n```/s', $source, $blocks);
            foreach ($blocks[1] as $code) {
                if (str_starts_with(trim($code), 'use RouteBuilder as Route;')) eval($code);
            }
            $routes = \RouteBuilder::all();
            self::assertSame('productShow', $routes['GET']['productos/{id}']['view']);
            self::assertSame('self-account', $routes['GET']['account']['sourceModule']);
            self::assertSame(['auth', 'can:catalogo.write'], $routes['POST']['ajax/catalogo/guardar']['middleware']);
            self::assertSame('catalog-client', $routes['GET']['api/catalogo']['context']['api_consumer']);
            self::assertSame('receive', $routes['POST']['webhook/proveedor']['action']);
            self::assertFalse($routes['GET']['sse/eventos']['refreshSession']);
            self::assertSame('catalogoIndex', \RouteBuilder::inferViewName('catalogo/ProductController', 'index'));
            self::assertSame('catalogo', \RouteBuilder::inferTemplateFromController('catalogo/ProductController'));
            self::assertSame('admin/catalogo', \RouteBuilder::relativePathFromController('admin/catalogo/ProductController'));
        } finally {
            $property->setValue(null, $previous);
        }
    }
}
