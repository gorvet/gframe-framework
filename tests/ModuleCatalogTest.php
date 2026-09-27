<?php

namespace GFrame\Tests;

use GFrame\Modules\ModuleAssetPublisher;
use GFrame\Modules\ModuleCatalog;
use PHPUnit\Framework\TestCase;

final class ModuleCatalogTest extends TestCase
{
    private string $temporaryPath;

    protected function setUp(): void
    {
        $this->temporaryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gframe-modules-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryPath, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryPath);
    }

    public function testFrameworkCatalogContainsAlertsAndItsDependencies(): void
    {
        $modules = ModuleCatalog::frameworkDefault()->resolve(['alerts']);

        self::assertSame(
            ['bootstrap', 'jquery', 'sweetalert2', 'gframe-icons', 'alerts'],
            array_column($modules, 'name')
        );
    }

    public function testDefaultUiIncludesTheRequiredVisualFoundation(): void
    {
        $names = array_column(ModuleCatalog::frameworkDefault()->defaults(), 'name');

        self::assertContains('bootstrap', $names);
        self::assertContains('jquery', $names);
        self::assertContains('sweetalert2', $names);
        self::assertContains('gframe-icons', $names);
        self::assertContains('alerts', $names);
        self::assertContains('frontend-core', $names);
    }

    public function testPublisherCopiesResolvedModuleAssets(): void
    {
        $public = $this->temporaryPath . DIRECTORY_SEPARATOR . 'public';
        $result = (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))
            ->publish(['alerts'], $public);

        self::assertContains('alerts', $result['modules']);
        self::assertFileExists($public . DIRECTORY_SEPARATOR . 'vendors' . DIRECTORY_SEPARATOR . 'internal' . DIRECTORY_SEPARATOR . 'gframe-alerts' . DIRECTORY_SEPARATOR . 'alertToast.js');
        self::assertFileExists($public . DIRECTORY_SEPARATOR . 'vendors' . DIRECTORY_SEPARATOR . 'external' . DIRECTORY_SEPARATOR . 'sweetalert2' . DIRECTORY_SEPARATOR . 'sweetalert2.all.min.js');
    }

    public function testEveryRegisteredAssetExistsInsideItsModule(): void
    {
        foreach (ModuleCatalog::frameworkDefault()->all() as $module) {
            foreach ($module['assets'] as $asset) {
                self::assertFileExists(
                    $module['path'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $asset['source']),
                    'Falta un activo declarado por ' . $module['name']
                );
            }
        }
    }

    public function testEveryRegisteredSchemaExistsInsideItsModule(): void
    {
        foreach (ModuleCatalog::frameworkDefault()->all() as $module) {
            foreach ((array)($module['schemas'] ?? []) as $schema) {
                self::assertFileExists(
                    $module['path'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $schema),
                    'Falta un esquema declarado por ' . $module['name']
                );
            }
        }
    }

    public function testPublisherDoesNotOverwriteExistingFilesUnlessRequested(): void
    {
        $public = $this->temporaryPath . DIRECTORY_SEPARATOR . 'public';
        $publisher = new ModuleAssetPublisher(ModuleCatalog::frameworkDefault());
        $publisher->publish(['password-utils'], $public);

        $target = $public . DIRECTORY_SEPARATOR . 'vendors' . DIRECTORY_SEPARATOR . 'internal' . DIRECTORY_SEPARATOR . 'passwordUtils' . DIRECTORY_SEPARATOR . 'passwordUtils.js';
        file_put_contents($target, 'personalizado');
        $publisher->publish(['password-utils'], $public);
        self::assertSame('personalizado', file_get_contents($target));

        $publisher->publish(['password-utils'], $public, true);
        self::assertNotSame('personalizado', file_get_contents($target));
    }

    public function testProjectPublisherCopiesModuleApplicationFiles(): void
    {
        $project = $this->temporaryPath . DIRECTORY_SEPARATOR . 'project';
        mkdir($project, 0775, true);

        $result = (new ModuleAssetPublisher(ModuleCatalog::frameworkDefault()))
            ->publishProject(['rich-text-editor'], $project);

        self::assertContains('rich-text-editor', $result['modules']);
        self::assertFileExists(
            $project . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views'
            . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'richTextEditor.php'
        );
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $target = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($target) ? $this->removeDirectory($target) : unlink($target);
        }
        rmdir($path);
    }
}
