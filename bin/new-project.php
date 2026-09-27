<?php

declare(strict_types=1);

use GFrame\Install\ProjectScaffolder;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'autoload.php';

$target = trim((string)($argv[1] ?? ''));
if ($target === '') {
    fwrite(STDERR, "Uso: composer new -- <directorio>\n");
    exit(1);
}

if (is_dir($target)) {
    $contents = array_values(array_diff(scandir($target) ?: [], ['.', '..']));
    if ($contents !== []) {
        fwrite(STDERR, "El directorio de destino debe estar vacío.\n");
        exit(1);
    }
} elseif (!mkdir($target, 0775, true) && !is_dir($target)) {
    fwrite(STDERR, "No se pudo crear el directorio de destino.\n");
    exit(1);
}

try {
    $files = ProjectScaffolder::frameworkDefault()->publish($target, false);
    fwrite(STDOUT, 'Proyecto GFrame creado: ' . count($files) . " archivos.\n");
    fwrite(STDOUT, "Instala sus dependencias y abre public/install/ en el navegador.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
