<?php

// Ejecutor de tareas en segundo plano con closures serializables.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'ClosureWrapper.php';

class Async
{
    protected string $serializedWrapper = '';

    public function create(Closure $closure): void
    {
        if (!defined('ABSPATH')) {
            throw new RuntimeException('GFrame debe iniciarse antes de crear una tarea asíncrona.');
        }

        $this->serializedWrapper = ClosureWrapper::serialize($closure);
        $this->run($this->serializedWrapper);
    }

    public function run(string $serialized): void
    {
        $php = $this->cliBinary();
        $worker = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'async-worker.php');
        if ($worker === false) {
            throw new RuntimeException('No se encontró el worker de tareas asíncronas.');
        }

        $store = \GFrame\Storage\DatabaseStateStore::configured();
        $jobID = bin2hex(random_bytes(32));
        $store->create('async', $jobID, ['closure' => base64_encode($serialized)], time() + 3600);
        $argument = base64_encode(json_encode(['root' => rtrim((string)ABSPATH, '/\\'), 'job_id' => $jobID], JSON_THROW_ON_ERROR));

        $output = [];
        $status = 0;

        if (PHP_OS_FAMILY === 'Windows') {
            $quote = static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'";
            $script = '$ErrorActionPreference = \'Stop\'; Start-Process -FilePath ' . $quote($php)
                . ' -ArgumentList @(' . $quote('"' . $worker . '"') . ', ' . $quote('"' . $argument . '"') . ') -WindowStyle Hidden';
            exec('powershell -NoProfile -NonInteractive -Command ' . escapeshellarg($script), $output, $status);
        } else {
            $command = escapeshellarg($php) . ' ' . escapeshellarg($worker) . ' '
                . escapeshellarg($argument) . ' > /dev/null 2>&1 &';
            exec($command, $output, $status);
        }

        if ($status !== 0) {
            $store->delete('async', $jobID);

            $message = 'No se pudo iniciar la tarea asíncrona. Código: ' . $status;
            if (class_exists(LogHelper::class)) {
                LogHelper::write($message, 'async_errors');
            } else {
                error_log($message);
            }
            throw new RuntimeException($message);
        }
    }

    protected function cliBinary(): string
    {
        $configured = trim((string)($_ENV['GFRAME_PHP_BINARY'] ?? getenv('GFRAME_PHP_BINARY') ?: ''));
        $name = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
        $candidates = $configured !== '' ? [$configured] : array_filter([
            PHP_SAPI === 'cli' ? PHP_BINARY : null,
            PHP_BINDIR . DIRECTORY_SEPARATOR . $name,
            php_ini_loaded_file() ? dirname(php_ini_loaded_file()) . DIRECTORY_SEPARATOR . $name : null,
            PHP_OS_FAMILY === 'Windows' ? dirname(dirname(PHP_BINARY)) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . $name : null,
            ...array_map(static fn(string $directory): string => rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $name,
                array_filter(explode(PATH_SEPARATOR, (string)getenv('PATH')))),
        ]);
        foreach (array_unique($candidates) as $candidate) {
            $path = realpath($candidate);
            if ($path === false || !is_file($path) || !is_executable($path)) continue;
            $output = []; $status = 0;
            exec(escapeshellarg($path) . ' -r ' . escapeshellarg('echo PHP_SAPI;'), $output, $status);
            if ($status === 0 && trim(implode("\n", $output)) === 'cli') return $path;
        }
        throw new RuntimeException('No se encontró PHP CLI. Configura GFRAME_PHP_BINARY con la ruta de su ejecutable.');
    }
}

