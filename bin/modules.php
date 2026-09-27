<?php

use GFrame\Modules\ModuleAssetPublisher;
use GFrame\Modules\ModuleCatalog;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'autoload.php';

$catalog = ModuleCatalog::frameworkDefault();
$command = strtolower((string) ($argv[1] ?? 'list'));

if ($command === 'list') {
    foreach ($catalog->all() as $module) {
        $version = isset($module['version']) ? ' ' . $module['version'] : '';
        $default = !empty($module['default']) ? ' predeterminado' : '';
        echo $module['name'] . $version . ' [' . ($module['type'] ?? 'module') . $default . ']' . PHP_EOL;
    }
    exit(0);
}

if ($command === 'publish') {
    $publicPath = (string) ($argv[2] ?? '');
    $modules = array_values(array_filter(array_slice($argv, 3), static fn(string $name): bool => $name !== ''));
    if ($publicPath === '') {
        fwrite(STDERR, "Uso: php bin/modules.php publish <ruta-public> [modulo...]\n");
        exit(1);
    }

    if ($modules === []) {
        $modules = array_column($catalog->defaults(), 'name');
    }

    $result = (new ModuleAssetPublisher($catalog))->publish($modules, $publicPath);
    echo 'Módulos publicados: ' . implode(', ', $result['modules']) . PHP_EOL;
    echo 'Archivos copiados: ' . count($result['files']) . PHP_EOL;
    exit(0);
}

fwrite(STDERR, "Comando desconocido. Use list o publish.\n");
exit(1);
