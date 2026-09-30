<?php

namespace GFrame\Install;

use RuntimeException;

final class ProjectConfigWriter
{
    /** @return array{config:string,environment:string,modules:string} */
    public function write(string $projectRoot, array $settings, array $modules, bool $overwrite = false, array $moduleEnvironment = []): array
    {
        $projectRoot = $this->projectRoot($projectRoot);
        $configDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'config';
        if (!is_dir($configDirectory) && !mkdir($configDirectory, 0775, true) && !is_dir($configDirectory)) {
            throw new RuntimeException('No se pudo crear el directorio de configuración.');
        }

        $configPath = $configDirectory . DIRECTORY_SEPARATOR . 'app.php';
        $environmentPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
        $modulesPath = $configDirectory . DIRECTORY_SEPARATOR . 'modules.php';
        $this->guard([$configPath, $environmentPath, $modulesPath], $overwrite);

        $tenancy = !empty($settings['tenancy'])
            ? ['key' => (string)($settings['tenant_key'] ?? 'tenant_id'), 'table' => (string)($settings['tenant_table'] ?? 'tenants')]
            : ['key' => null, 'table' => null];

        $database = (array)($settings['database'] ?? []);
        $php = $this->configFile($settings, $database, $tenancy);
        $environment = $this->environment($settings, $database, $moduleEnvironment);
        $moduleFile = "<?php\n\nreturn " . var_export(array_values($modules), true) . ";\n";

        $written = [];
        try {
            foreach ([$configPath => $php, $environmentPath => $environment, $modulesPath => $moduleFile] as $path => $content) {
                $this->writeFile($path, $content);
                $written[] = $path;
            }
        } catch (RuntimeException $exception) {
            if (!$overwrite) {
                foreach ($written as $path) {
                    unlink($path);
                }
            }
            throw $exception;
        }

        return ['config' => $configPath, 'environment' => $environmentPath, 'modules' => $modulesPath];
    }

    public function assertAvailable(string $projectRoot): void
    {
        $root = $this->projectRoot($projectRoot);
        $this->guard([
            $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php',
            $root . DIRECTORY_SEPARATOR . '.env',
            $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'modules.php',
        ], false);
    }

