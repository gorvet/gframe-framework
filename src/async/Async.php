<?php

// Ejecutor de tareas en segundo plano basado en Opis Closure.
class Async
{
    public function create(Closure $closure): void
    {
        if (!defined('ABSPATH')) {
            throw new RuntimeException('GFrame debe iniciarse antes de crear una tarea asíncrona.');
        }

        $payload = json_encode([
            'root' => rtrim((string)ABSPATH, '/\\'),
            'closure' => base64_encode(serialize(new \Opis\Closure\SerializableClosure($closure))),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($payload) || $payload === '') {
            throw new RuntimeException('No se pudo preparar la tarea asíncrona.');
        }

        $this->run($payload);
    }

    public function run(string $payload): void
    {
        $worker = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'async-worker.php');
        if ($worker === false) {
            throw new RuntimeException('No se encontró el worker de tareas asíncronas.');
        }

        $encoded = base64_encode($payload);
        $jobFile = null;
        $argument = $encoded;

        if (strlen($encoded) > 6000) {
            $jobFile = tempnam(sys_get_temp_dir(), 'gframe_job_');
            if ($jobFile === false || file_put_contents($jobFile, $encoded, LOCK_EX) === false) {
                throw new RuntimeException('No se pudo crear el archivo temporal de la tarea.');
            }
            $argument = 'file:' . $jobFile;
        }

        $output = [];
        $status = 0;

        if (PHP_OS_FAMILY === 'Windows') {
            $quote = static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'";
            $script = 'Start-Process -FilePath ' . $quote(PHP_BINARY)
                . ' -ArgumentList @(' . $quote($worker) . ', ' . $quote($argument) . ') -WindowStyle Hidden';
            exec('powershell -NoProfile -NonInteractive -Command ' . escapeshellarg($script), $output, $status);
        } else {
            $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($worker) . ' '
                . escapeshellarg($argument) . ' > /dev/null 2>&1 &';
            exec($command, $output, $status);
        }

        if ($status !== 0) {
            if ($jobFile !== null && is_file($jobFile)) {
                @unlink($jobFile);
            }

            $message = 'No se pudo iniciar la tarea asíncrona. Código: ' . $status;
            if (class_exists(LogHelper::class)) {
                LogHelper::write($message, 'async_errors');
            } else {
                error_log($message);
            }
        }
    }
}

