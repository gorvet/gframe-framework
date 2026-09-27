<?php

namespace GFrame\Modules;

use InvalidArgumentException;
use RuntimeException;

final class ModuleCatalog
{
    public function __construct(private readonly string $modulesPath)
    {
    }

    public static function frameworkDefault(): self
    {
        return new self(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'modules');
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $modules = [];
        $files = glob(rtrim($this->modulesPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'module.php') ?: [];
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($files as $file) {
            $module = require $file;
            if (!is_array($module)) {
                throw new RuntimeException("El manifiesto {$file} debe devolver un arreglo.");
            }

            $name = $this->validateName((string) ($module['name'] ?? ''));
            if (isset($modules[$name])) {
                throw new RuntimeException("El módulo {$name} está duplicado.");
            }

            $module['name'] = $name;
            $module['path'] = dirname($file);
            $module['dependencies'] = array_values(array_unique(array_map(
                fn(mixed $dependency): string => $this->validateName((string) $dependency),
                (array) ($module['dependencies'] ?? [])
            )));
            $module['assets'] = array_values((array) ($module['assets'] ?? []));
            $module['default'] = (bool) ($module['default'] ?? false);
            $modules[$name] = $module;
        }

        return $modules;
    }

    /** @return list<array<string, mixed>> */
    public function defaults(): array
    {
        $names = [];
        foreach ($this->all() as $module) {
            if ($module['default']) {
                $names[] = $module['name'];
            }
        }

        return $this->resolve($names);
    }

    /** @return array<string, mixed> */
    public function get(string $name): array
    {
        $name = $this->validateName($name);
        $modules = $this->all();
        if (!isset($modules[$name])) {
            throw new InvalidArgumentException("El módulo {$name} no existe.");
        }

        return $modules[$name];
    }

    /** @param list<string> $names @return list<array<string, mixed>> */
    public function resolve(array $names): array
    {
        $modules = $this->all();
        $resolved = [];
        $visiting = [];

        $visit = function (string $name) use (&$visit, &$resolved, &$visiting, $modules): void {
            $name = $this->validateName($name);
            if (isset($resolved[$name])) {
                return;
            }
            if (isset($visiting[$name])) {
                throw new RuntimeException("Dependencia circular detectada en {$name}.");
            }
            if (!isset($modules[$name])) {
                throw new InvalidArgumentException("El módulo {$name} no existe.");
            }

            $visiting[$name] = true;
            foreach ($modules[$name]['dependencies'] as $dependency) {
                $visit($dependency);
            }
            unset($visiting[$name]);
            $resolved[$name] = $modules[$name];
        };

        foreach ($names as $name) {
            $visit($name);
        }

        return array_values($resolved);
    }

    private function validateName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name) !== 1) {
            throw new InvalidArgumentException("Nombre de módulo inválido: {$name}.");
        }

        return $name;
    }
}
