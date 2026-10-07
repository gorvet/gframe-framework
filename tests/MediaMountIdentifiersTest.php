<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class MediaMountIdentifiersTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testLibraryAndPickerCanRenderTheSameMediaWithoutDuplicateIds(): void
    {
        define('site_url', 'https://example.test/');
        $root = dirname(__DIR__);
        $html = '';
        foreach (['library', 'picker'] as $fragment) {
            $recent = ['data' => [['media_id' => 7, 'media_url' => 'docs/test.pdf', 'type' => 'docs', 'name' => 'Test']], 'meta' => ['page' => 1, 'total_pages' => 2], 'filters' => ['sources' => [['value' => 'docs', 'label' => 'Docs', 'total' => 1]]]];
            ob_start();
            include $root . '/resources/modules/media-library/application/app/views/media-library/_mlist.php';
            $html .= ob_get_clean();
        }
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $ids = [];
        foreach ((new \DOMXPath($dom))->query('//*[@id]') as $node) {
            $id = $node->getAttribute('id');
            self::assertNotSame('', $id);
            self::assertArrayNotHasKey($id, $ids, 'ID repetido: ' . $id);
            $ids[$id] = true;
        }
        self::assertArrayHasKey('mID_7', $ids);
        self::assertArrayHasKey('mp-media-7', $ids);
        self::assertArrayHasKey('all_items_pagination', $ids);
        self::assertArrayHasKey('mp-all-items-pagination', $ids);
    }
}
