<?php

namespace GFrame\Tests;

use GFrame\Modules\ModuleCatalog;
use MarkdownHelper;
use PHPUnit\Framework\TestCase;

final class MarkdownTest extends TestCase
{
    public function testComponentHasFrontendAndBackend(): void
    {
        $module = ModuleCatalog::frameworkDefault()->get('markdown');
        self::assertFalse($module['default']);
        self::assertFileExists($module['path'] . '/src/MarkdownHelper.php');
        self::assertFileExists($module['path'] . '/public/markdown.js');
        self::assertStringNotContainsString('utils/markdown.js', (string)file_get_contents(dirname(__DIR__) . '/resources/modules/frontend-core/public/utils.js'));
    }

    public function testChannelConversionEscapesHtmlAndFormatsText(): void
    {
        $html = MarkdownHelper::channelMarkdownToHtml('*Texto* <script>alert(1)</script>');
        self::assertStringContainsString('<strong>Texto</strong>', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }
}