    private function projectRoot(string $path): string
    {
        $root = realpath($path);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('La raíz del proyecto no es válida.');
        }
        return rtrim($root, '\\/');
    }

    private function guard(array $paths, bool $overwrite): void
    {
        if ($overwrite) {
            return;
        }
        foreach ($paths as $path) {
            if (is_file($path)) {
                throw new RuntimeException("El archivo {$path} ya existe.");
            }
        }
    }

    private function environment(array $settings, array $database, array $moduleEnvironment = []): string
    {
        $values = [
            'APP_NAME' => (string)($settings['app_name'] ?? 'GFrame'),
            'APP_ENV' => (string)($settings['environment'] ?? 'production'),
            'APP_DEBUG' => !empty($settings['debug']) ? 'true' : 'false',
            'APP_URL' => (string)($settings['app_url'] ?? ''),
            'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
            'METRICOOL_HASH' => (string)($settings['metricool_hash'] ?? ''),
            'SESSION_DRIVER' => $database !== [] ? 'database' : 'native',
            'SESSION_REDIS_HOST' => '127.0.0.1',
            'SESSION_REDIS_PORT' => '6379',
            'SESSION_REDIS_PASSWORD' => '',
            'SESSION_REDIS_DATABASE' => '0',
        ];
        if ($database !== []) {
            $values['DB_DRIVER'] = (string)($database['driver'] ?? 'mysql');
            if ($values['DB_DRIVER'] === 'sqlite') {
                $values['DB_SQLITE_PATH'] = (string)($database['path'] ?? 'storage/database.sqlite');
            } else {
                $values['DB_HOST'] = (string)($database['host'] ?? 'localhost');
                $values['DB_PORT'] = (string)($database['port'] ?? 3306);
                $values['DB_NAME'] = (string)($database['database'] ?? '');
                $values['DB_USER'] = (string)($database['username'] ?? '');
                $values['DB_PASSWORD'] = (string)($database['password'] ?? '');
            }
        }
        foreach ($moduleEnvironment as $key) {
            $key = strtoupper(trim((string)$key));
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) === 1 && !array_key_exists($key, $values)) {
                $values[$key] = '';
            }
        }

        $lines = [];
        foreach ($values as $key => $value) {
            $lines[] = $key . '=' . $this->quoteEnvironment((string)$value);
        }
        return implode("\n", $lines) . "\n";
    }

    private function quoteEnvironment(string $value): string
    {
        return '"' . str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', ''], $value) . '"';
    }

    private function configFile(array $settings, array $database, array $tenancy): string
    {
        $name = var_export((string)($settings['app_name'] ?? 'GFrame'), true);
        $environment = var_export((string)($settings['environment'] ?? 'production'), true);
        $timezone = var_export((string)($settings['timezone'] ?? 'UTC'), true);
        $language = var_export((string)($settings['language'] ?? 'es'), true);
        $debug = !empty($settings['debug']) ? 'true' : 'false';
        $public = !array_key_exists('public', $settings) || !empty($settings['public']) ? 'true' : 'false';
        $databaseConfig = '[]';
        if ($database !== []) {
            $databaseConfig = strtolower((string)($database['driver'] ?? 'mysql')) === 'sqlite'
                ? "['main' => ['driver' => 'sqlite', 'path' => project_path((string)env('DB_SQLITE_PATH', 'storage/database.sqlite')), 'foreign_keys' => true]]"
                : "['main' => ['driver' => 'mysql', 'host' => env('DB_HOST', 'localhost'), 'port' => env_int('DB_PORT', 3306), 'database' => env('DB_NAME', ''), 'username' => env('DB_USER', ''), 'password' => env('DB_PASSWORD', ''), 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'auto_create' => false]]";
        }
        $auth = var_export([
            'password_expiration' => [
                'enabled' => (bool)($settings['password_expiration_enabled'] ?? false),
                'days' => max(1, (int)($settings['password_expiration_days'] ?? 90)),
                'warning_days' => max(0, (int)($settings['password_expiration_warning_days'] ?? 7)),
            ],
        ], true);
        $seo = var_export([
            'enabled' => (bool)($settings['seo_enabled'] ?? true),
            'allow_indexing' => (bool)($settings['seo_allow_indexing'] ?? true),
            'sitemap' => (bool)($settings['seo_sitemap'] ?? true),
            'robots' => (bool)($settings['seo_robots'] ?? true),
            'llms' => (bool)($settings['seo_llms'] ?? true),
        ], true);
        $metricoolEnabled = !empty($settings['metricool_enabled']) ? 'true' : 'false';
        $analytics = "['enabled' => {$metricoolEnabled}, 'metricool' => ['enabled' => {$metricoolEnabled}, 'hash' => env('METRICOOL_HASH', '')]]";
        $tenancyExport = var_export($tenancy, true);
        $idleTimeout = max(60, (int)($settings['session_idle_timeout'] ?? 1800));
        $sessionDriver = $database !== [] ? 'database' : 'native';
        $mediaScope = (string)($settings['media_scope'] ?? (!empty($settings['tenancy']) ? 'tenant' : 'global'));
        if (!in_array($mediaScope, ['global', 'tenant', 'user'], true)) {
            $mediaScope = !empty($settings['tenancy']) ? 'tenant' : 'global';
        }
        $mediaScopeExport = var_export($mediaScope, true);

        return <<<PHP
<?php

return [
    'app' => [
        'name' => env('APP_NAME', {$name}),
        'environment' => env('APP_ENV', {$environment}),
        'debug' => env_bool('APP_DEBUG', {$debug}),
        'public' => {$public},
        'url' => env('APP_URL', null),
        'timezone' => {$timezone},
        'language' => {$language},
        'supported_languages' => [{$language}],
    ],
    'database' => ['default' => 'main', 'connections' => {$databaseConfig}],
    'session' => [
        'name' => null,
        'driver' => env('SESSION_DRIVER', '{$sessionDriver}'),
        'connection' => 'main',
        'lifetime' => {$idleTimeout},
        'idle_timeout' => {$idleTimeout},
        'redis' => [
            'host' => env('SESSION_REDIS_HOST', '127.0.0.1'),
            'port' => env_int('SESSION_REDIS_PORT', 6379),
            'password' => env('SESSION_REDIS_PASSWORD', ''),
            'database' => env_int('SESSION_REDIS_DATABASE', 0),
            'timeout' => 2.0,
            'prefix' => 'gframe:session:',
        ],
    ],
    'auth' => {$auth},
    'tenancy' => {$tenancyExport},
    'media' => ['scope' => {$mediaScopeExport}],
    'seo' => {$seo},
    'analytics' => {$analytics},
];
PHP;
    }

    private function writeFile(string $path, string $content): void
    {
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException("No se pudo escribir {$path}.");
        }
    }
}
