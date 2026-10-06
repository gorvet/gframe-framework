<?php

namespace GFrame\Modules;

use RuntimeException;

/** File lookup only: route inference, permissions and responses remain unchanged. */
final class ModuleRuntime
{
    private static array $modules = [];
    private static array $installed = [];
    private static string $projectRoot = '';
    private static bool $autoloadRegistered = false;

    public static function initialize(ModuleCatalog $catalog, array $installed, string $projectRoot): void
    {
        self::$modules = [];
        self::$installed = [];
        self::$projectRoot = rtrim($projectRoot, '/\\');
        foreach ($catalog->resolve($installed) as $module) {
            self::$installed[$module['name']] = true;
            if (!isset($module['runtime'])) continue;
            $runtime = $module['runtime'];
            $root = self::containedFile($module['path'], (string)($runtime['root'] ?? ''));
            $namespace = trim((string)($runtime['namespace'] ?? ''), '\\');
            if ($root === null || !is_dir($root) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $namespace)) {
                throw new RuntimeException('La estructura runtime del módulo no es válida: ' . $module['name']);
            }
            self::$modules[$module['name']] = ['root' => $root, 'namespace' => $namespace, 'templates' => (array)($runtime['templates'] ?? [])];
        }
        if (!self::$autoloadRegistered) {
            spl_autoload_register([self::class, 'autoload']);
            self::$autoloadRegistered = true;
        }
    }

    public static function inferModule(string $controller): ?string
    {
        $parts = explode('/', str_replace('\\', '/', $controller));
        $name = count($parts) > 1 ? $parts[count($parts) - 2] : '';
        return isset(self::$modules[$name]) ? $name : null;
    }

    /** Indica si el módulo runtime está activo en este proyecto. */
    public static function has(string $module): bool
    {
        return isset(self::$modules[$module]);
    }

    /** Consulta el registro cargado al arrancar, incluidos los módulos sin MVC. */
    public static function isInstalled(string $module): bool
    {
        return isset(self::$installed[ModuleCatalog::canonicalName($module)]);
    }

    public static function file(string $type, string $relative, ?string $module = null): ?string
    {
        if (!in_array($type, ['controllers', 'models', 'services', 'views'], true)) return null;
        $projectRoot = self::$projectRoot !== '' ? self::$projectRoot : (defined('ABSPATH') ? rtrim(ABSPATH, '/\\') : '');
        $project = self::containedFile($projectRoot . '/app/' . $type, $relative);
        if ($project !== null && is_file($project)) return $project;
        if ($module === null || !isset(self::$modules[$module])) return null;
        $native = self::containedFile(self::$modules[$module]['root'] . '/' . $type, $relative);
        return $native !== null && is_file($native) ? $native : null;
    }

    public static function template(string $relative, ?string $module = null): ?string
    {
        $file = self::file('views', 'templates/' . $relative, $module);
        if ($file !== null) return $file;
        $name = preg_replace('/(?:Template)?(?:\.meta)?\.php$/', '', $relative);
        $owners = [];
        foreach (self::$modules as $owner => $definition) {
            if (in_array($name, $definition['templates'], true)) $owners[] = $owner;
        }
        if (count($owners) > 1) throw new RuntimeException('Template declarado por varios módulos: ' . $name);
        return $owners === [] ? null : self::file('views', 'templates/' . $relative, $owners[0]);
    }

    public static function controller(string $relative, ?string $module = null): ?array
    {
        $file = self::file('controllers', $relative . '.php', $module);
        if ($file === null) return null;
        require_once $file;
        $parts = explode('/', str_replace('\\', '/', $relative));
        $name = array_pop($parts);
        $projectClass = 'App\\Controllers\\' . implode('\\', array_map([self::class, 'studly'], $parts)) . ($parts !== [] ? '\\' : '') . $name;
        $nativeClass = self::nativeClass($relative, 'controllers', $module);
        $projectRoot = self::$projectRoot !== '' ? self::$projectRoot : (defined('ABSPATH') ? rtrim(ABSPATH, '/\\') : '');
        $isProject = self::containedFile($projectRoot . '/app/controllers', $relative . '.php') === $file;
        $classes = $isProject ? [$projectClass, $name] : [$nativeClass];
        foreach ($classes as $class) {
            if ($class !== null && class_exists($class, false)) return ['path' => $file, 'class' => $class];
        }
        throw new RuntimeException('El controlador no declara la clase esperada: ' . $relative);
    }

    private static function nativeClass(string $relative, string $type, ?string $module): ?string
    {
        if ($module === null || !isset(self::$modules[$module])) return null;
        $relative = str_replace('\\', '/', $relative);
        $suffix = str_starts_with($relative, $module . '/') ? substr($relative, strlen($module) + 1) : $relative;
        return self::$modules[$module]['namespace'] . '\\' . ucfirst($type) . '\\' . str_replace('/', '\\', $suffix);
    }

    public static function autoload(string $class): void
    {
        foreach (self::$modules as $module => $definition) {
            foreach (['controllers', 'models', 'services'] as $type) {
                $prefix = $definition['namespace'] . '\\' . ucfirst($type) . '\\';
                $projectPrefix = 'App\\' . ucfirst($type) . '\\' . self::studly($module) . '\\';
                if (str_starts_with($class, $prefix)) {
                    $suffix = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                    $file = self::containedFile($definition['root'] . '/' . $type, $module . '/' . $suffix)
                        ?? self::containedFile($definition['root'] . '/' . $type, $suffix);
                } elseif (str_starts_with($class, $projectPrefix)) {
                    $file = self::containedFile(self::$projectRoot . '/app/' . $type, $module . '/' . str_replace('\\', '/', substr($class, strlen($projectPrefix))) . '.php');
                } else continue;
                if ($file !== null && is_file($file)) require_once $file;
                return;
            }
        }
    }

    public static function createCustomizationDirectories(array $module, string $projectRoot): void
    {
        if (!isset($module['runtime'])) return;
        $root = self::containedFile((string)$module['path'], (string)($module['runtime']['root'] ?? ''));
        if ($root === null || !is_dir($root)) throw new RuntimeException('La raíz runtime del módulo no es válida.');
        foreach (['controllers', 'models', 'services', 'views'] as $type) {
            if (!is_dir($root . '/' . $type)) continue;
            $hasFiles = false;
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $type, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->isFile()) { $hasFiles = true; break; }
            }
            if (!$hasFiles) continue;
            $directory = rtrim($projectRoot, '/\\') . '/app/' . $type . '/' . $module['name'];
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('No se pudo crear la carpeta de personalización: ' . $directory);
            }
        }
    }

    private static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    private static function containedFile(string $root, string $relative): ?string
    {
        if ($relative === '' || preg_match('#^(?:[A-Za-z]:|[/\\\\])#', $relative) || in_array('..', preg_split('#[/\\\\]#', $relative), true)) return null;
        $root = realpath($root);
        if ($root === false) return null;
        $resolved = realpath($root . '/' . str_replace('\\', '/', $relative));
        if ($resolved === false) return null;
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $resolved = str_replace('\\', '/', $resolved);
        return str_starts_with($resolved, $root . '/') ? $resolved : null;
    }
}
