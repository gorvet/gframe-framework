<?php

namespace GFrame\Foundation;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use FilesystemIterator;

final class Bootstrap
{
    private static bool $booted = false;

    public static function boot(string $projectRoot): void
    {
        if (self::$booted) {
            return;
        }

        $projectRoot = realpath($projectRoot) ?: '';
        if ($projectRoot === '' || !is_dir($projectRoot)) {
            throw new RuntimeException('La raíz del proyecto no es válida.');
        }

        if (!defined('ABSPATH')) {
            define('ABSPATH', rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
        }
        if (!defined('GFRAME_PATH')) {
            define('GFRAME_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }

        self::loadProjectConfiguration($projectRoot);
        self::loadUtilityFiles();
        self::registerProjectAutoload($projectRoot . DIRECTORY_SEPARATOR . 'app');
        self::loadRoutes($projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes');

        self::$booted = true;
    }

    private static function loadProjectConfiguration(string $projectRoot): void
    {
        $candidates = [
            $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php',
            $projectRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'Config.php',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                require_once $candidate;
                return;
            }
        }

        throw new RuntimeException('No se encontró config/bootstrap.php ni el archivo de configuración heredado.');
    }

    private static function loadUtilityFiles(): void
    {
        $files = glob(GFRAME_PATH . 'utils' . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($files as $file) {
            require_once $file;
        }
    }

    private static function loadRoutes(string $routesDirectory): void
    {
        if (!is_dir($routesDirectory)) {
            throw new RuntimeException('No se encontró el directorio config/routes.');
        }

        $files = glob($routesDirectory . DIRECTORY_SEPARATOR . 'routes_*.php') ?: [];
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($files as $file) {
            require_once $file;
        }
    }

    private static function registerProjectAutoload(string $appRoot): void
    {
        $appRoot = realpath($appRoot) ?: '';
        if ($appRoot === '' || !is_dir($appRoot)) {
            return;
        }

        spl_autoload_register(static function (string $class) use ($appRoot): void {
            static $classMap = null;

            if ($classMap === null) {
                $classMap = [];
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($appRoot, FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if (!$file->isFile() || strcasecmp($file->getExtension(), 'php') !== 0) {
                        continue;
                    }

                    $className = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                    $realPath = $file->getRealPath();
                    if ($className !== '' && $realPath !== false && !isset($classMap[$className])) {
                        $classMap[$className] = $realPath;
                    }
                }
            }

            $normalized = trim($class, '\\');
            if ($normalized !== '' && isset($classMap[$normalized])) {
                require_once $classMap[$normalized];
            }
        });
    }
}
