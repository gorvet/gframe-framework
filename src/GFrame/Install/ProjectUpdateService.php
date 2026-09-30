<?php

namespace GFrame\Install;

use Composer\InstalledVersions;
use GFrame\Modules\ModuleCatalog;
use PDO;
use RuntimeException;

final class ProjectUpdateService
{
    public function __construct(
        private readonly ModuleCatalog $modules,
        private readonly MigrationRunner $migrations,
        private readonly PermissionTemplateSynchronizer $permissions = new PermissionTemplateSynchronizer()
    ) {
    }

    public static function frameworkDefault(): self
    {
        $modules = ModuleCatalog::frameworkDefault();
        return new self($modules, new MigrationRunner($modules));
    }

    public function update(
        string $projectRoot,
        array $requestedModules = [],
        ?PDO $pdo = null,
        string $driver = '',
        bool $preserveCustom = false,
        bool $dryRun = false
    ): array {
        $projectRoot = realpath($projectRoot) ?: '';
        if ($projectRoot === '' || !is_dir($projectRoot)) throw new RuntimeException('La raíz del proyecto no es válida.');
        $lockPath = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'gframe-installed.json';
        $lock = $this->readLock($lockPath);
        $modules = $requestedModules !== [] ? $requestedModules : (array)($lock['modules'] ?? []);
        if ($modules === []) throw new RuntimeException('Indica los módulos instalados con --modules o crea el registro de instalación.');
        $resolved = $this->modules->resolve(array_values(array_unique(array_map('strval', $modules))));
        $moduleNames = array_column($resolved, 'name');
        $managed = is_array($lock['managed_files'] ?? null) ? $lock['managed_files'] : [];
        $nextManaged = $managed;
        $updated = [];
        $added = [];
        $unchanged = [];
        $conflicts = [];
        $overwrittenCustom = [];
        $copies = [];

        foreach ($this->files($resolved, $projectRoot) as $relative => $source) {
            $target = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $sourceHash = hash_file('sha256', $source);
            $targetHash = is_file($target) ? hash_file('sha256', $target) : null;
            $recordedHash = is_string($managed[$relative] ?? null) ? $managed[$relative] : null;
            $safe = $targetHash === null || $targetHash === $sourceHash || ($recordedHash !== null && hash_equals($recordedHash, $targetHash));
            if (!$safe && $preserveCustom) {
                $conflicts[] = $relative;
                continue;
            }
            if (!$safe) $overwrittenCustom[] = $relative;
            if ($targetHash === $sourceHash) {
                $unchanged[] = $relative;
                $nextManaged[$relative] = $sourceHash;
                continue;
            }
            $copies[$source] = $target;
            if ($targetHash === null) {
                $added[] = $relative;
            } else {
                $updated[] = $relative;
            }
            $nextManaged[$relative] = $sourceHash;
        }

        $migrationResult = ['executed' => [], 'count' => 0];
        $permissionResult = ['roles' => 0, 'granted' => 0, 'revoked' => 0];
        if ($pdo instanceof PDO) {
            $migrationResult = $dryRun
                ? $this->migrations->pending($pdo, $driver, $moduleNames)
                : $this->migrations->migrate($pdo, $driver, $moduleNames);
            $permissionResult = $this->permissions->sync($pdo, $projectRoot, $dryRun);
        }

        if (!$dryRun) {
            foreach ($copies as $source => $target) $this->copy($source, $target);
            $lock['modules'] = $moduleNames;
            $lock['managed_files'] = $nextManaged;
            $lock['framework_version'] = $this->frameworkVersion();
            $lock['updated_at'] = date(DATE_ATOM);
            $this->writeLock($lockPath, $lock);
        }

        return [
            'status' => 'success', 'code' => $dryRun ? 'project_update_previewed' : 'project_updated',
            'modules' => $moduleNames, 'added' => $added, 'updated' => $updated,
            'unchanged' => $unchanged, 'conflicts' => $conflicts, 'overwritten_custom' => $overwrittenCustom,
            'migrations' => $migrationResult,
            'permissions' => $permissionResult,
        ];
    }

    private function files(array $modules, string $projectRoot): array
    {
        $files = [];
        foreach ($modules as $module) {
            foreach ((array)($module['assets'] ?? []) as $entry) {
                $this->collect((string)$module['path'], (array)$entry, 'public', $files);
            }
            foreach ((array)($module['application'] ?? []) as $entry) {
                $this->collect((string)$module['path'], (array)$entry, '', $files);
            }
        }
        ksort($files, SORT_NATURAL | SORT_FLAG_CASE);
        return $files;
    }

    private function collect(string $modulePath, array $entry, string $prefix, array &$files): void
    {
        $sourceRelative = $this->safeRelative((string)($entry['source'] ?? ''));
        $targetRelative = $this->safeRelative((string)($entry['target'] ?? ''));
        $source = realpath($modulePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sourceRelative));
        $moduleRoot = realpath($modulePath);
        if ($source === false || $moduleRoot === false || !$this->within($source, $moduleRoot)) throw new RuntimeException("No se encontró {$sourceRelative} dentro del módulo.");
        $targetBase = trim($prefix . '/' . $targetRelative, '/');
        if (is_file($source)) {
            $files[$targetBase] = $source;
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $suffix = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen(rtrim(str_replace('\\', '/', $source), '/'))), '/');
            $files[$targetBase . '/' . $suffix] = $file->getPathname();
        }
    }

    private function safeRelative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || in_array('..', explode('/', $path), true) || preg_match('/^[A-Za-z]:/', $path)) throw new RuntimeException("Ruta de actualización inválida: {$path}.");
        return $path;
    }

    private function copy(string $source, string $target): void
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException("No se pudo crear {$directory}.");
        if (!copy($source, $target)) throw new RuntimeException("No se pudo actualizar {$target}.");
    }

    private function readLock(string $path): array
    {
        if (!is_file($path)) return [];
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) throw new RuntimeException('El registro de instalación no contiene JSON válido.');
        return $data;
    }

    private function writeLock(string $path, array $data): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('No se pudo crear el directorio de estado.');
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) throw new RuntimeException('No se pudo guardar el estado de actualización.');
    }

    private function frameworkVersion(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('gframe/framework')) {
            return (string)(InstalledVersions::getPrettyVersion('gframe/framework') ?? InstalledVersions::getReference('gframe/framework') ?? 'unknown');
        }
        return 'development';
    }

    private function within(string $path, string $root): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $root = rtrim(str_replace('\\', '/', $root), '/');
        return $path === $root || str_starts_with($path, $root . '/');
    }
}
