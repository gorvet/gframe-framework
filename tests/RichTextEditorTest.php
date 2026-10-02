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
        self::assertFileExists($this->modulePath . '/application/app/views/rich-text-editor/richTextEditor.php');
        self::assertFileExists($this->modulePath . '/application/app/views/rich-text-editor/richTextEditor.meta.php');
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
        $view = (string)file_get_contents($this->modulePath . '/application/app/views/rich-text-editor/richTextEditor.php');

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

    public function testNativeComponentAndProjectOverrideArePreservedByUpdate(): void
    {
        $root = sys_get_temp_dir() . '/gframe-rich-editor-' . bin2hex(random_bytes(6));
        mkdir($root);
        $catalog = \GFrame\Modules\ModuleCatalog::frameworkDefault();
        try {
            (new \GFrame\Modules\ModuleAssetPublisher($catalog))->publishProject(['rich-text-editor'], $root);
            \GFrame\Modules\ModuleRuntime::initialize($catalog, ['rich-text-editor'], $root);
            $native = \GFrame\Modules\ModuleRuntime::file('views', 'rich-text-editor/richTextEditor.php', 'rich-text-editor');
            self::assertNotNull($native);
            $richTextEditor = ['id'=>'2 test', 'value'=>'<script>evil()</script>', 'label'=>'Texto'];
            ob_start();
            include $native;
            $html = ob_get_clean();
            self::assertStringContainsString('id="editor-2-test"', $html);
            self::assertStringContainsString('&lt;script&gt;evil()&lt;/script&gt;', $html);
            file_put_contents($root . '/app/views/rich-text-editor/richTextEditor.php', '<p>custom-editor</p>');
            file_put_contents($root . '/app/views/rich-text-editor/richTextEditor.meta.php', '<?php return ["js"=>["public/custom.js"]];');
            \GFrame\Install\ProjectUpdateService::frameworkDefault()->update($root, ['rich-text-editor']);
            ob_start();
            include \GFrame\Modules\ModuleRuntime::file('views', 'rich-text-editor/richTextEditor.php', 'rich-text-editor');
            self::assertSame('<p>custom-editor</p>', ob_get_clean());
            self::assertSame(['js'=>['public/custom.js']], require \GFrame\Modules\ModuleRuntime::file('views', 'rich-text-editor/richTextEditor.meta.php', 'rich-text-editor'));
            self::assertFileDoesNotExist($root . '/app/views/admin/components/richTextEditor.php');
        } finally {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }
}
