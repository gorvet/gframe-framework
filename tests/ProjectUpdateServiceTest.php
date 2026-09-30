<?php

namespace GFrame\Tests;

use GFrame\Install\MigrationRunner;
use GFrame\Install\ProjectUpdateService;
use GFrame\Modules\ModuleCatalog;
use PDO;
use PHPUnit\Framework\TestCase;

final class ProjectUpdateServiceTest extends TestCase
{
    private string $temporaryPath;

    protected function setUp(): void
    {
        $this->temporaryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gframe-update-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryPath, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryPath);
    }

    public function testUpdaterAddsFilesUpdatesManagedFilesAndOverwritesCoreCustomizations(): void
    {
        $modulesPath = $this->temporaryPath . '/modules';
        $modulePath = $modulesPath . '/demo';
        $project = $this->temporaryPath . '/project';
        mkdir($modulePath . '/public', 0775, true);
        mkdir($project . '/public', 0775, true);
        file_put_contents($modulePath . '/public/demo.js', 'version-1');
        file_put_contents($modulePath . '/module.php', "<?php return ['name'=>'demo','assets'=>[['source'=>'public','target'=>'js/demo']]];");
        $catalog = new ModuleCatalog($modulesPath);
        $updater = new ProjectUpdateService($catalog, new MigrationRunner($catalog));

        $first = $updater->update($project, ['demo'], null, '', false, false);
        self::assertSame(['public/js/demo/demo.js'], $first['added']);
        self::assertSame('version-1', file_get_contents($project . '/public/js/demo/demo.js'));

        file_put_contents($modulePath . '/public/demo.js', 'version-2');
        $second = $updater->update($project, [], null, '', false, false);
        self::assertSame(['public/js/demo/demo.js'], $second['updated']);
        self::assertSame('version-2', file_get_contents($project . '/public/js/demo/demo.js'));

        file_put_contents($project . '/public/js/demo/demo.js', 'personalizado');
        file_put_contents($modulePath . '/public/demo.js', 'version-3');
        $third = $updater->update($project, [], null, '', false, false);
        self::assertSame(['public/js/demo/demo.js'], $third['overwritten_custom']);
        self::assertSame('version-3', file_get_contents($project . '/public/js/demo/demo.js'));

        file_put_contents($project . '/public/js/demo/demo.js', 'personalizado-2');
        file_put_contents($modulePath . '/public/demo.js', 'version-4');
        $protected = $updater->update($project, [], null, '', true, false);
        self::assertSame(['public/js/demo/demo.js'], $protected['conflicts']);
        self::assertSame('personalizado-2', file_get_contents($project . '/public/js/demo/demo.js'));
    }

    public function testMigrationRunnerAppliesEachMigrationOnlyOnce(): void
    {
        $modulesPath = $this->temporaryPath . '/modules';
        $modulePath = $modulesPath . '/demo';
        mkdir($modulePath . '/migrations', 0775, true);
        file_put_contents($modulePath . '/migrations/001.sql', 'ALTER TABLE sample ADD COLUMN label TEXT NULL;');
        file_put_contents($modulePath . '/module.php', "<?php return ['name'=>'demo','migrations'=>['sqlite'=>['migrations/001.sql']]];");
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE sample (id INTEGER PRIMARY KEY)');
        $runner = new MigrationRunner(new ModuleCatalog($modulesPath));

        self::assertSame(1, $runner->pending($pdo, 'sqlite', ['demo'])['count']);
        self::assertSame(1, $runner->migrate($pdo, 'sqlite', ['demo'])['count']);
        self::assertSame(0, $runner->pending($pdo, 'sqlite', ['demo'])['count']);
        self::assertSame(0, $runner->migrate($pdo, 'sqlite', ['demo'])['count']);
        $columns = $pdo->query('PRAGMA table_info(sample)')->fetchAll(PDO::FETCH_ASSOC);
        self::assertContains('label', array_column($columns, 'name'));
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) return;
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
            $child = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($child) ? $this->removeDirectory($child) : unlink($child);
        }
        rmdir($path);
    }
}
