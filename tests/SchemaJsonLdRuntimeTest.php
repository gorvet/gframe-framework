<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SchemaJsonLdRuntimeTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testCompoundPresetsResolveRecursivelyAndSaasKeepsSoftwareType(): void
    {
        define('GFRAME_PATH', dirname(__DIR__) . '/src/');
        define('site_url', 'https://example.test/');

        $composer = new \SchemaComposer();

        $contact = $composer->compose(['preset' => 'contact_page']);
        self::assertSame('ContactPage', $contact['type']);
        self::assertArrayNotHasKey('preset', $contact);
        self::assertArrayNotHasKey('presets', $contact);

        $product = $composer->compose(['preset' => 'product_page']);
        self::assertSame('Product', $product['type']);
        self::assertArrayHasKey('product', $product);

        $saas = $composer->compose(['preset' => 'saas_landing']);
        self::assertSame('SoftwareApplication', $saas['type']);
        self::assertArrayHasKey('software', $saas);
        self::assertArrayHasKey('faq', $saas);
    }

    #[RunInSeparateProcess]
    public function testPresetCyclesAreRejectedInsteadOfRecursingForever(): void
    {
        $root = sys_get_temp_dir() . '/gframe-schema-' . bin2hex(random_bytes(6));
        mkdir($root . '/seo', 0775, true);
        file_put_contents($root . '/seo/schema.presets.php', "<?php return ['a' => ['preset' => 'b'], 'b' => ['preset' => 'a']];");
        define('GFRAME_PATH', $root . '/');

        try {
            $this->expectException(\InvalidArgumentException::class);
            (new \SchemaComposer())->compose(['preset' => 'a']);
        } finally {
            unlink($root . '/seo/schema.presets.php');
            rmdir($root . '/seo');
            rmdir($root);
        }
    }

    #[RunInSeparateProcess]
    public function testSearchActionIsOnlyGeneratedWhenExplicitlyConfigured(): void
    {
        define('GFRAME_PATH', dirname(__DIR__) . '/src/');
        define('site_url', 'https://example.test/');

        $composer = new \SchemaComposer();
        $plain = $composer->compose(['preset' => 'webpage'], ['title' => 'Inicio']);
        self::assertArrayNotHasKey('search', $plain);

        $plainGraph = $this->graph((new \JsonLD())->renderSchema($plain, ['title' => 'Inicio'], ['currentURL' => 'https://example.test/']));
        $website = $this->node($plainGraph, 'WebSite');
        self::assertArrayNotHasKey('potentialAction', $website);

        $search = $composer->compose([
            'preset' => 'webpage',
            'search' => ['target' => 'https://example.test/buscar?q={search_term_string}'],
        ], ['title' => 'Inicio']);
        $searchGraph = $this->graph((new \JsonLD())->renderSchema($search, ['title' => 'Inicio'], ['currentURL' => 'https://example.test/']));
        self::assertSame('SearchAction', $this->node($searchGraph, 'WebSite')['potentialAction']['@type']);
    }

    #[RunInSeparateProcess]
    public function testJsonLdPreservesZeroAndFalseValues(): void
    {
        define('site_url', 'https://example.test/');
        $renderer = new \JsonLD();

        $productGraph = $this->graph($renderer->renderSchema([
            'type' => 'Product',
            'siteName' => 'Ejemplo',
            'title' => 'Producto gratis',
            'product' => ['name' => 'Producto gratis', 'price' => 0, 'currency' => 'USD'],
        ], [], ['currentURL' => 'https://example.test/producto']));
        self::assertSame(0, $this->node($productGraph, 'Product')['offers']['price']);

        $jobGraph = $this->graph($renderer->renderSchema([
            'type' => 'JobPosting',
            'siteName' => 'Ejemplo',
            'job' => ['title' => 'Vacante', 'directApply' => false],
        ], [], ['currentURL' => 'https://example.test/empleo']));
        self::assertFalse($this->node($jobGraph, 'JobPosting')['directApply']);

        $creativeGraph = $this->graph($renderer->renderSchema([
            'type' => 'CreativeWork',
            'siteName' => 'Ejemplo',
            'creativeWork' => ['name' => 'Obra', 'isAccessibleForFree' => false],
        ], [], ['currentURL' => 'https://example.test/obra']));
        self::assertFalse($this->node($creativeGraph, 'CreativeWork')['isAccessibleForFree']);
    }

    #[RunInSeparateProcess]
    public function testSoftwareAggregateDoesNotInventZeroPriceForUnpricedPlans(): void
    {
        define('site_url', 'https://example.test/');
        $graph = $this->graph((new \JsonLD())->renderSchema([
            'type' => 'SoftwareApplication',
            'siteName' => 'Ejemplo',
            'software' => [
                'name' => 'App',
                'offers' => [
                    ['name' => 'Consultar'],
                    ['name' => 'Pro', 'price' => 10, 'currency' => 'USD'],
                ],
            ],
        ], [], ['currentURL' => 'https://example.test/app']));

        $offers = $this->node($graph, 'SoftwareApplication')['offers'];
        self::assertSame('10', $offers['lowPrice']);
        self::assertSame('10', $offers['highPrice']);
        self::assertSame('2', $offers['offerCount']);
    }

    #[RunInSeparateProcess]
    public function testSeoDisabledSuppressesJsonLd(): void
    {
        $root = sys_get_temp_dir() . '/gframe-meta-' . bin2hex(random_bytes(6));
        mkdir($root, 0775, true);
        define('ABSPATH', $root . '/');
        define('SEO_ENABLED', false);
        define('SEO_ALLOW_INDEXING', false);
        define('site_url', 'https://example.test/');

        try {
            $meta = new \Meta();
            $meta->setSchema(['preset' => 'webpage']);
            self::assertSame('', $meta->renderSchema());
        } finally {
            rmdir($root);
        }
    }

    private function graph(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return (array)($decoded['@graph'] ?? []);
    }

    private function node(array $graph, string $type): array
    {
        foreach ($graph as $node) {
            $types = (array)($node['@type'] ?? []);
            if (in_array($type, $types, true)) {
                return (array)$node;
            }
        }
        self::fail('No se encontró el nodo JSON-LD ' . $type);
    }
}
