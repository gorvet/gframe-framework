<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Config\ProjectSettings;
use GFrame\Config\Contracts\ProjectSettingsProvider;
use PHPUnit\Framework\TestCase;

final class ProjectSettingsTest extends TestCase
{
    private array $previous;

    protected function setUp(): void
    {
        $this->previous = ConfigRepository::get();
        ConfigRepository::replace(require dirname(__DIR__) . '/config/defaults.php');
    }
    protected function tearDown(): void { ConfigRepository::replace($this->previous); }

    public function testNoProviderPreservesConfiguration(): void
    {
        $before = ConfigRepository::get();
        ProjectSettings::load();
        self::assertSame($before, ConfigRepository::get());
    }

    public function testOverridesOnlyReturnedSettingsAndSelectsSupportedDefaultLanguage(): void
    {
        ConfigRepository::merge(['app' => ['settings_provider' => TestProjectSettingsProvider::class, 'supported_languages' => ['es', 'en']]]);
        TestProjectSettingsProvider::$settings = ['app' => ['name' => 'Mi sitio', 'timezone' => 'America/Havana', 'language' => 'en'],
            'seo' => ['allow_indexing' => false], 'analytics' => ['enabled' => true, 'metricool' => ['enabled' => true, 'hash' => 'abc123']]];
        ProjectSettings::load();
        self::assertSame('Mi sitio', ConfigRepository::get('app.name'));
        self::assertSame(['en', 'es'], ConfigRepository::get('app.supported_languages'));
        self::assertSame('America/Havana', ConfigRepository::get('app.timezone'));
        self::assertFalse(ConfigRepository::get('seo.allow_indexing'));
        self::assertTrue(ConfigRepository::get('seo.sitemap'));
        self::assertSame('abc123', ConfigRepository::get('analytics.metricool.hash'));
        self::assertSame(5, ConfigRepository::get('mail.rate_limit.max_attempts'));
    }

    public static function invalidSettings(): array
    {
        return [
            [['database' => ['password' => 'secret']]],
            [['app' => ['debug' => true]]],
            [['app' => ['timezone' => 'invalid']]],
            [['app' => ['language' => 'unsupported']]],
            [['seo' => ['enabled' => 'false']]],
            [['analytics' => ['metricool' => ['hash' => '"><script>']]]],
            [['mail' => ['rate_limit' => ['max_attempts' => 999]]]],
        ];
    }

    public function testIndependentProcessLoadsDatabaseSettingsBeforeConstants(): void
    {
        $root = sys_get_temp_dir() . '/gframe-settings-' . bin2hex(random_bytes(5));
        mkdir($root . '/config/routes', 0777, true);
        mkdir($root . '/app/services', 0777, true);
        $database = $root . '/settings.sqlite';
        $pdo = new \PDO('sqlite:' . $database);
        $pdo->exec('CREATE TABLE project_settings (name TEXT)');
        $pdo->exec("INSERT INTO project_settings VALUES ('Desde DB')");
        $config = ['app' => ['settings_provider' => 'DatabaseProjectSettings', 'url' => 'https://example.test', 'debug' => false],
            'database' => ['default' => 'main', 'connections' => ['main' => ['driver' => 'sqlite', 'path' => $database]]]];
        file_put_contents($root . '/config/app.php', '<?php return ' . var_export($config, true) . ';');
        file_put_contents($root . '/app/services/DatabaseProjectSettings.php', <<<'PHP'
<?php
class DatabaseProjectSettings implements \GFrame\Config\Contracts\ProjectSettingsProvider
{
    public function resolve(): array
    {
        $pdo = DatabaseManager::connection();
        return ['app' => ['name' => $pdo->query('SELECT name FROM project_settings')->fetchColumn(), 'timezone' => 'America/Havana'],
            'seo' => ['allow_indexing' => false], 'analytics' => ['enabled' => true, 'metricool' => ['enabled' => true, 'hash' => 'abc123']]];
    }
}
PHP);
        $code = 'require ' . var_export(dirname(__DIR__) . '/packages/autoload.php', true) . ';'
            . '\\GFrame\\Foundation\\Bootstrap::boot($argv[1]);'
            . 'echo json_encode([site_name, date_default_timezone_get(), SEO_ALLOW_INDEXING, Metricool, METRICOOL_HASH, session_status()]);';
        try {
            foreach (['Desde DB', 'Modificado'] as $name) {
                $pdo->prepare('UPDATE project_settings SET name = ?')->execute([$name]);
                $output = []; $status = 0;
                exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' ' . escapeshellarg($root), $output, $status);
                self::assertSame(0, $status);
                self::assertSame([$name, 'America/Havana', false, true, 'abc123', PHP_SESSION_NONE], json_decode(implode("\n", $output), true));
            }
        } finally {
            $pdo = null;
            foreach (['settings.sqlite', 'config/app.php', 'app/services/DatabaseProjectSettings.php'] as $file) unlink($root . '/' . $file);
            rmdir($root . '/config/routes'); rmdir($root . '/config'); rmdir($root . '/app/services'); rmdir($root . '/app'); rmdir($root);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSettings')]
    public function testInvalidSettingsFailAtomically(array $settings): void
    {
        ConfigRepository::merge(['app' => ['settings_provider' => TestProjectSettingsProvider::class]]);
        TestProjectSettingsProvider::$settings = $settings;
        $before = ConfigRepository::get();
        try {
            ProjectSettings::load();
            self::fail('Expected configuration failure');
        } catch (\RuntimeException $error) {
            self::assertSame('project_settings_failed', $error->getMessage());
            self::assertNull($error->getPrevious());
            self::assertSame($before, ConfigRepository::get());
        }
    }
}

final class TestProjectSettingsProvider implements ProjectSettingsProvider
{
    public static array $settings = [];
    public function resolve(): array { return self::$settings; }
}
