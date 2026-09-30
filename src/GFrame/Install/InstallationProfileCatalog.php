<?php

namespace GFrame\Install;

use InvalidArgumentException;
use RuntimeException;

final class InstallationProfileCatalog
{
    public function __construct(private readonly string $profilesFile)
    {
    }

    public static function frameworkDefault(): self
    {
        return new self(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'resources'
            . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'profiles.php');
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        if (!is_file($this->profilesFile)) {
            throw new RuntimeException('No se encontró el catálogo de perfiles de instalación.');
        }

        $profiles = require $this->profilesFile;
        if (!is_array($profiles)) {
            throw new RuntimeException('El catálogo de perfiles de instalación no es válido.');
        }

        foreach ($profiles as $slug => &$profile) {
            if (!is_string($slug) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1 || !is_array($profile)) {
                throw new RuntimeException('Existe un perfil de instalación inválido.');
            }
            $profile['slug'] = $slug;
            $profile['database'] = (bool)($profile['database'] ?? false);
            $profile['auth'] = (bool)($profile['auth'] ?? false);
            $profile['tenancy'] = (bool)($profile['tenancy'] ?? false);
            $profile['public'] = (bool)($profile['public'] ?? true);
            $profile['modules'] = array_values(array_unique(array_map('strval', (array)($profile['modules'] ?? []))));
        }
        unset($profile);

        return $profiles;
    }

    /** @return array<string, mixed> */
    public function get(string $slug): array
    {
        $profiles = $this->all();
        if (!isset($profiles[$slug])) {
            throw new InvalidArgumentException("El perfil de instalación {$slug} no existe.");
        }

        return $profiles[$slug];
    }
}
