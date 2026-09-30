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
}
