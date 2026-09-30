<?php

declare(strict_types=1);

use GFrame\Install\ProjectScaffolder;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'autoload.php';

$argument = trim((string)($argv[1] ?? ''));
$help = static function (): string {
    return <<<TEXT
Uso: composer new -- <directorio>

Crea un proyecto GFrame en un directorio nuevo o vacío.

Opciones:
  -h, --help    Muestra esta ayuda.

Ejemplo:
  composer new -- ../mi-proyecto
TEXT;
};

if (in_array($argument, ['-h', '--help'], true)) {
    fwrite(STDOUT, $help() . PHP_EOL);
    exit(0);
}

$target = $argument;
if ($target === '') {
    fwrite(STDERR, $help() . PHP_EOL);
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
} catch (Exception $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
