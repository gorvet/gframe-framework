<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class RichTextEditorTest extends TestCase
{
    private string $modulePath;
    private string $tinyMcePath;

    protected function setUp(): void
    {
        $root = dirname(__DIR__) . '/resources/modules';
        $this->modulePath = $root . '/rich-text-editor';
        $this->tinyMcePath = $root . '/tinymce';
    }

    public function testManifestDeclaresEditorDependenciesAndFiles(): void
    {
        $manifest = require $this->modulePath . '/module.php';

        self::assertSame('rich-text-editor', $manifest['name']);
        self::assertSame(['jquery', 'tinymce'], $manifest['dependencies']);
        self::assertFileExists($this->modulePath . '/application/views/richTextEditor.php');
        self::assertFileExists($this->modulePath . '/application/views/richTextEditor.meta.php');
        self::assertFileExists($this->modulePath . '/public/rich-text-editor.js');
    }

    public function testConfiguredTinyMcePluginsAreBundled(): void
    {
        $javascript = (string)file_get_contents($this->modulePath . '/public/rich-text-editor.js');
        preg_match('/plugins:\s*"([^"]+)"/', $javascript, $matches);
        $plugins = preg_split('/\s+/', trim((string)($matches[1] ?? ''))) ?: [];

        self::assertNotEmpty($plugins);
        foreach ($plugins as $plugin) {
            self::assertDirectoryExists($this->tinyMcePath . '/public/plugins/' . $plugin, $plugin);
            self::assertFileExists($this->tinyMcePath . '/public/plugins/' . $plugin . '/plugin.min.js', $plugin);
        }
    }

    public function testSpanishLanguageThemeAndSkinAreBundled(): void
    {
        self::assertFileExists($this->tinyMcePath . '/public/langs/es.js');
        self::assertFileExists($this->tinyMcePath . '/public/themes/silver/theme.min.js');
        self::assertFileExists($this->tinyMcePath . '/public/skins/ui/oxide/skin.min.css');
        self::assertFileExists($this->tinyMcePath . '/public/icons/default/icons.min.js');
    }

    public function testBundledVersionAndLicenseAreDeclared(): void
    {
        $manifest = require $this->tinyMcePath . '/module.php';
        $package = json_decode((string)file_get_contents($this->tinyMcePath . '/public/package.json'), true);
        $license = (string)file_get_contents($this->tinyMcePath . '/public/license.md');

        self::assertSame('8.6.0', $manifest['version']);
        self::assertSame($manifest['version'], $package['version']);
        self::assertStringContainsString('GNU General Public License', $license);
    }

    public function testComponentEscapesValuesAndNormalizesIdentifiers(): void
    {
        $view = (string)file_get_contents($this->modulePath . '/application/views/richTextEditor.php');

        self::assertGreaterThanOrEqual(5, substr_count($view, 'htmlspecialchars('));
        self::assertStringContainsString("preg_replace('/[^A-Za-z0-9_-]+/'", $view);
        self::assertStringContainsString("'editor-' . \$editorID", $view);
        self::assertStringContainsString('data-editor-min-height', $view);
    }

    public function testClientExposesRegistrationSaveAndLifecycleMethods(): void
    {
        $javascript = (string)file_get_contents($this->modulePath . '/public/rich-text-editor.js');

        self::assertStringContainsString('register: function(editorID, options)', $javascript);
        self::assertStringContainsString('initAll: function(root)', $javascript);
        self::assertStringContainsString('saveAll: function()', $javascript);
        self::assertStringContainsString('destroy: function(elementOrID)', $javascript);
        self::assertStringContainsString('destroyAll: function(root)', $javascript);
        self::assertStringContainsString('valid_elements:', $javascript);
        self::assertStringContainsString('paste_preprocess: pastePreprocess', $javascript);
    }
}
