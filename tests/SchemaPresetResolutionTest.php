<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SchemaPresetResolutionTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testAllBuiltInPresetsResolveWithoutCycles(): void
    {
        define('site_url', 'https://example.test/');
        if (!defined('GFRAME_PATH')) define('GFRAME_PATH', dirname(__DIR__) . '/src/');
        $catalog = require dirname(__DIR__) . '/src/seo/schema.presets.php';
        $composer = new \SchemaComposer();
        foreach (array_keys($catalog) as $name) {
            $schema = $composer->compose(['preset' => $name, 'title' => 'Ejemplo']);
            self::assertArrayNotHasKey('preset', $schema, $name);
            self::assertArrayNotHasKey('presets', $schema, $name);
            self::assertNotEmpty($schema['type'], $name);
            json_decode((new \JsonLD())->renderSchema($schema, [], []), true, 512, JSON_THROW_ON_ERROR);
        }
        foreach (['blog_article' => 'BlogPosting', 'news_article' => 'NewsArticle', 'tech_article' => 'TechArticle', 'contact_page' => 'ContactPage', 'product_page' => 'Product', 'saas_landing' => 'SoftwareApplication'] as $preset => $type) {
            self::assertSame($type, $composer->compose(['preset' => $preset])['type']);
        }
        self::assertSame('Article', $composer->compose(['preset' => 'blog_article', 'type' => 'Article'])['type']);
    }

    #[RunInSeparateProcess]
    public function testFaqComplementsTypesAndNestedPresetsWithoutReplacingThem(): void
    {
        define('GFRAME_PATH', dirname(__DIR__) . '/src/');
        $composer = new \SchemaComposer();
        self::assertSame('WebPage', $composer->compose(['preset' => 'faq'])['type']);
        self::assertSame('WebPage', $composer->compose(['preset' => 'faq_page'])['type']);
        foreach (['software' => 'SoftwareApplication', 'product_page' => 'Product', 'news_article' => 'NewsArticle'] as $preset => $type) {
            $schema = $composer->compose(['presets' => [$preset, 'faq']]);
            self::assertSame($type, $schema['type']);
            self::assertArrayHasKey('faq', $schema);
        }
        $product = $composer->compose(['presets' => ['product_page', 'faq'], 'product' => ['name' => 'Final']]);
        self::assertSame('Final', $product['product']['name']);
        self::assertSame('USD', $product['product']['currency']);
    }
}
