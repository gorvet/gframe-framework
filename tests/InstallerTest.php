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

    public function testProfilesCoverStaticManagedIntranetAndSaasApplications(): void
    {
        $profiles = InstallationProfileCatalog::frameworkDefault()->all();

        self::assertSame(['static', 'managed', 'intranet', 'saas'], array_keys($profiles));
        self::assertFalse($profiles['static']['database']);
        self::assertTrue($profiles['managed']['auth']);
        self::assertFalse($profiles['intranet']['public']);
        self::assertContains('auth-ui', $profiles['intranet']['modules']);
        self::assertContains('admin-panel', $profiles['managed']['modules']);
        self::assertTrue($profiles['saas']['tenancy']);
        self::assertContains('media-library', $profiles['managed']['modules']);
        self::assertContains('notifications', $profiles['saas']['modules']);
        self::assertContains('cron-runner', $profiles['saas']['modules']);
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
        $lock = json_decode((string)file_get_contents($result['lock']), true);
        self::assertArrayHasKey('app/views/templates/mail/mailTemplate.html', $lock['managed_files']);
        self::assertArrayHasKey('.htaccess', $lock['managed_files']);
        self::assertArrayNotHasKey('public/img/logo.png', $lock['managed_files']);
        self::assertArrayNotHasKey('config/routes/routes_web.php', $lock['managed_files']);
        self::assertFileExists($databasePath);
        self::assertFileExists($this->temporaryPath . DIRECTORY_SEPARATOR . '.env');
        self::assertFileExists($this->temporaryPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php');
        self::assertFileExists($this->temporaryPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'routes_system.php');
        self::assertFileExists($this->temporaryPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'gframe-installed.json');
        self::assertFileExists($this->temporaryPath . DIRECTORY_SEPARATOR . 'index.php');
        self::assertFileExists($this->temporaryPath . DIRECTORY_SEPARATOR . 'install.php');
        self::assertFileExists($this->temporaryPath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'home' . DIRECTORY_SEPARATOR . 'home.css');
        self::assertStringContainsString(
            'Algo maravilloso se construye aquí.',
            (string)file_get_contents($this->temporaryPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'home' . DIRECTORY_SEPARATOR . 'homeIndex.php')
        );

        $pdo = new PDO('sqlite:' . $databasePath);
        self::assertSame('owner@example.com', $pdo->query('SELECT email FROM users LIMIT 1')->fetchColumn());
        $configText = (string)file_get_contents($this->temporaryPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php');
        self::assertStringContainsString("'url' => env('APP_URL', null)", $configText);
        self::assertStringContainsString("'metricool'", $configText);
        self::assertStringContainsString("'idle_timeout' => 1800", $configText);
        self::assertStringContainsString("'scope' => 'global'", $configText);
        $systemRoutes = (string)file_get_contents($this->temporaryPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'routes_system.php');
        self::assertStringContainsString("Route::get('sitemap.xml'", $systemRoutes);
        self::assertStringContainsString("Route::get('robots.txt'", $systemRoutes);
        self::assertStringContainsString("Route::get('llms.txt'", $systemRoutes);
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

    public function testInvalidSuperadministratorDoesNotPublishOrCreateDatabase(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite no está disponible.');
        }

        $databasePath = $this->temporaryPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'database.sqlite';
        $result = ProjectInstaller::frameworkDefault()->install([
            'project_root' => $this->temporaryPath,
            'profile' => 'managed',
            'database' => ['driver' => 'sqlite', 'path' => $databasePath],
            'superadministrator' => ['email' => 'correo-invalido', 'password' => 'Password-123'],
        ]);

        self::assertSame('invalid_email', $result['code']);
        self::assertFileDoesNotExist($databasePath);
        self::assertFileDoesNotExist($this->temporaryPath . DIRECTORY_SEPARATOR . 'index.php');
        self::assertFileDoesNotExist($this->temporaryPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'gframe-installed.json');
    }

    public function testFailedPublicationLeavesInstallerRetryable(): void
    {
        $modulesPath = $this->temporaryPath . DIRECTORY_SEPARATOR . 'test-modules';
        $modulePath = $modulesPath . DIRECTORY_SEPARATOR . 'broken';
        mkdir($modulePath, 0775, true);
        file_put_contents($modulePath . DIRECTORY_SEPARATOR . 'module.php', "<?php return ['name' => 'broken', 'assets' => [['source' => 'public', 'target' => 'test/broken']]];");
        $catalog = new ModuleCatalog($modulesPath);
        $installer = new ProjectInstaller(
            InstallationProfileCatalog::frameworkDefault(),
            $catalog,
            new SchemaInstaller($catalog, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'resources'),
            new ModuleAssetPublisher($catalog)
        );

        try {
            $installer->install(['project_root' => $this->temporaryPath, 'profile' => 'static', 'modules' => ['broken']]);
            self::fail('Se esperaba un fallo de publicación.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('No se encontró el activo', $exception->getMessage());
        }
        self::assertFileDoesNotExist($this->temporaryPath . DIRECTORY_SEPARATOR . '.env');
        self::assertFileDoesNotExist($this->temporaryPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php');
        self::assertFileDoesNotExist($this->temporaryPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'gframe-installed.json');

        mkdir($modulePath . DIRECTORY_SEPARATOR . 'public');
        file_put_contents($modulePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'style.css', 'body{}');
        $result = $installer->install(['project_root' => $this->temporaryPath, 'profile' => 'static', 'modules' => ['broken']]);
        self::assertSame('success', $result['status']);
        self::assertFileExists($this->temporaryPath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'broken' . DIRECTORY_SEPARATOR . 'style.css');
    }

    public function testExistingConfigurationStopsBeforeScaffolding(): void
    {
        file_put_contents($this->temporaryPath . DIRECTORY_SEPARATOR . '.env', 'USER_SETTING=preserve');
        $this->expectException(\RuntimeException::class);
        try {
            ProjectInstaller::frameworkDefault()->install(['project_root' => $this->temporaryPath, 'profile' => 'static']);
        } finally {
            self::assertFileDoesNotExist($this->temporaryPath . DIRECTORY_SEPARATOR . 'index.php');
            self::assertSame('USER_SETTING=preserve', file_get_contents($this->temporaryPath . DIRECTORY_SEPARATOR . '.env'));
        }
    }

    public function testEveryInstallationPublishesErrorPages(): void
    {
        $result = ProjectInstaller::frameworkDefault()->install([
            'project_root' => $this->temporaryPath,
            'profile' => 'static',
            'app_name' => 'Sitio de prueba',
        ]);

        self::assertSame('success', $result['status']);
        self::assertContains('error-pages', $result['modules']);
        self::assertDirectoryExists($this->temporaryPath . '/app/views/error-pages');
        self::assertDirectoryDoesNotExist($this->temporaryPath . '/app/controllers/error-pages');
        self::assertDirectoryDoesNotExist($this->temporaryPath . '/app/models/error-pages');
        self::assertDirectoryDoesNotExist($this->temporaryPath . '/app/services/error-pages');
        self::assertFileDoesNotExist($this->temporaryPath . '/config/modules.php');
        self::assertFileExists($this->temporaryPath . '/deployment/nginx.conf');
        self::assertFileDoesNotExist($this->temporaryPath . '/config/server/nginx.conf');
        self::assertFileDoesNotExist($this->temporaryPath . '/app/views/error-pages/error404.php');
        self::assertFileDoesNotExist($this->temporaryPath . '/app/views/templates/errorTemplate.php');
    }

    public function testDatabaseProfilesInstallTheirCompleteModuleSets(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite no está disponible.');
        }

        foreach (['managed', 'intranet', 'saas'] as $profile) {
            $project = $this->temporaryPath . DIRECTORY_SEPARATOR . $profile;
            mkdir($project, 0775, true);
            $database = $project . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'database.sqlite';

            $result = ProjectInstaller::frameworkDefault()->install([
                'project_root' => $project,
                'profile' => $profile,
                'app_name' => 'Aplicación ' . $profile,
                'database' => ['driver' => 'sqlite', 'path' => $database],
                'superadministrator' => [
                    'email' => $profile . '@example.com',
                    'password' => 'Password-123',
                ],
            ]);

            self::assertSame('success', $result['status'], 'Falló el perfil ' . $profile);
            self::assertContains('error-pages', $result['modules']);
            self::assertContains('heartbeat-client', $result['modules']);
            self::assertContains('media-library', $result['modules']);
            self::assertContains('admin-panel', $result['modules']);
            self::assertDirectoryExists($project . '/app/views/admin-panel');
            self::assertFileDoesNotExist($project . '/app/views/templates/adminTemplate.php');
            self::assertFileExists($project . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'session.js');

            $pdo = new PDO('sqlite:' . $database);
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
            self::assertContains('users', $tables);
            self::assertContains('media', $tables);
            self::assertContains('gframe_migrations', $tables);
            self::assertSame(2, (int)$pdo->query("SELECT COUNT(*) FROM gframe_migrations WHERE module = 'media-library'")->fetchColumn());
            self::assertContains('remote_url', $pdo->query('PRAGMA table_info(media)')->fetchAll(PDO::FETCH_COLUMN, 1));
            $lock = json_decode((string)file_get_contents($project . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'gframe-installed.json'), true);
            self::assertNotEmpty($lock['framework_version'] ?? '');
            self::assertNotEmpty($lock['managed_files'] ?? []);

            if ($profile === 'saas') {
                self::assertContains('tenants', $tables);
                self::assertContains('notification_queue', $tables);
                self::assertContains('cron_tasks', $tables);
                $config = (string)file_get_contents($project . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php');
                self::assertStringContainsString("'scope' => 'tenant'", $config);
            }
        }
    }

    public function testSaasProfileInstallsOnDisposableMysqlDatabase(): void
    {
        if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO MySQL no está disponible.');
        }
        if (getenv('GFRAME_TEST_MYSQL') !== '1') {
            self::markTestSkipped('La prueba MySQL requiere GFRAME_TEST_MYSQL=1.');
        }

        $host = getenv('GFRAME_TEST_MYSQL_HOST') ?: '127.0.0.1';
        $port = (int)(getenv('GFRAME_TEST_MYSQL_PORT') ?: 3306);
        $user = getenv('GFRAME_TEST_MYSQL_USER') ?: 'root';
        $password = getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: '';
        $name = 'gframe_install_test_' . bin2hex(random_bytes(6));
        $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        try {
            $result = ProjectInstaller::frameworkDefault()->install([
                'project_root' => $this->temporaryPath,
                'profile' => 'saas',
                'database' => [
                    'driver' => 'mysql', 'host' => $host, 'port' => $port,
                    'database' => $name, 'username' => $user, 'password' => $password,
                    'auto_create' => true,
                ],
                'superadministrator' => ['email' => 'saas@example.test', 'password' => 'Password-123'],
            ]);
            self::assertSame('success', $result['status']);
            $database = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $password);
            $tables = $database->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach (['users', 'roles', 'tenants', 'tenant_memberships', 'gframe_sessions', 'notification_queue', 'cron_tasks'] as $table) {
                self::assertContains($table, $tables);
            }
            self::assertNotContains('gframe_session_users', $tables);
            self::assertSame('saas@example.test', $database->query('SELECT email FROM users LIMIT 1')->fetchColumn());
        } finally {
            if (preg_match('/^gframe_install_test_[a-f0-9]{12}$/', $name) === 1) {
                $server->exec('DROP DATABASE IF EXISTS `' . $name . '`');
            }
        }
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
