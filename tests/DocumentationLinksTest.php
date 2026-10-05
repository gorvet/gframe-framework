<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class DocumentationLinksTest extends TestCase
{
    public function testCommandGuideUsesExistingRepositoryScripts(): void
    {
        $root = dirname(__DIR__);
        $composer = json_decode(file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $guide = file_get_contents($root . '/docs/comandos.md');
        foreach (['new', 'modules:list', 'lint', 'test', 'skills:check', 'check'] as $script) {
            self::assertArrayHasKey($script, $composer['scripts']);
            self::assertStringContainsString('composer ' . $script, $guide);
        }
        foreach (glob($root . '/docs/*.md') as $file) {
            self::assertStringNotContainsString('bin/modules.php publish-project', file_get_contents($file), basename($file));
        }
    }

    public function testFirstPageTutorialExamplesHaveValidPhpSyntax(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/docs/primer-proyecto.md');
        preg_match_all('/```php\s*\n(.*?)\n```/s', $source, $blocks);
        self::assertGreaterThanOrEqual(4, count($blocks[1]));
        foreach ($blocks[1] as $code) {
            self::assertNotEmpty(token_get_all(str_starts_with(trim($code), '<?php') ? $code : '<?php ' . $code, TOKEN_PARSE));
        }
    }

    public function testRelativeMarkdownLinksResolveToExistingFiles(): void
    {
        $directory = dirname(__DIR__) . '/docs';
        $broken = [];
        $checked = 0;
        foreach (glob($directory . '/*.md') as $file) {
            $text = preg_replace('/```.*?```/s', '', file_get_contents($file));
            preg_match_all('/(?<!!)\[[^\]]+\]\(([^\s)]+)\)/u', $text, $links);
            foreach ($links[1] as $link) {
                if (preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/|#)/i', $link)) continue;
                $path = rawurldecode(explode('#', $link, 2)[0]);
                if ($path === '') continue;
                $checked++;
                if (!is_file(dirname($file) . '/' . $path)) $broken[] = basename($file) . ': ' . $link;
            }
        }
        self::assertGreaterThan(100, $checked);
        self::assertSame([], $broken, implode("\n", $broken));
    }

    public function testRelativeDocumentationAnchorsMatchRenderedHeadings(): void
    {
        $broken = [];
        foreach (glob(dirname(__DIR__) . '/docs/*.md') as $file) {
            $text = preg_replace('/```.*?```/s', '', file_get_contents($file));
            preg_match_all('/(?<!!)\[[^\]]+\]\(([^\s)]+)\)/u', $text, $links);
            foreach ($links[1] as $link) {
                if (preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/)/i', $link) || !str_contains($link, '#')) continue;
                [$path, $anchor] = explode('#', $link, 2);
                $target = $path === '' ? $file : dirname($file) . '/' . rawurldecode($path);
                if (!is_file($target) || !str_ends_with($target, '.md')) continue;
                preg_match_all('/^#{2,3}\s+(.+)$/mu', file_get_contents($target), $headings);
                $ids = array_map(static fn(string $heading): string => trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($heading)), '-'), $headings[1]);
                if (!in_array(rawurldecode($anchor), $ids, true)) $broken[] = basename($file) . ': ' . $link;
            }
        }
        self::assertSame([], $broken, implode("\n", $broken));
    }
}
