<?php

namespace GFrame\Install;

use RuntimeException;

final class ProjectScaffolder
{
    /** Archivos gestionados que deben mantenerse al actualizar un proyecto. */
    public const UPDATE_PATHS = [
        '.htaccess', 'index.php', 'install.php', 'core/Load.php',
        'config/meta/global.meta.php',
        'deployment',
        'app/views/templates/header.php', 'app/views/templates/footer.php',
        'app/views/templates/mail',
        'public/css/variables.css', 'public/css/bootstrap-buttons-compat.css',
        'public/css/common.css', 'public/css/colores.html',
        'public/css/install/install.css', 'public/js/install/install.js',
        'app/views/home/homeIndex.php', 'app/views/home/home.group.meta.php',
        'app/views/templates/homeTemplate.php', 'public/css/home/home.css',
        'public/js/app/home/mngnoadmin.js',
    ];

    public function __construct(private readonly string $skeletonPath)
    {
    }

    public static function frameworkDefault(): self
    {
        return new self(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'skeleton');
    }

    /** @return list<string> */
    public function publish(string $projectRoot, bool $overwrite = false): array
    {
        $sourceRoot = realpath($this->skeletonPath);
        $targetRoot = realpath($projectRoot);
        if ($sourceRoot === false || $targetRoot === false || !is_dir($targetRoot)) {
            throw new RuntimeException('No se encontró el esqueleto o la raíz del proyecto.');
        }

        $files = [];
        $this->copyDirectory($sourceRoot, $targetRoot, $overwrite, $files, $targetRoot);
        return $files;
    }

    /** @param list<string> $files */
    private function copyDirectory(string $source, string $target, bool $overwrite, array &$files, string $targetRoot): void
    {
        if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
            throw new RuntimeException("No se pudo crear {$target}.");
        }
        foreach (scandir($source) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $source . DIRECTORY_SEPARATOR . $item;
            $to = $target . DIRECTORY_SEPARATOR . $item;
            if (is_dir($from)) {
                $this->copyDirectory($from, $to, $overwrite, $files, $targetRoot);
                continue;
            }
            if (is_file($to) && !$overwrite) {
                continue;
            }
            if (!copy($from, $to)) {
                throw new RuntimeException("No se pudo copiar {$from}.");
            }
            $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($to, strlen($targetRoot) + 1));
        }
    }
}
