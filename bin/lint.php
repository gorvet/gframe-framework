<?php

$root = dirname(__DIR__);
$directories = [$root . '/src', $root . '/bin', $root . '/tests'];
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

        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname());
        exec($command, $output, $status);
        if ($status !== 0) {
            $failed[] = $file->getPathname();
        }
    }
}

if ($failed !== []) {
    fwrite(STDERR, "Falló la validación sintáctica:\n- " . implode("\n- ", $failed) . PHP_EOL);
    exit(1);
}

echo "Todos los archivos PHP son válidos." . PHP_EOL;
