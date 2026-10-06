<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class JsonLdDocumentationTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testDocumentedExamplesProduceTheirEntities(): void
    {
        define('site_url', 'https://example.com/');
        if (!defined('GFRAME_PATH')) define('GFRAME_PATH', dirname(__DIR__) . '/src/');
        $source = file_get_contents(dirname(__DIR__) . '/docs/json-ld-ejemplos.md');
        preg_match_all('/```php\R(.*?)\R```/s', $source, $matches);
        self::assertCount(6, $matches[1]);
        $expected = ['WebPage', 'BlogPosting', 'Product', 'SoftwareApplication', 'Service', 'Book'];
        foreach ($matches[1] as $index => $code) {
            token_get_all($code, TOKEN_PARSE);
            $config = eval(preg_replace('/^<\?php\s*/', '', $code));
            $schema = (new \SchemaComposer())->compose($config['schema'], $config['metaTags'] ?? [], ['currentURL' => 'https://example.com/pagina']);
            $graph = json_decode((new \JsonLD())->renderSchema($schema, $config['metaTags'] ?? [], ['currentURL' => 'https://example.com/pagina']), true, 512, JSON_THROW_ON_ERROR)['@graph'];
            $types = [];
            foreach ($graph as $node) $types = array_merge($types, (array)$node['@type']);
            self::assertContains($expected[$index], $types);
            if ($index === 3) {
                self::assertContains('FAQPage', $types);
                $app = array_values(array_filter($graph, fn($node) => $node['@type'] === 'SoftwareApplication'))[0];
                self::assertSame('AggregateOffer', $app['offers']['@type']);
                self::assertSame('2', $app['offers']['offerCount']);
                self::assertArrayNotHasKey('aggregateRating', $app);
            }
        }
        $composer = new \SchemaComposer();
        self::assertSame('BlogPosting', $composer->compose(['preset' => 'blog_article'])['type']);
        self::assertSame('BlogPosting', $composer->compose(['preset' => 'blog_article', 'type' => 'BlogPosting'])['type']);
    }
}
