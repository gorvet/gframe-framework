<?php

namespace GFrame\Install;

use GFrame\Modules\ModuleCatalog;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class SchemaInstaller
{
    public function __construct(
        private readonly ModuleCatalog $modules,
        private readonly string $frameworkResources,
        private readonly SqlStatementParser $parser = new SqlStatementParser()
    ) {
    }

    public static function frameworkDefault(): self
    {
        $resources = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'resources';
        return new self(ModuleCatalog::frameworkDefault(), $resources);
    }

    /** @param list<string> $moduleNames @return array{scripts:list<string>,statements:int} */
    public function install(
        PDO $pdo,
        string $driver,
        bool $withAuth,
        array $moduleNames = [],
        bool $withTenancy = false
    ): array
    {
        $driver = strtolower(trim($driver));
        if (!in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new InvalidArgumentException("El motor {$driver} no está soportado por el instalador.");
        }

        $resolved = $this->modules->resolve($moduleNames);
        foreach ($resolved as $module) {
            if (in_array('auth', (array)($module['requires_schema'] ?? []), true)) {
                $withAuth = true;
            }
        }

        $scripts = [];
        if ($withTenancy) {
            $scripts[] = $this->frameworkResources . DIRECTORY_SEPARATOR . 'database'
                . DIRECTORY_SEPARATOR . 'schema' . DIRECTORY_SEPARATOR . $driver . DIRECTORY_SEPARATOR . 'tenancy.sql';
        }
        if ($withAuth) {
            $scripts[] = $this->frameworkResources . DIRECTORY_SEPARATOR . 'database'
                . DIRECTORY_SEPARATOR . 'schema' . DIRECTORY_SEPARATOR . $driver . DIRECTORY_SEPARATOR . 'auth.sql';
        }

        foreach ($resolved as $module) {
            $relative = $module['schemas'][$driver] ?? null;
            if (!is_string($relative) || trim($relative) === '') {
                continue;
            }
            $scripts[] = $this->safeModuleSchema((string)$module['path'], $relative);
        }

        $scripts = array_values(array_unique($scripts));
        $statementCount = 0;
        foreach ($scripts as $script) {
            if (!is_file($script)) {
                throw new RuntimeException("No se encontró el esquema {$script}.");
            }
            foreach ($this->parser->parse((string)file_get_contents($script)) as $statement) {
                $pdo->exec($statement);
                $statementCount++;
            }
        }

        return ['scripts' => $scripts, 'statements' => $statementCount];
    }

    private function safeModuleSchema(string $modulePath, string $relative): string
    {
        $relative = str_replace('\\', '/', trim($relative));
        if ($relative === '' || str_starts_with($relative, '/') || in_array('..', explode('/', $relative), true)) {
            throw new RuntimeException('La ruta del esquema del módulo no es válida.');
        }

        return rtrim($modulePath, '\\/') . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
