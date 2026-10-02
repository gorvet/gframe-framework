<?php

namespace GFrame\Tests;

use GFrame\Modules\ModuleCatalog;
use GFrame\Modules\ModuleRuntime;
use GFrame\Modules\ModuleAssetPublisher;
use GFrame\Modules\LexicalSearch\Services\LexicalSearchEngine;
use PHPUnit\Framework\TestCase;

final class LexicalSearchTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/gframe-lexical-' . bin2hex(random_bytes(6));
        mkdir($this->root);
        ModuleRuntime::initialize(ModuleCatalog::frameworkDefault(), ['lexical-search'], $this->root);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function testNormalizesAccentsAndRanksExactAndApproximateMatches(): void
    {
        $engine = new LexicalSearchEngine();
        self::assertSame('gestion de campanas', $engine->normalize(' Gestión de campañas! '));
        $rows = [['id'=>1, 'title'=>'Campañas'], ['id'=>2, 'title'=>'Campanas'], ['id'=>3, 'title'=>'Factura']];
        $result = $engine->rank($rows, 'campañas', ['title'=>1]);
        self::assertSame([1, 2], array_column($result, 'id'));
        self::assertNotEmpty($engine->rank($rows, 'campañs', ['title'=>1]));
        self::assertSame([], $engine->rank($rows, 'inexistente', ['title'=>1]));
        self::assertSame([], $engine->rank($rows, '', ['title'=>1]));
    }

    public function testPreservesWeightsFiltersBoostsAndPlainTextSnippets(): void
    {
        $rows = [['id'=>1, 'title'=>'Contrato', 'body'=>'Texto'], ['id'=>2, 'title'=>'Texto', 'body'=>'Contrato'], ['id'=>3, 'title'=>'Contrato', 'body'=>'<p>Contrato privado</p>']];
        $engine = new LexicalSearchEngine();
        $options = ['filter'=>fn($row)=>$row['id']!==3, 'snippet_fields'=>['body']];
        self::assertSame([1, 2], array_column($engine->rank($rows, 'contrato', ['title'=>2, 'body'=>1], $options), 'id'));
        $options['boost'] = fn($row)=>$row['id']===2 ? 10 : 0;
        self::assertSame(2, $engine->rank($rows, 'contrato', ['title'=>2, 'body'=>1], $options)[0]['id']);
        $result = $engine->rank([$rows[2]], 'contrato', ['body'=>1], ['snippet_fields'=>['body']]);
        self::assertSame('Contrato privado', $result[0]['snippet']);
    }

    public function testPublishesAssetsAndLeavesServiceCustomizationEmpty(): void
    {
        (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))->publishProject(['lexical-search'], $this->root);
        self::assertFileExists($this->root . '/public/vendors/internal/lexical-search/lexical-search.js');
        self::assertDirectoryExists($this->root . '/app/services/lexical-search');
        self::assertSame(['.', '..'], scandir($this->root . '/app/services/lexical-search'));
        self::assertFalse((new \ReflectionClass(LexicalSearchEngine::class))->isFinal());
        file_put_contents($this->root . '/app/services/lexical-search/LexicalSearchEngine.php', '<?php namespace App\\Services\\LexicalSearch; class LexicalSearchEngine extends \\GFrame\\Modules\\LexicalSearch\\Services\\LexicalSearchEngine { public function normalize(string $text): string { return parent::normalize($text) . " custom"; } }');
        $custom = new \App\Services\LexicalSearch\LexicalSearchEngine();
        self::assertSame('gestion custom', $custom->normalize('Gestión'));
    }
}
