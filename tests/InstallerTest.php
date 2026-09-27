<?php

namespace GFrame\Tests;

use GFrame\Install\InstallationProfileCatalog;
use GFrame\Install\ProjectInstaller;
use GFrame\Install\SchemaInstaller;
use GFrame\Install\SqlStatementParser;
use GFrame\Modules\ModuleAssetPublisher;
use GFrame\Modules\ModuleCatalog;
use PDO;
use PHPUnit\Framework\TestCase;

final class InstallerTest extends TestCase
{
    private string $temporaryPath;

    protected function setUp(): void
    {
        $this->temporaryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gframe-installer-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryPath, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryPath);
    }

    public function testProfilesCoverStaticManagedAndSaasApplications(): void
    {
        $profiles = InstallationProfileCatalog::frameworkDefault()->all();

        self::assertSame(['static', 'managed', 'saas'], array_keys($profiles));
        self::assertFalse($profiles['static']['database']);
        self::assertTrue($profiles['managed']['auth']);
        self::assertTrue($profiles['saas']['tenancy']);
        self::assertContains('media-library', $profiles['managed']['modules']);
    }

    public function testSqlParserKeepsSemicolonsInsideStrings(): void
    {
        $statements = (new SqlStatementParser())->parse(
            "-- comentario\nCREATE TABLE test (value TEXT); INSERT INTO test VALUES ('uno;dos');"
        );

        self::assertCount(2, $statements);
        self::assertStringContainsString("'uno;dos'", $statements[1]);
    }

    public function testSqliteInstallerCreatesAuthAndSelectedModuleTables(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite no está disponible.');
        }

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $result = SchemaInstaller::frameworkDefault()->install(
            $pdo,
            'sqlite',
            true,
            ['media-library', 'notifications']
        );

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")
            ->fetchAll(PDO::FETCH_COLUMN);

        self::assertContains('users', $tables);
        self::assertContains('roles', $tables);
        self::assertContains('media', $tables);
        self::assertContains('media_relations', $tables);
        self::assertContains('notification_queue', $tables);
        self::assertGreaterThan(0, $result['statements']);
    }

    public function testSqliteInstallerCreatesTenancySchemaForSaasProfile(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite no está disponible.');
        }

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        SchemaInstaller::frameworkDefault()->install($pdo, 'sqlite', true, [], true);

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")
            ->fetchAll(PDO::FETCH_COLUMN);
        self::assertContains('tenants', $tables);
    }

    public function testProjectInstallerCreatesConfigurationDatabaseAndFirstSuperadministrator(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite no está disponible.');
        }

        $modulesPath = $this->temporaryPath . DIRECTORY_SEPARATOR . 'modules';
        mkdir($modulesPath, 0775, true);
        $profilesFile = $this->temporaryPath . DIRECTORY_SEPARATOR . 'profiles.php';
        file_put_contents($profilesFile, "<?php return ['managed' => ['name' => 'Administrado', 'database' => true, 'auth' => true, 'tenancy' => false, 'modules' => []]];");

        $catalog = new ModuleCatalog($modulesPath);
        $installer = new ProjectInstaller(
            new InstallationProfileCatalog($profilesFile),
            $catalog,
            new SchemaInstaller($catalog, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'resources'),
            new ModuleAssetPublisher($catalog)
        );
        $databasePath = $this->temporaryPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'database.sqlite';

        $result = $installer->install([
            'project_root' => $this->temporaryPath,
            'profile' => 'managed',
            'app_name' => 'Aplicación de prueba',
            'database' => ['driver' => 'sqlite', 'path' => $databasePath],
            'superadministrator' => ['email' => 'owner@example.com', 'password' => 'Password-123'],
        ]);

        self::assertSame('success', $result['status']);
        self::assertFileExists($databasePath);
        self::assertFileExists($this->temporaryPath . DIRECTORY_SEPARATOR . '.env');
        self::assertFileExists($this->temporaryPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php');
        self::assertFileExists($this->temporaryPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'gframe-installed.json');
        self::assertFileExists($this->temporaryPath . DIRECTORY_SEPARATOR . 'index.php');
        self::assertStringContainsString(
            'La base está preparada',
            (string)file_get_contents($this->temporaryPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'home' . DIRECTORY_SEPARATOR . 'homeIndex.php')
        );

        $pdo = new PDO('sqlite:' . $databasePath);
        self::assertSame('owner@example.com', $pdo->query('SELECT email FROM users LIMIT 1')->fetchColumn());
        $configText = (string)file_get_contents($this->temporaryPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php');
        self::assertStringContainsString("'url' => env('APP_URL', null)", $configText);
        self::assertStringContainsString("'metricool'", $configText);
    }

    public function testStaticProfileRejectsDatabaseModulesBeforePublishingFiles(): void
    {
        $profilesFile = $this->temporaryPath . DIRECTORY_SEPARATOR . 'profiles.php';
        file_put_contents($profilesFile, "<?php return ['static' => ['name' => 'Estático', 'database' => false, 'auth' => false, 'tenancy' => false, 'modules' => []]];");
        $catalog = ModuleCatalog::frameworkDefault();
        $installer = new ProjectInstaller(
            new InstallationProfileCatalog($profilesFile),
            $catalog,
            SchemaInstaller::frameworkDefault(),
            new ModuleAssetPublisher($catalog)
        );

        $result = $installer->install([
            'project_root' => $this->temporaryPath,
            'profile' => 'static',
            'modules' => ['media-library'],
        ]);

        self::assertSame('modules_require_database', $result['code']);
        self::assertFileDoesNotExist($this->temporaryPath . DIRECTORY_SEPARATOR . 'index.php');
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
