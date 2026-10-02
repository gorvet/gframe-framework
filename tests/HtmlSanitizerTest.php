<?php

namespace GFrame\Tests;

use GFrame\Security\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    public static function unsafeContent(): array
    {
        return [
            ['<p onclick="alert(1)" style="color:red">Hola</p>', '<p>Hola</p>'],
            ['<script>alert(1)</script><p>Bien</p>', '<p>Bien</p>'],
            ['<iframe src="https://example.test"></iframe><p>Bien</p>', '<p>Bien</p>'],
            ['<a href="javascript:alert(1)">Abrir</a>', '<a>Abrir</a>'],
            ['<a href="jav&#x61;script:alert(1)">Abrir</a>', '<a>Abrir</a>'],
            ['<img src="data:image/svg+xml;base64,abc" onerror="alert(1)">', ''],
            ['<img src="javascript:alert(1)">', ''],
            ['<svg onload="alert(1)"><script>alert(1)</script></svg>', ''],
            ['<p><strong>Contraseña y acción</strong></p>', '<p><strong>Contraseña y acción</strong></p>'],
        ];
    }

    #[DataProvider('unsafeContent')]
    public function testSanitizesUntrustedHtml(string $input, string $expected): void
    {
        self::assertSame($expected, HtmlSanitizer::sanitize($input));
    }

    public function testPreservesSafeRichContentAndRestoresLibxmlState(): void
    {
        $previous = libxml_use_internal_errors(false);
        try {
            $html = HtmlSanitizer::sanitize('<h2>Título</h2><ul><li>Uno</li></ul><table><tr><td colspan="2">Dos</td></tr></table><a href="https://example.test" target="_blank" onclick="evil()">Abrir</a><img src="uploads/library/image.png" alt="Foto" width="150">');
            foreach (['<h2>Título</h2>', '<ul><li>Uno</li></ul>', 'colspan="2"', 'rel="noopener noreferrer"', 'target="_blank"', 'src="uploads/library/image.png"'] as $fragment) self::assertStringContainsString($fragment, $html);
            self::assertStringNotContainsString('onclick', $html);
            self::assertFalse(libxml_use_internal_errors());
            self::assertSame('', HtmlSanitizer::sanitize('  '));
        } finally { libxml_use_internal_errors($previous); }
    }

    public function testMissingDomFailsClosedInsteadOfKeepingDangerousAttributes(): void
    {
        // Simula DOM ausente incluso en PHP con la extensión compilada.
        $code = 'namespace GFrame\\Security; function class_exists(string $name): bool { return false; } require ' . var_export(dirname(__DIR__) . '/src/GFrame/Security/HtmlSanitizer.php', true) . '; try { \\GFrame\\Security\\HtmlSanitizer::sanitize("<p onclick=evil()>Hola</p>"); exit(1); } catch (\\RuntimeException $error) { echo "closed"; }';
        $process = proc_open([PHP_BINARY, '-n', '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error);
        self::assertSame('closed', $out);
    }
}
