<?php

$autoloadCandidates = array_filter([
    $_composer_autoload_path ?? null,
    dirname(__DIR__) . '/packages/autoload.php',
    dirname(__DIR__, 3) . '/autoload.php',
]);

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        break;
    }
}

if (!class_exists(\GFrame\Foundation\Bootstrap::class) || PHP_SAPI !== 'cli') {
    exit(1);
}

$argument = (string)($argv[1] ?? '');

$decoded = base64_decode($argument, true);
$payload = is_string($decoded) ? json_decode($decoded, true) : null;
if (!is_array($payload) || empty($payload['root']) || !preg_match('/^[a-f0-9]{64}$/D', (string)($payload['job_id'] ?? ''))) {
    exit(1);
}

try {
    \GFrame\Foundation\Bootstrap::boot((string)$payload['root']);
    $job = \GFrame\Storage\DatabaseStateStore::configured()->update('async', $payload['job_id'], static fn(array $record): array => $record, true);
    $serializedClosure = base64_decode((string)$job['closure'], true);
    if (!is_string($serializedClosure) || $serializedClosure === '') throw new RuntimeException('La tarea no es válida.');
    $closure = ClosureWrapper::unserialize($serializedClosure);
    $closure();
} catch (Throwable $exception) {
    error_log('[gframe.async] ' . $exception->getMessage());
    exit(1);
}
