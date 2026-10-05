<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class ContentToolsDocumentationTest extends TestCase
{
    public function testMarkdownExamples(): void
    {
        preg_match_all('/```php\R(.*?)\R```/s', file_get_contents(dirname(__DIR__) . '/docs/markdown.md'), $blocks);
        eval($blocks[1][0]);
        self::assertSame('<h2>Título</h2>', $html);
        self::assertSame('## Título', $texto);
        eval($blocks[1][1]);
        self::assertStringContainsString('<h2>Primeros pasos</h2>', $contentHtml);
        self::assertStringContainsString('<strong>Instala</strong>', $contentHtml);
        self::assertStringContainsString('<ul>', $contentHtml);
        $meta = eval(preg_replace('/^<\?php\s*/', '', $blocks[1][3]));
        self::assertSame('public/vendors/internal/markdown/markdown.js', $meta['js'][0]);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testLexicalSearchExampleAndProjectSubclass(): void
    {
        require_once dirname(__DIR__) . '/resources/modules/lexical-search/application/app/services/lexical-search/LexicalSearchEngine.php';
        preg_match_all('/```php\R(.*?)\R```/s', file_get_contents(dirname(__DIR__) . '/docs/lexical-search.md'), $blocks);
        eval($blocks[1][1]);
        self::assertSame([1], array_column($results, 'id'));
        self::assertSame('Envía avisos a tus usuarios.', $results[0]['snippet']);
        eval(preg_replace('/^<\?php\s*/', '', $blocks[1][2]));
        $custom = new \App\Services\LexicalSearch\LexicalSearchEngine();
        self::assertSame($rows, $custom->searchDocuments($rows, ''));
        self::assertSame([2], array_column($custom->searchDocuments($rows, 'multimedia'), 'id'));
    }
}
