<?php

namespace GFrame\Modules;

use RuntimeException;

final class ModuleAssetPublisher
{
    public function __construct(private readonly ModuleCatalog $catalog)
    {
    }

    /**
     * @param list<string> $modules
     * @return array{modules:list<string>,files:list<string>}
     */
    public function publish(array $modules, string $publicPath, bool $overwrite = false): array
    {
        $publicPath = $this->preparePublicPath($publicPath);
        $publishedModules = [];
        $publishedFiles = [];

        foreach ($this->catalog->resolve($modules) as $module) {
            foreach ($module['assets'] as $asset) {
                if (!is_array($asset)) {
                    throw new RuntimeException("Activo inválido en el módulo {$module['name']}.");
                }

                $source = $this->resolveSource((string) $module['path'], (string) ($asset['source'] ?? ''));
                $target = $this->resolveTarget($publicPath, (string) ($asset['target'] ?? ''));
                $this->copy($source, $target, $overwrite, $publishedFiles, $publicPath);
            }
            $publishedModules[] = (string) $module['name'];
        }

        return ['modules' => $publishedModules, 'files' => $publishedFiles];
    }

    /**
     * Publica activos públicos y archivos de aplicación declarados por los módulos.
     *
     * @param list<string> $modules
     * @return array{modules:list<string>,public_files:list<string>,application_files:list<string>}
     */
    public function publishProject(array $modules, string $projectRoot, bool $overwrite = false): array
    {
        $projectRoot = rtrim($projectRoot, "\\/");
        if ($projectRoot === '' || !is_dir($projectRoot)) {
            throw new RuntimeException('La raíz del proyecto no es válida.');
        }

        $public = $this->publish($modules, $projectRoot . DIRECTORY_SEPARATOR . 'public', $overwrite);
        $applicationFiles = [];
        foreach ($this->catalog->resolve($modules) as $module) {
            ModuleRuntime::createCustomizationDirectories($module, $projectRoot);
            foreach ((array)($module['application'] ?? []) as $entry) {
                if (!is_array($entry)) {
                    throw new RuntimeException("Archivo de aplicación inválido en el módulo {$module['name']}.");
                }
                $source = $this->resolveSource((string)$module['path'], (string)($entry['source'] ?? ''));
                $target = $this->resolveTarget($projectRoot, (string)($entry['target'] ?? ''));
                $this->copy($source, $target, $overwrite, $applicationFiles, $projectRoot);
            }
        }

        return [
            'modules' => $public['modules'],
            'public_files' => $public['files'],
            'application_files' => $applicationFiles,
        ];
    }

    private function preparePublicPath(string $publicPath): string
    {
        $publicPath = rtrim($publicPath, "\\/");
        if ($publicPath === '') {
            throw new RuntimeException('La ruta pública no puede estar vacía.');
        }
        if (!is_dir($publicPath) && !mkdir($publicPath, 0775, true) && !is_dir($publicPath)) {
            throw new RuntimeException("No se pudo crear {$publicPath}.");
        }

        return realpath($publicPath) ?: $publicPath;
    }

    private function resolveSource(string $modulePath, string $source): string
    {
        if ($source === '' || $this->hasTraversal($source)) {
            throw new RuntimeException("Ruta de origen inválida: {$source}.");
        }

        $moduleRoot = realpath($modulePath);
        $resolved = realpath($modulePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $source));
        if ($moduleRoot === false || $resolved === false || !$this->isWithin($resolved, $moduleRoot)) {
            throw new RuntimeException("No se encontró el activo {$source} dentro del módulo.");
        }

        return $resolved;
    }

    private function resolveTarget(string $publicPath, string $target): string
    {
        if ($target === '' || $this->hasTraversal($target) || preg_match('/^[A-Za-z]:[\\\\\/]|^[\\\\\/]/', $target) === 1) {
            throw new RuntimeException("Ruta de destino inválida: {$target}.");
        }

        return $publicPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $target);
    }

    /** @param list<string> $publishedFiles */
    private function copy(string $source, string $target, bool $overwrite, array &$publishedFiles, string $publicPath): void
    {
        if (is_dir($source)) {
            if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
                throw new RuntimeException("No se pudo crear {$target}.");
            }
            $items = scandir($source);
            if ($items === false) {
                throw new RuntimeException("No se pudo leer {$source}.");
            }
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $this->copy(
                    $source . DIRECTORY_SEPARATOR . $item,
                    $target . DIRECTORY_SEPARATOR . $item,
                    $overwrite,
                    $publishedFiles,
                    $publicPath
                );
            }
            return;
        }

        if (is_file($target) && !$overwrite) {
            return;
        }
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("No se pudo crear {$directory}.");
        }
        if (!copy($source, $target)) {
            throw new RuntimeException("No se pudo publicar {$source}.");
        }

        $publishedFiles[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($target, strlen($publicPath) + 1));
    }

    private function hasTraversal(string $path): bool
    {
        return in_array('..', preg_split('#[\\\\/]#', $path) ?: [], true);
    }

    private function isWithin(string $path, string $root): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $root = rtrim(str_replace('\\', '/', $root), '/');

        return $path === $root || str_starts_with($path, $root . '/');
    }
}
