<?php

namespace GFrame\Tests;

use LogicException;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class JsonLdTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testCompositePresetsResolveToTheirExpectedPrimaryType(): void
    {
        $this->defineFrameworkPaths();
        $composer = new \SchemaComposer();

        $contact = $composer->compose(['preset' => 'contact_page']);
        $product = $composer->compose(['preset' => 'product_page']);
        $saas = $composer->compose(['preset' => 'saas_landing']);

        self::assertSame('ContactPage', $contact['type']);
        self::assertSame('Product', $product['type']);
        self::assertArrayHasKey('product', $product);
        self::assertSame('SoftwareApplication', $saas['type']);
        self::assertArrayHasKey('software', $saas);
        self::assertArrayHasKey('faq', $saas);
    }

    #[RunInSeparateProcess]
    public function testComposerDoesNotInventSearchRoute(): void
    {
        $this->defineFrameworkPaths();
        $composer = new \SchemaComposer();

        $schema = $composer->compose(['preset' => 'webpage']);

        self::assertArrayNotHasKey('search', $schema);
    }

    #[RunInSeparateProcess]
    public function testRendererOnlyEmitsExplicitSearchAction(): void
    {
        $this->defineFrameworkPaths();
        define('site_url', 'https://example.test/');
        $renderer = new \JsonLD();

        $withoutSearch = $this->graph($renderer->renderSchema(
            ['type' => 'WebPage'],
            ['title' => 'Inicio'],
            ['currentURL' => 'https://example.test/']
        ));
        self::assertArrayNotHasKey('potentialAction', $this->nodeByType($withoutSearch, 'WebSite'));

        $withSearch = $this->graph($renderer->renderSchema(
            [
                'type' => 'WebPage',
                'search' => ['target' => 'https://example.test/search?q={search_term_string}'],
            ],
            ['title' => 'Inicio'],
            ['currentURL' => 'https://example.test/']
        ));
        self::assertSame(
            'SearchAction',
            $this->nodeByType($withSearch, 'WebSite')['potentialAction']['@type']
        );
    }

    #[RunInSeparateProcess]
    public function testRendererPreservesZeroAndFalseValues(): void
    {
        $this->defineFrameworkPaths();
        define('site_url', 'https://example.test/');
        $renderer = new \JsonLD();

        $productGraph = $this->graph($renderer->renderSchema(
            [
                'type' => 'Product',
                'product' => ['name' => 'Plan gratuito', 'price' => 0, 'currency' => 'USD'],
            ],
            [],
            ['currentURL' => 'https://example.test/product']
        ));
        self::assertSame(0, $this->nodeByType($productGraph, 'Product')['offers']['price']);

        $jobGraph = $this->graph($renderer->renderSchema(
            [
                'type' => 'JobPosting',
                'job' => ['title' => 'Puesto', 'directApply' => false],
            ],
            [],
            ['currentURL' => 'https://example.test/job']
        ));
        self::assertArrayHasKey('directApply', $this->nodeByType($jobGraph, 'JobPosting'));
        self::assertFalse($this->nodeByType($jobGraph, 'JobPosting')['directApply']);
    }

    #[RunInSeparateProcess]
    public function testEmptyAggregateRatingIsNotEmitted(): void
    {
        $this->defineFrameworkPaths();
        define('site_url', 'https://example.test/');
        $renderer = new \JsonLD();

        $graph = $this->graph($renderer->renderSchema(
            [
                'type' => 'SoftwareApplication',
                'software' => [
                    'name' => 'Aplicación',
                    'aggregateRating' => ['ratingValue' => null, 'reviewCount' => null],
                ],
            ],
            [],
            ['currentURL' => 'https://example.test/app']
        ));

        self::assertArrayNotHasKey('aggregateRating', $this->nodeByType($graph, 'SoftwareApplication'));
    }

    #[RunInSeparateProcess]
    public function testCircularPresetReferenceThrowsLogicException(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gframe-jsonld-' . bin2hex(random_bytes(6));
        mkdir($root . DIRECTORY_SEPARATOR . 'seo', 0775, true);
        file_put_contents(
            $root . DIRECTORY_SEPARATOR . 'seo' . DIRECTORY_SEPARATOR . 'schema.presets.php',
            "<?php return ['a' => ['preset' => 'b'], 'b' => ['preset' => 'a']];"
        );
        define('GFRAME_PATH', $root . DIRECTORY_SEPARATOR);
        define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

        try {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('a -> b -> a');
            (new \SchemaComposer())->compose(['preset' => 'a']);
        } finally {
            @unlink($root . DIRECTORY_SEPARATOR . 'seo' . DIRECTORY_SEPARATOR . 'schema.presets.php');
            @rmdir($root . DIRECTORY_SEPARATOR . 'seo');
            @rmdir($root);
        }
    }

    #[RunInSeparateProcess]
    public function testMetaOmitsJsonLdWhenSeoIsDisabled(): void
    {
        define('ABSPATH', __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'meta-project' . DIRECTORY_SEPARATOR);
        define('SEO_ENABLED', false);
        define('SEO_ALLOW_INDEXING', false);

        $meta = new \Meta();
        $meta->setSchema(['preset' => 'webpage']);

        self::assertSame('', $meta->renderSchema());
    }

    private function defineFrameworkPaths(): void
    {
        define('GFRAME_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR);
        define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
    }

    private function graph(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return $decoded['@graph'];
    }

    private function nodeByType(array $graph, string $type): array
    {
        foreach ($graph as $node) {
            $types = (array)($node['@type'] ?? []);
            if (in_array($type, $types, true)) {
                return $node;
            }
        }
        self::fail('No se encontró nodo JSON-LD de tipo ' . $type);
    }
}
