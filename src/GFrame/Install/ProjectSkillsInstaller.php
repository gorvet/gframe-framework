<?php

declare(strict_types=1);

namespace GFrame\Install;

use RuntimeException;

/** Instala instrucciones locales sin ejecutar autoload, configuración ni la aplicación. */
final class ProjectSkillsInstaller
{
    public function install(string $project, string $target = 'All', bool $dryRun = false): array
    {
        if (!in_array($target, ['Codex', 'Claude', 'All'], true)) {
            throw new RuntimeException('Cliente de skills no admitido.');
        }
        $project = $this->directory($project);
        $composer = $this->json($project . '/composer.json');
        $package = $composer;
        $packageRoot = $project;
        $standalone = ($composer['name'] ?? '') === 'gorvet/gframe';
        if (!$standalone) {
            $vendor = (string)($composer['config']['vendor-dir'] ?? 'vendor');
            $vendorRoot = $this->directory($this->absolute($vendor) ? $vendor : $project . '/' . $vendor);
            $installed = $this->json($vendorRoot . '/composer/installed.json');
            $packages = $installed['packages'] ?? $installed;
            $matches = array_values(array_filter($packages, static fn(array $item): bool => ($item['name'] ?? '') === 'gorvet/gframe'));
            if (count($matches) !== 1) {
                throw new RuntimeException('Composer debe identificar exactamente un paquete gorvet/gframe.');
            }
            $package = $matches[0];
            $packageRoot = $this->directory(isset($package['install-path'])
                ? $vendorRoot . '/composer/' . $package['install-path'] : $vendorRoot . '/gorvet/gframe');
        }
        if (($this->json($packageRoot . '/composer.json')['name'] ?? '') !== 'gorvet/gframe') {
            throw new RuntimeException('La ruta instalada no corresponde a GFrame.');
        }
        $sourceRoot = $this->directory($packageRoot . '/skills');
        $desired = [];
        foreach (glob($sourceRoot . '/gframe-*', GLOB_ONLYDIR) ?: [] as $skill) {
            if (!preg_match('/^gframe-[a-z0-9-]+$/D', basename($skill)) || !is_file($skill . '/SKILL.md')) {
                throw new RuntimeException('Skill GFrame incompleta o inválida.');
            }
            $this->collect($skill, basename($skill), $desired);
        }
        if ($desired === []) {
            throw new RuntimeException('El paquete instalado no contiene skills GFrame.');
        }
        ksort($desired);
        $source = ['package' => 'gorvet/gframe', 'path' => $packageRoot, 'version' => $package['version'] ?? null,
            'reference' => $package['source']['reference'] ?? $package['dist']['reference'] ?? null,
            'evidence' => $standalone ? 'standalone-package' : 'composer-installed-metadata'];
        $reports = [];
        foreach ($target === 'All' ? ['Codex', 'Claude'] : [$target] as $client) {
            $clientRoot = $project . ($client === 'Codex' ? '/.agents' : '/.claude');
            $destination = $clientRoot . '/skills';
            $statePath = $clientRoot . '/gframe-skills.json';
            $this->plain($destination);
            $this->plain($statePath);
            // Lock outside the project so preview does not create project files.
            $lock = fopen(sys_get_temp_dir() . '/gframe-skills-' . hash('sha256', strtolower($destination)) . '.lock', 'c');
            if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
                if (is_resource($lock)) fclose($lock);
                throw new RuntimeException('Otra instalación de skills está utilizando este destino.');
            }
            try {
                $previous = [];
                if (is_file($statePath)) {
                    $state = $this->json($statePath);
                    if (($state['schema'] ?? null) !== 1 || ($state['client'] ?? null) !== $client || !is_array($state['files'] ?? null)) {
                        throw new RuntimeException('Registro de skills inválido.');
                    }
                    $previous = $state['files'];
                    foreach ($previous as $relative => $hash) {
                        $this->skillPath($destination, $relative);
                        if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) throw new RuntimeException('Hash de skills inválido.');
                    }
                }
                $unmanaged = [];
                foreach ($desired as $relative => $_) {
                    $name = explode('/', $relative)[0];
                    $registered = array_filter(array_keys($previous), static fn(string $key): bool => str_starts_with($key, $name . '/')) !== [];
                    if (!$registered && file_exists($destination . '/' . $name)) $unmanaged[$name] = true;
                }
                $paths = array_unique(array_merge(array_keys($desired), array_keys($previous)));
                sort($paths);
                $files = $next = [];
                foreach ($paths as $relative) {
                    $path = $this->skillPath($destination, $relative);
                    $current = $this->hash($path);
                    if (isset($desired[$relative])) {
                        $incoming = $desired[$relative]['hash'];
                        if (isset($unmanaged[explode('/', $relative)[0]])) $action = 'conflict';
                        elseif ($current === null) { $action = 'create'; $next[$relative] = $incoming; }
                        elseif (!isset($previous[$relative])) $action = 'conflict';
                        elseif ($current === $incoming) { $action = 'unchanged'; $next[$relative] = $incoming; }
                        elseif ($current === $previous[$relative]) { $action = 'update'; $next[$relative] = $incoming; }
                        else { $action = 'conflict'; $next[$relative] = $previous[$relative]; }
                    } elseif ($current === null) $action = 'forget';
                    elseif ($current === $previous[$relative]) $action = 'remove';
                    else { $action = 'conflict'; $next[$relative] = $previous[$relative]; }
                    $files[] = ['path' => $relative, 'action' => $action, 'current_hash' => $current];
                }
                $json = json_encode(['schema' => 1, 'client' => $client, 'source' => $source, 'files' => (object)$next],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                $changed = !is_file($statePath) || trim((string)file_get_contents($statePath)) !== trim($json);
                if (!$dryRun) {
                    foreach ($files as $file) {
                        if (!in_array($file['action'], ['create', 'update', 'remove'], true)) continue;
                        $path = $this->skillPath($destination, $file['path']);
                        if ($this->hash($path) !== $file['current_hash']) throw new RuntimeException('Un archivo cambió durante la instalación.');
                        if ($file['action'] === 'remove') {
                            if (!unlink($path)) throw new RuntimeException('No se pudo retirar una skill obsoleta.');
                        } else {
                            $incoming = $desired[$file['path']];
                            $this->plain($incoming['path']);
                            if ($this->hash($incoming['path']) !== $incoming['hash']) throw new RuntimeException('La fuente cambió durante la instalación.');
                            $this->mkdir(dirname($path));
                            if (!copy($incoming['path'], $path)) throw new RuntimeException('No se pudo copiar una skill.');
                        }
                    }
                    if ($changed) {
                        $this->mkdir($clientRoot);
                        $this->plain($statePath);
                        if (file_put_contents($statePath, $json) === false) throw new RuntimeException('No se pudo guardar el registro de skills.');
                    }
                }
                $reports[] = ['client' => $client, 'project' => $project, 'source' => $source, 'destination' => $destination,
                    'dry_run' => $dryRun, 'registry_changed' => $changed, 'files' => $files];
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
        return $reports;
    }

    private function collect(string $directory, string $relative, array &$files): void
    {
        $this->plain($directory);
        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $directory . '/' . $name;
            $this->plain($path);
            if (is_dir($path)) $this->collect($path, $relative . '/' . $name, $files);
            else $files[$relative . '/' . $name] = ['path' => $path, 'hash' => $this->hash($path)];
        }
    }

