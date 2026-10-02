<?php

namespace GFrame\Install;

use GFrame\Modules\ModuleAssetPublisher;
use GFrame\Modules\ModuleCatalog;
use Composer\InstalledVersions;
use PDO;
use RuntimeException;

final class ProjectInstaller
{
    public function __construct(
        private readonly InstallationProfileCatalog $profiles,
        private readonly ModuleCatalog $modules,
        private readonly SchemaInstaller $schemas,
        private readonly ModuleAssetPublisher $publisher,
        private readonly ProjectConfigWriter $configuration = new ProjectConfigWriter(),
        private readonly SuperadministratorInstaller $superadministrator = new SuperadministratorInstaller(),
        private readonly ?ProjectScaffolder $scaffolder = null,
        private readonly ?MigrationRunner $migrations = null,
        private readonly PermissionTemplateSynchronizer $permissions = new PermissionTemplateSynchronizer()
    ) {
    }

    public static function frameworkDefault(): self
    {
        $modules = ModuleCatalog::frameworkDefault();
        return new self(
            InstallationProfileCatalog::frameworkDefault(),
            $modules,
            SchemaInstaller::frameworkDefault(),
            new ModuleAssetPublisher($modules),
            new ProjectConfigWriter(),
            new SuperadministratorInstaller(),
            ProjectScaffolder::frameworkDefault(),
            new MigrationRunner($modules)
        );
    }

    public function install(array $input): array
    {
        $projectRoot = realpath((string)($input['project_root'] ?? ''));
        if ($projectRoot === false || !is_dir($projectRoot)) {
            return ['status' => 'error', 'code' => 'invalid_project_root'];
        }

        $lock = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'gframe-installed.json';
        if (is_file($lock)) {
            return ['status' => 'error', 'code' => 'application_already_installed'];
        }

        $profile = $this->profiles->get((string)($input['profile'] ?? 'managed'));
        $selected = array_values(array_unique(array_merge(
            array_column($this->modules->defaults(), 'name'),
            (array)$profile['modules'],
            array_map('strval', (array)($input['modules'] ?? []))
        )));
        $resolved = $this->modules->resolve($selected);
        $moduleNames = array_column($resolved, 'name');

        if (empty($profile['database']) && $this->requiresDatabase($resolved)) {
            return ['status' => 'error', 'code' => 'modules_require_database'];
        }

        if (!empty($profile['auth'])) {
            $admin = (array)($input['superadministrator'] ?? []);
            $validation = $this->superadministrator->validate(
                (string)($admin['email'] ?? ''),
                (string)($admin['password'] ?? '')
            );
            if ($validation['status'] !== 'success') {
                return $validation;
            }
        }

        $this->configuration->assertAvailable($projectRoot);

        $scaffolded = ($this->scaffolder ?? ProjectScaffolder::frameworkDefault())->publish($projectRoot);

        $database = null;
        $schemaResult = ['scripts' => [], 'statements' => 0];
        $adminResult = null;
        $permissionResult = ['roles' => 0, 'granted' => 0, 'revoked' => 0];
        $databaseSettings = (array)($input['database'] ?? []);
        if (!empty($profile['database'])) {
            $database = $this->connect($projectRoot, $databaseSettings);
            $schemaResult = $this->schemas->install(
                $database,
                strtolower((string)($databaseSettings['driver'] ?? 'mysql')),
                (bool)$profile['auth'],
                $moduleNames,
                (bool)$profile['tenancy']
            );
            if (!empty($profile['auth'])) $permissionResult = $this->permissions->sync($database, $projectRoot);
        }

        $settings = $input;
        $settings['database'] = !empty($profile['database']) ? $databaseSettings : [];
        $settings['tenancy'] = (bool)$profile['tenancy'];
        $settings['public'] = (bool)$profile['public'];
        if (!$settings['public']) {
            $settings['seo_enabled'] = false;
            $settings['seo_allow_indexing'] = false;
            $settings['seo_sitemap'] = false;
            $settings['seo_robots'] = true;
            $settings['seo_llms'] = false;
            $settings['metricool_enabled'] = false;
            $settings['metricool_hash'] = '';
        }
        $moduleEnvironment = [];
        foreach ($resolved as $module) {
            $moduleEnvironment = array_merge($moduleEnvironment, (array)($module['environment'] ?? []));
        }
        $files = $this->configuration->write($projectRoot, $settings, false, array_values(array_unique($moduleEnvironment)));
        try {
            $published = $this->publisher->publishProject($moduleNames, $projectRoot);
            if ($database instanceof PDO) {
                ($this->migrations ?? new MigrationRunner($this->modules))->baseline(
                    $database,
                    strtolower((string)($databaseSettings['driver'] ?? 'mysql')),
                    $moduleNames
                );
            }
            if (!empty($profile['auth'])) {
                if (!$database instanceof PDO) {
                    throw new RuntimeException('La autenticación requiere una conexión de base de datos.');
                }
                $admin = (array)($input['superadministrator'] ?? []);
                $adminResult = $this->superadministrator->install(
                    $database,
                    (string)($admin['email'] ?? ''),
                    (string)($admin['password'] ?? '')
                );
                if (($adminResult['status'] ?? '') !== 'success') {
                    $this->removeConfiguration($files);
                    return $adminResult;
                }
            }
            $this->writeLock($lock, [
            'installed_at' => date(DATE_ATOM),
            'profile' => $profile['slug'],
            'modules' => $moduleNames,
            'framework_version' => class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('gframe/framework')
                ? (string)(InstalledVersions::getPrettyVersion('gframe/framework') ?? 'unknown')
                : 'development',
            'managed_files' => $this->managedHashes($projectRoot, $published, $scaffolded),
            ]);
        } catch (\Exception $exception) {
            if ($database instanceof PDO && ($adminResult['status'] ?? '') === 'success') {
                $this->superadministrator->removeCreated($database, (int)$adminResult['user_id']);
            }
            $this->removeConfiguration($files);
            throw $exception;
        }

        return [
            'status' => 'success',
            'code' => 'application_installed',
            'profile' => $profile['slug'],
            'modules' => $moduleNames,
            'schemas' => $schemaResult,
            'superadministrator' => $adminResult,
            'permissions' => $permissionResult,
            'files' => $files,
            'published' => $published,
            'scaffolded' => $scaffolded,
            'lock' => $lock,
        ];
    }

