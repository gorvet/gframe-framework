<?php

$root = dirname(__DIR__);
if ($argc !== 1) {
    if ($argc !== 3 || $argv[1] !== '--root') {
        fwrite(STDERR, "Uso: php bin/lint.php [--root <carpeta-framework>]\n");
        exit(2);
    }
    $root = $argv[2];
}
$root = realpath($root);
if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "La carpeta del framework no existe.\n");
    exit(1);
}
$directories = array_map(static fn(string $directory): string => $root . '/' . $directory,
    ['src', 'bin', 'tests', 'config', 'resources', 'maintenance']);
$files = [];
$failed = [];

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $files[] = $file->getPathname();
    }
}

// Composer publishes this PHP executable without a .php extension.
if (is_file($root . '/bin/gframe-update')) {
    $files[] = $root . '/bin/gframe-update';
}
sort($files);
if ($files === []) {
    fwrite(STDERR, "No se encontraron archivos PHP para comprobar.\n");
    exit(1);
}
foreach ($files as $path) {
    // Pass arguments directly: do not interpret framework paths through a shell.
    $process = proc_open([PHP_BINARY, '-l', $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        $failed[] = $path;
        continue;
    }
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        $failed[] = $path;
    }
}

if ($failed !== []) {
    fwrite(STDERR, "Falló la validación sintáctica:\n- " . implode("\n- ", $failed) . PHP_EOL);
    exit(1);
}

echo "Todos los archivos PHP son válidos." . PHP_EOL;
echo 'Archivos comprobados: ' . count($files) . PHP_EOL;