    private function skillPath(string $root, string $relative): string
    {
        if (!preg_match('~^gframe-[a-z0-9-]+/.+~D', $relative)) throw new RuntimeException('Ruta de skill inválida.');
        foreach (explode('/', $relative) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || preg_match('/[\\\\:<>"|?*\x00-\x1f]/', $part) || rtrim($part, ' .') !== $part) {
                throw new RuntimeException('Ruta de skill inválida.');
            }
        }
        $path = $root . '/' . $relative;
        $this->plain($path);
        return $path;
    }

    private function plain(string $path): void
    {
        for ($cursor = $path; ; $cursor = dirname($cursor)) {
            clearstatcache(true, $cursor);
            if (is_link($cursor)) throw new RuntimeException('No se admiten enlaces en la instalación de skills.');
            if (PHP_OS_FAMILY === 'Windows' && file_exists($cursor)) {
                $local = lstat($cursor);
                $resolved = stat($cursor);
                if ($local === false || $resolved === false || $local['dev'] !== $resolved['dev'] || $local['ino'] !== $resolved['ino'] || $local['mode'] !== $resolved['mode']) {
                    throw new RuntimeException('No se admiten junctions en la instalación de skills.');
                }
            }
            if (dirname($cursor) === $cursor) break;
        }
    }

    private function directory(string $path): string
    {
        if (!$this->absolute($path)) $path = getcwd() . '/' . $path;
        $this->plain($path);
        $resolved = realpath($path);
        if ($resolved === false || !is_dir($resolved)) throw new RuntimeException('No se encontró un directorio requerido para las skills.');
        $this->plain($resolved);
        return str_replace('\\', '/', $resolved);
    }

    private function json(string $path): array
    {
        $this->plain($path);
        $text = file_get_contents($path);
        if ($text === false) throw new RuntimeException('No se encontró la metadata de skills/Composer.');
        $value = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new RuntimeException('Metadata de skills inválida.');
        return $value;
    }

    private function hash(string $path): ?string
    {
        if (!file_exists($path)) return null;
        if (!is_file($path)) throw new RuntimeException('Se esperaba un archivo de skills.');
        $hash = hash_file('sha256', $path);
        if ($hash === false) throw new RuntimeException('No se pudo comprobar una skill.');
        return $hash;
    }

    private function mkdir(string $path): void
    {
        $this->plain($path);
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) throw new RuntimeException('No se pudo crear el directorio de skills.');
        $this->plain($path);
    }

    private function absolute(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[a-z]:[\\\\\/]/i', $path) === 1;
    }
}