    private function removeConfiguration(array $files): void
    {
        foreach ($files as $path) {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }

    private function managedHashes(string $projectRoot, array $published, array $scaffolded): array
    {
        $paths = array_merge(
            array_map(static fn(string $path): string => 'public/' . ltrim($path, '/'), (array)($published['public_files'] ?? [])),
            (array)($published['application_files'] ?? [])
        );
        foreach ($scaffolded as $path) {
            foreach (ProjectScaffolder::UPDATE_PATHS as $managed) {
                if ($path === $managed || str_starts_with($path, $managed . '/')) {
                    $paths[] = $path;
                    break;
                }
            }
        }
        $hashes = [];
        foreach ($paths as $path) {
            $normalized = str_replace('\\', '/', (string)$path);
            $absolute = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
            if (is_file($absolute)) $hashes[$normalized] = hash_file('sha256', $absolute);
        }
        ksort($hashes, SORT_NATURAL | SORT_FLAG_CASE);
        return $hashes;
    }

    private function connect(string $projectRoot, array &$settings): PDO
    {
        $driver = strtolower(trim((string)($settings['driver'] ?? 'mysql')));
        if ($driver === 'sqlite') {
            $configuredPath = trim((string)($settings['path'] ?? 'storage/database.sqlite'));
            if ($configuredPath === '' || in_array('..', preg_split('#[\\\\/]#', $configuredPath) ?: [], true)) {
                throw new RuntimeException('La ruta de SQLite no es válida.');
            }
            $path = $configuredPath;
            if (!preg_match('/^[A-Za-z]:[\\\\\/]|^[\\\\\/]/', $configuredPath)) {
                $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configuredPath);
            }
            $result = (new \SqliteConnection())->connect([
                'path' => $path,
                'foreign_keys' => true,
            ]);
        } elseif ($driver === 'mysql') {
            $settings['auto_create'] = (bool)($settings['auto_create'] ?? false);
            $result = (new \MySqlConnection())->connect($settings);
        } else {
            throw new RuntimeException("El motor {$driver} no está soportado.");
        }

        if (!$result instanceof PDO) {
            throw new RuntimeException((string)($result['message'] ?? 'No se pudo conectar con la base de datos.'));
        }
        return $result;
    }

    private function writeLock(string $path, array $data): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio de instalación.');
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo registrar la instalación.');
        }
    }

    /** @param list<array<string, mixed>> $modules */
    private function requiresDatabase(array $modules): bool
    {
        foreach ($modules as $module) {
            if (!empty($module['schemas']) || !empty($module['requires_schema'])) {
                return true;
            }
        }
        return false;
    }
}
