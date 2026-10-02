<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErrorPagesTest extends TestCase
{
    public function testErrorCodeUsesNeutralThemeColor(): void
    {
        $css = (string)file_get_contents(dirname(__DIR__) . '/resources/modules/error-pages/public/404.css');
        self::assertMatchesRegularExpression('/\.error-code\s*\{\s*color:\s*var\(--bs-gray-800\)/', $css);
    }

    public static function setUpBeforeClass(): void
    {
        if (!defined('site_url')) {
            define('site_url', 'https://example.test/app');
        }
        if (!defined('site_name')) {
            define('site_name', 'Aplicación de prueba');
        }
        if (!defined('APP_LANG')) {
            define('APP_LANG', 'es');
        }
        if (!defined('DebugMode')) {
            define('DebugMode', false);
        }
    }

    public static function routeCases(): array
    {
        return [
            'permiso' => ['permission', 'errorPermissions', 403],
            'prohibido' => ['403', 'error403', 403],
            'no encontrado' => ['badController', 'error404', 404],
            'error interno' => ['500', 'error500', 500],
            'servicio no disponible' => ['2002', 'error503', 503],
        ];
    }

    #[DataProvider('routeCases')]
    public function testBuildsTheExpectedWebErrorRoute(string $code, string $view, int $httpCode): void
    {
        $route = (new \ErrorResponder())->buildRouteParams($code, [
            'method' => 'GET',
            'currentURL' => 'https://example.test/app/admin/users/42',
        ]);

        self::assertSame('error/ErrorResponder', $route['controller']);
        self::assertSame('error', $route['templateName']);
        self::assertSame($view, $route['view']);
        self::assertSame($httpCode, $route['httpCode']);
        self::assertTrue($route['skipAction']);
        self::assertSame('https://example.test/app/admin/users', $route['params']['tolink']);
    }

    public function testPreservesErrorHelpAndContext(): void
    {
        $route = (new \ErrorResponder())->buildRouteParams('permission', [
            'tolink' => 'https://example.test/app/admin',
            'infoMsg' => 'No puedes editar este registro.',
            'helpMsg' => 'Solicita acceso al administrador.',
            'helpUrl' => 'https://example.test/app/ayuda',
            'helpLabel' => 'Consultar ayuda',
            'helpEnabled' => false,
            'context' => ['resource' => 'users'],
        ]);

        self::assertSame('https://example.test/app/admin', $route['params']['tolink']);
        self::assertSame('No puedes editar este registro.', $route['params']['infoMsg']);
        self::assertSame('Solicita acceso al administrador.', $route['params']['helpMsg']);
        self::assertSame('https://example.test/app/ayuda', $route['params']['helpUrl']);
        self::assertSame('Consultar ayuda', $route['params']['helpLabel']);
        self::assertFalse($route['params']['helpEnabled']);
        self::assertSame(['resource' => 'users'], $route['context']);
    }

    public function testRejectsAnExternalRefererAsTheReturnUrl(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://malicious.example/phishing';

        try {
            $route = (new \ErrorResponder())->buildRouteParams('404');
            self::assertSame(site_url, $route['params']['tolink']);
        } finally {
            unset($_SERVER['HTTP_REFERER']);
        }
    }

    public function testRouterPreservesWebErrorHelpReturnLinkAndContext(): void
    {
        $router = new \Router();
        (new \ReflectionProperty(\Router::class, 'intendedType'))->setValue($router, 'web');
        $method = new \ReflectionMethod(\Router::class, 'processErrorResponse');
        $route = $method->invoke($router, [
            'status' => 'error', 'code' => 'permission', 'message' => 'Detalle privado',
            'tolink' => 'https://example.test/app/admin',
            'helpMsg' => 'Solicita acceso.', 'helpUrl' => 'https://example.test/app/ayuda',
            'helpLabel' => 'Consultar ayuda', 'helpEnabled' => false,
            'context' => ['resource' => 'users'],
        ], ['type' => 'web', 'method' => 'GET', 'currentURL' => 'https://example.test/app/admin/users']);

        self::assertSame('errorPermissions', $route['view']);
        self::assertSame('', $route['params']['infoMsg']);
        self::assertSame('https://example.test/app/admin', $route['params']['tolink']);
        self::assertSame('Solicita acceso.', $route['params']['helpMsg']);
        self::assertSame('https://example.test/app/ayuda', $route['params']['helpUrl']);
        self::assertSame('Consultar ayuda', $route['params']['helpLabel']);
        self::assertFalse($route['params']['helpEnabled']);
        self::assertSame(['resource' => 'users'], $route['context']);
    }

    public function testOriginalTemplateCardStylesAndHelpOptionsArePublishedTogether(): void
    {
        $module = dirname(__DIR__) . '/resources/modules/error-pages/';
        $template = (string)file_get_contents($module . 'application/app/views/templates/errorTemplate.php');
        $css = (string)file_get_contents($module . 'public/404.css');
        self::assertStringContainsString('class="error-page"', $template);
        self::assertStringContainsString('class="error-brand"', $template);
        self::assertStringContainsString("'public/img/logo.png'", $template);
        self::assertStringNotContainsString("'public/img/favicon.png'", $template);
        self::assertStringContainsString('body.tpl-error #footer', $css);
        self::assertStringContainsString('.error-card', $css);
        self::assertStringContainsString('.error-brand', $css);
        self::assertStringContainsString('background-color: var(--bs-gray-100);', $css);
        self::assertStringNotContainsString('background-image:', $css);
        self::assertStringNotContainsString('brightness(0) invert(1)', $css);

        $routeParams = ['params' => ['helpEnabled' => false, 'tolink' => site_url]];
        ob_start();
        require $module . 'application/app/views/error-pages/error404.php';
        $html = (string)ob_get_clean();
        self::assertStringContainsString('aria-labelledby="errorTitle"', $html);
        self::assertStringContainsString('class="error-title"', $html);
        self::assertStringNotContainsString('terminos-uso#contacto', $html);
        self::assertStringNotContainsString('class="error-help', $html);
    }

    public static function transportCases(): array
    {
        return [
            'ajax' => ['ajax', 200, 'application/json', 'internal_error'],
            'system' => ['system', 200, 'application/json', 'internal_error'],
            'api' => ['api', 500, 'application/json', '"http_code":500'],
            'webhook' => ['webhook', 500, 'text/plain', 'Internal server error'],
            'sse' => ['sse', 500, 'text/event-stream', 'event: error'],
        ];
    }

    #[DataProvider('transportCases')]
    public function testNonWebChannelsUseStableResponses(
        string $channel,
        int $httpCode,
        string $contentType,
        string $expectedOutput
    ): void {
        $method = new \ReflectionMethod(\ErrorHandler::class, 'respondForNonWeb');
        http_response_code(200);

        ob_start();
        $method->invoke(null, $channel, new \RuntimeException('dato sensible'));
        $output = (string)ob_get_clean();

        self::assertSame($httpCode, http_response_code());
        self::assertStringContainsString($expectedOutput, $output);
        self::assertStringNotContainsString('dato sensible', $output);

        $headers = headers_list();
        if ($headers !== []) {
            self::assertNotEmpty(array_filter(
                $headers,
                static fn(string $header): bool => str_contains(strtolower($header), strtolower($contentType))
            ));
        }
    }

    public function testPublishedViewsRenderEscapedMessagesAndExpectedActions(): void
    {
        $routeParams = [
            'params' => [
                'infoMsg' => '<script>alert(1)</script>',
                'helpMsg' => 'Consulta la documentación.',
                'helpUrl' => 'https://example.test/app/ayuda',
                'helpLabel' => 'Abrir ayuda',
                'tolink' => 'https://example.test/app/admin',
            ],
        ];

        ob_start();
        require dirname(__DIR__) . '/resources/modules/error-pages/application/app/views/error-pages/error403.php';
        $html = (string)ob_get_clean();

        self::assertStringContainsString('Acceso denegado', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('Abrir ayuda', $html);
        self::assertStringContainsString('https://example.test/app/admin', $html);
    }

    public function testDefault404IsConciseAndFooterUsesTheAuthSpacingFix(): void
    {
        $routeParams = ['params' => []];
        ob_start();
        require dirname(__DIR__) . '/resources/modules/error-pages/application/app/views/error-pages/error404.php';
        $html = (string)ob_get_clean();
        self::assertStringContainsString('Página no encontrada', $html);
        self::assertStringContainsString('Volver al inicio', $html);
        self::assertStringContainsString('La dirección puede ser incorrecta o el contenido ya no está disponible.', $html);
        self::assertStringNotContainsString('class="error-help', $html);
        self::assertStringNotContainsString('terminos-uso#contacto', $html);
        $css = (string)file_get_contents(dirname(__DIR__) . '/resources/modules/error-pages/public/404.css');
        $common = (string)file_get_contents(dirname(__DIR__) . '/resources/skeleton/public/css/common.css');
        self::assertStringContainsString('#footer.gframe-footer .copyright .container,', $common);
        self::assertStringContainsString('#footer.gframe-footer .credits .container {', $common);
        self::assertStringContainsString('padding-bottom: 0 !important;', $common);
        self::assertStringNotContainsString('#footer .copyright', $css);
        $auth = (string)file_get_contents(dirname(__DIR__) . '/resources/modules/auth-ui/public/auth.css');
        self::assertStringNotContainsString('#footer .copyright', $auth);
    }
}
