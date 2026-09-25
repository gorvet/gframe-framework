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
if (str_starts_with($argument, 'file:')) {
    $jobFile = substr($argument, 5);
    $argument = is_file($jobFile) ? trim((string)file_get_contents($jobFile)) : '';
    if (is_file($jobFile)) {
        @unlink($jobFile);
    }
}

$decoded = base64_decode($argument, true);
$payload = is_string($decoded) ? json_decode($decoded, true) : null;
if (!is_array($payload) || empty($payload['root']) || empty($payload['closure'])) {
    exit(1);
}

\GFrame\Foundation\Bootstrap::boot((string)$payload['root']);

$serializedClosure = base64_decode((string)$payload['closure'], true);
if (!is_string($serializedClosure) || $serializedClosure === '') {
    exit(1);
}

$wrapper = @unserialize($serializedClosure);
if (!$wrapper instanceof \Opis\Closure\SerializableClosure) {
    exit(1);
}

$closure = $wrapper->getClosure();
$closure();
