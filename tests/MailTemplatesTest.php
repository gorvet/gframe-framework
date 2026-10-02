<?php

namespace GFrame\Tests;

use GFrame\Mail\MailTemplateRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class MailTemplatesTest extends TestCase
{
    public function testCommonTemplatesRenderWithThemeAndEscapedContent(): void
    {
        $registry = new MailTemplateRegistry(dirname(__DIR__) . '/resources/skeleton/app/views/templates/mail');
        self::assertSame(['contactTemplate', 'mailTemplate'], array_column($registry->all(), 'id'));
        foreach (['contactTemplate', 'mailTemplate'] as $id) {
            $html = $registry->render($id, [
                'title' => 'Prueba', 'h1' => '<script>título</script>',
                'p1' => '{{mailPrimary}}', 'p2' => 'Nota', 'name' => 'Ada',
                'subject' => 'Consulta', 'message' => '<script>contenido</script>',
                'aHref' => 'https://example.test/verify?a=1&b=2', 'aText' => 'Verificar',
                'mailLogo' => 'https://example.test/logo.png', 'mailPrimary' => '#123456',
            ]);
            if ($id === 'contactTemplate') self::assertStringNotContainsString('{{', $html);
            else self::assertStringContainsString('{{mailPrimary}}', $html);
            self::assertStringNotContainsString('<script>', $html);
            self::assertStringNotContainsString('GOlab', $html);
            self::assertStringContainsString('#123456', $html);
            self::assertStringContainsString('https://example.test/logo.png', $html);
            self::assertStringContainsString('Todos los derechos reservados.', $html);
            self::assertStringContainsString('&copy; ' . date('Y'), $html);
            self::assertStringContainsString('Construido con GFrame.', $html);
        }
        self::assertStringContainsString('https://example.test/verify?a=1&amp;b=2', $registry->render('mailTemplate', ['aHref' => 'https://example.test/verify?a=1&b=2']));
    }

    public function testThemeReadsOnlyBaseAndExplicitLightVariables(): void
    {
        $method = new ReflectionMethod(\MailThemeHelper::class, 'extractLightVariables');
        $method->setAccessible(true);
        $variables = $method->invoke(null, <<<'CSS'
/* :root { --bs-primary: invalid; } */
:root[data-bs-theme="light"] { --bs-primary: #123456; }
:root { --bs-primary: #999999; --bs-white: #fff; }
:root[data-bs-theme="dark"] { --bs-primary: #000000; --dark-only: black; }
.card { --bs-white: red; }
@media (prefers-color-scheme: dark) { :root { --bs-primary: black; } }
CSS);
        self::assertSame('#123456', $variables['bs-primary']);
        self::assertSame('#fff', $variables['bs-white']);
        self::assertArrayNotHasKey('dark-only', $variables);
    }

    public function testEveryStandardTemplateContainsGreetingAndFooter(): void
    {
        $root = dirname(__DIR__);
        foreach (['mailTemplate', 'contactTemplate', 'notification'] as $id) {
            $directory = $id === 'notification' ? '/resources/modules/notifications-email/application/mail' : '/resources/skeleton/app/views/templates/mail';
            $html = (new MailTemplateRegistry($root . $directory))->render($id, ['recipient_name' => 'Ana', 'message' => 'Mensaje de prueba.']);
            self::assertStringContainsString('Hola, Ana.', $html, $id);
            self::assertStringContainsString('Todos los derechos reservados.', $html, $id);
        }
        $registry = new MailTemplateRegistry($root . '/resources/modules/notifications-email/application/mail');
        $html = $registry->render('notification', ['user_name' => 'Ana', 'message' => 'Hola, Ana. Tu cuenta ha sido suspendida.']);
        self::assertSame(1, substr_count($html, 'Hola, Ana.'));
        self::assertStringContainsString('display:none;', $html);
        self::assertStringContainsString('Hola, Ana.', $registry->render('notification', ['user_name' => 'Ana', 'message' => 'Mensaje sin saludo.']));
    }

    public function testNotificationIncludesLogoAndFooterOutsideContentCard(): void
    {
        $registry = new MailTemplateRegistry(dirname(__DIR__) . '/resources/modules/notifications-email/application/mail');
        $html = $registry->render('notification', ['title' => 'Aviso', 'message' => '<texto>', 'action_display' => 'none', 'mailLogo' => 'https://example.test/logo.png']);
        self::assertStringContainsString('https://example.test/logo.png', $html);
        self::assertStringContainsString('&lt;texto&gt;', $html);
        self::assertStringNotContainsString('{{', $html);
        self::assertMatchesRegularExpression('~</div>\s*</div>\s*<div[^>]+>.*Todos los derechos reservados.*Construido con GFrame~s', $html);
        self::assertStringContainsString('white-space:pre-wrap', $html);
        self::assertStringContainsString('display:none;', $html);
    }

    public function testAllTemplatesShareTheSameLiveThemeStyles(): void
    {
        $root = dirname(__DIR__);
        $templates = [
            'mailTemplate' => $root . '/resources/skeleton/app/views/templates/mail',
            'contactTemplate' => $root . '/resources/skeleton/app/views/templates/mail',
            'notification' => $root . '/resources/modules/notifications-email/application/mail',
        ];
        $body = null;
        $css = null;
        foreach ($templates as $id => $directory) {
            $html = (new MailTemplateRegistry($directory))->render($id, [
                'mailFont' => 'Arial,sans-serif', 'mailBodyBg' => '#abcdef',
                'mailBodyColor' => '#123456', 'mailPrimary' => '#654321',
                'mailLogo' => 'https://example.test/logo.png',
            ]);
            preg_match('~<body[^>]+>~', $html, $bodyMatch);
            preg_match('~<style[^>]*>(.*?)</style>~s', $html, $cssMatch);
            self::assertNotEmpty($bodyMatch, $id);
            self::assertNotEmpty($cssMatch, $id);
            if ($body === null) { $body = $bodyMatch[0]; $css = $cssMatch[1]; }
            self::assertSame($body, $bodyMatch[0], $id);
            self::assertSame($css, $cssMatch[1], $id);
            self::assertStringContainsString('background:#abcdef', $html);
            self::assertStringContainsString('color:#123456', $html);
            self::assertStringContainsString('height="55"', $html);
        }
    }
}
