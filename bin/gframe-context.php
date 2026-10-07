<?php

declare(strict_types=1);

// Read metadata only: do not load Composer, configuration, routes or registrars.
$project = getcwd();
foreach (array_slice($argv, 1) as $argument) {
    if (in_array($argument, ['--help', '-h'], true)) {
        echo "Uso: php bin/gframe-context.php [--project=ruta]\nDevuelve contexto JSON de solo lectura, sin arrancar la aplicación.\n";
        exit(0);
    }
    if (!str_starts_with($argument, '--project=') || substr($argument, 10) === '') {
        fwrite(STDERR, "Argumento inválido. Use --help.\n");
        exit(2);
    }
    $project = substr($argument, 10);
}

$readJson = static function (string $path): array {
    if (!is_file($path)) throw new RuntimeException("No se encontró {$path}.");
    $value = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException("Se esperaba un objeto o lista JSON en {$path}.");
    return $value;
};
$absolute = static fn(string $path): bool => str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1;

try {
    $project = realpath((string)$project);
    if ($project === false || !is_dir($project)) throw new RuntimeException('El directorio de proyecto no existe.');
    $composer = $readJson($project . '/composer.json');
    $framework = ($composer['name'] ?? '') === 'gorvet/gframe';
    $vendor = (string)($composer['config']['vendor-dir'] ?? 'vendor');
    $vendorRoot = $absolute($vendor) ? $vendor : $project . '/' . $vendor;
    $package = $composer;
    $packageRoot = $project;
    if (!$framework) {
        $metadataPath = $vendorRoot . '/composer/installed.json';
        $installed = $readJson($metadataPath);
        $packages = $installed['packages'] ?? $installed;
        if (!is_array($packages)) throw new RuntimeException('La lista de paquetes instalada no es válida.');
        $matches = array_values(array_filter($packages, static fn($item): bool => is_array($item) && ($item['name'] ?? '') === 'gorvet/gframe'));
        if (count($matches) !== 1) throw new RuntimeException('La metadata instalada debe identificar exactamente un paquete gorvet/gframe.');
        $package = $matches[0];
        $installPath = (string)($package['install-path'] ?? '../gorvet/gframe');
        $packageRoot = realpath($absolute($installPath) ? $installPath : dirname($metadataPath) . '/' . $installPath);
        if ($packageRoot === false || ($readJson($packageRoot . '/composer.json')['name'] ?? '') !== 'gorvet/gframe') {
            throw new RuntimeException('La ruta instalada no corresponde al paquete gorvet/gframe.');
        }
    }
    $registryPath = $project . '/storage/gframe-installed.json';
    $registry = is_file($registryPath) ? $readJson($registryPath) : [];
    $modules = $registry['modules'] ?? [];
    if (!is_array($modules) || array_filter($modules, static fn($module): bool => !is_string($module)) !== []) throw new RuntimeException('La lista de módulos registrados no es válida.');
    $skills = [];
    foreach (glob($packageRoot . '/skills/gframe-*/SKILL.md') ?: [] as $path) $skills[] = ['name' => basename(dirname($path)), 'path' => $path];
    usort($skills, static fn(array $left, array $right): int => strcmp($left['name'], $right['name']));
    $extensions = [];
    foreach (['pdo', 'pdo_mysql', 'pdo_sqlite', 'mbstring', 'openssl', 'fileinfo', 'gd', 'dom'] as $extension) $extensions[$extension] = extension_loaded($extension);
    $report = [
        'status' => 'success', 'scope' => $framework ? 'framework' : 'application', 'project' => $project,
        'package' => ['name' => 'gorvet/gframe', 'path' => $packageRoot, 'version' => $package['version'] ?? null, 'reference' => $package['source']['reference'] ?? $package['dist']['reference'] ?? null],
        'autoload' => ['path' => $vendorRoot . '/autoload.php', 'exists' => is_file($vendorRoot . '/autoload.php'), 'executed' => false],
        'installation_registered' => is_file($registryPath), 'installed_modules' => array_values($modules),
        'canonical_skills' => $skills, 'php' => ['version' => PHP_VERSION, 'binary' => PHP_BINARY, 'extensions' => $extensions],
        'application_booted' => false, 'version_evidence' => $framework ? 'package-composer' : 'composer-installed-metadata',
    ];
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Exception $exception) {
    echo json_encode(['status' => 'error', 'code' => 'context_unavailable', 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}
