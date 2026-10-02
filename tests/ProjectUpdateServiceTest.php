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

    public function testUninstalledProjectUpdatesInstallerWithoutConfigurationOrLock(): void
    {
        $project = $this->temporaryPath . '/project';
        mkdir($project);
        file_put_contents($project . '/install.php', 'old installer');
        $updater = ProjectUpdateService::frameworkDefault();
        $preview = $updater->updateInstaller($project, false, true);
        self::assertContains('install.php', $preview['updated']);
        self::assertSame('old installer', file_get_contents($project . '/install.php'));
        $preserved = $updater->updateInstaller($project, true);
        self::assertContains('install.php', $preserved['conflicts']);
        $result = $updater->updateInstaller($project);
        self::assertSame('installer_updated', $result['code']);
        self::assertSame([], $result['modules']);
        self::assertFileDoesNotExist($project . '/config/app.php');
        self::assertFileExists($project . '/deployment/nginx.conf');
        self::assertFileDoesNotExist($project . '/config/server/nginx.conf');
        self::assertFileDoesNotExist($project . '/storage/gframe-installed.json');
        self::assertFileDoesNotExist($project . '/.env');
        self::assertDirectoryDoesNotExist($project . '/app/controllers');
        self::assertSame(file_get_contents(dirname(__DIR__) . '/resources/skeleton/core/Load.php'), file_get_contents($project . '/core/Load.php'));
        $command = [PHP_BINARY, dirname(__DIR__) . '/bin/gframe-update', '--project=' . $project];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error);
        self::assertStringContainsString('El proyecto sigue pendiente de instalación.', $output);
        self::assertFileDoesNotExist($project . '/storage/gframe-installed.json');
    }

    public function testInstallerOnlyUpdateRejectsConfiguredProjects(): void
    {
        $project = $this->temporaryPath . '/project';
        mkdir($project . '/config', 0775, true);
        file_put_contents($project . '/install.php', 'original');
        file_put_contents($project . '/config/app.php', '<?php return [];');
        $this->expectException(\RuntimeException::class);
        ProjectUpdateService::frameworkDefault()->updateInstaller($project);
    }

    public function testFrameworkUpdatePublishesGlobalMetaTemplatesAndSharedCss(): void
    {
        $project = $this->temporaryPath . '/project';
        mkdir($project, 0775, true);
        $updater = ProjectUpdateService::frameworkDefault();
        $files = [
            'config/meta/global.meta.php', 'app/views/templates/header.php',
            'app/views/templates/footer.php', 'public/css/variables.css',
            'public/css/bootstrap-buttons-compat.css', 'public/css/common.css',
            'app/views/home/homeIndex.php', 'app/views/home/home.group.meta.php',
            'app/views/templates/homeTemplate.php', 'public/css/home/home.css',
            'app/views/templates/mail/mailTemplate.html',
            'app/views/templates/mail/contactTemplate.html',
            '.htaccess', 'index.php', 'install.php', 'core/Load.php',
            'public/css/colores.html', 'public/js/app/home/mngnoadmin.js',
        ];
        $preview = $updater->update($project, ['error-pages'], null, '', false, true);
        foreach ($files as $file) {
            self::assertContains($file, $preview['added']);
            self::assertFileDoesNotExist($project . '/' . $file);
        }
        $result = $updater->update($project, ['error-pages']);
        foreach ($files as $file) {
            self::assertContains($file, $result['added']);
            self::assertSame(file_get_contents(dirname(__DIR__) . '/resources/skeleton/' . $file), file_get_contents($project . '/' . $file));
        }
        self::assertFileExists($project . '/public/css/404/404.css');
        self::assertFileExists($project . '/public/vendors/external/bootstrap/css/bootstrap.min.css');
        file_put_contents($project . '/config/meta/global.meta.php', '<?php return [];');
        $protected = $updater->update($project, [], null, '', true);
        self::assertContains('config/meta/global.meta.php', $protected['conflicts']);
        self::assertSame('<?php return [];', file_get_contents($project . '/config/meta/global.meta.php'));
    }

    public function testMailTemplatesReplaceOldPublishedFilesAndRespectPreserveCustom(): void
    {
        $project = $this->temporaryPath . '/project';
        mkdir($project . '/app/views/templates/mail', 0775, true);
        $relative = 'app/views/templates/mail/mailTemplate.html';
        file_put_contents($project . '/' . $relative, '<p>Plantilla antigua</p>');
        $updater = ProjectUpdateService::frameworkDefault();
        $preview = $updater->update($project, ['notifications-email'], null, '', false, true);
        self::assertContains($relative, $preview['updated']);
        self::assertSame('<p>Plantilla antigua</p>', file_get_contents($project . '/' . $relative));
        $protected = $updater->update($project, ['notifications-email'], null, '', true);
        self::assertContains($relative, $protected['conflicts']);
        self::assertSame('<p>Plantilla antigua</p>', file_get_contents($project . '/' . $relative));
        $result = $updater->update($project, [], null, '', false);
        self::assertContains($relative, $result['updated']);
        $registry = new \GFrame\Mail\MailTemplateRegistry($project . '/app/views/templates/mail');
        $html = $registry->render('mailTemplate', ['h1' => 'Verifica tu cuenta', 'greeting' => 'Hola, Ana.', 'p1' => 'Confirma tu correo.', 'aHref' => 'https://example.test/verify', 'aText' => 'Verificar', 'p2' => 'Si no lo solicitaste, ignora este mensaje.']);
        self::assertStringContainsString('Hola, Ana.', $html);
        self::assertStringContainsString('Todos los derechos reservados.', $html);
        self::assertStringContainsString('Construido con GFrame.', $html);
        foreach (['contactTemplate', 'notification'] as $template) {
            self::assertStringContainsString('Todos los derechos reservados.', $registry->render($template, []));
        }
        self::assertContains($relative, $updater->update($project)['unchanged']);
    }

    public function testEverySkeletonFileHasAnExplicitUpdateOrProjectOwnershipDecision(): void
    {
        $root = dirname(__DIR__) . '/resources/skeleton';
        $projectOwned = [
            '.env.example', '.gitignore', 'composer.json', 'README.md',
            'app/controllers/home/HomeController.php',
            'app/views/templates/footer/copyright.php', 'app/views/templates/footer/credits.php',
            'config/Permissions.php', 'config/routes/routes_system.php', 'config/routes/routes_web.php',
            'public/img/apple-touch-icon.png', 'public/img/favicon.png', 'public/img/logo.png',
            'public/img/navlogo.png', 'public/img/heros/home-hero.jpg',
        ];
        $project = $this->temporaryPath . '/project';
        mkdir($project);
        $preview = ProjectUpdateService::frameworkDefault()->update($project, ['error-pages'], null, '', false, true);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (in_array($relative, $projectOwned, true)) {
                self::assertNotContains($relative, $preview['added'], $relative);
            } else {
                self::assertContains($relative, $preview['added'], 'Falta decidir cómo actualizar ' . $relative);
            }
        }
    }

    public function testUpdaterIncludesEveryFilePublishedByEveryModule(): void
    {
        $project = $this->temporaryPath . '/project';
        mkdir($project);
        $catalog = ModuleCatalog::frameworkDefault();
        $modules = array_keys($catalog->all());
        $published = (new \GFrame\Modules\ModuleAssetPublisher($catalog))->publishProject($modules, $project);
        $preview = ProjectUpdateService::frameworkDefault()->update($project, $modules, null, '', false, true);
        $expected = array_merge(array_map(static fn(string $path): string => 'public/' . $path, $published['public_files']), $published['application_files']);
        foreach ($expected as $path) self::assertContains($path, $preview['unchanged'], $path);
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
