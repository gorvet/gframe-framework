<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

$autoload = ABSPATH . 'packages' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('No se encontró packages/autoload.php. Ejecuta composer install.');
}

require_once $autoload;

if (!class_exists(\GFrame\Foundation\Bootstrap::class)) {
    throw new RuntimeException('GFrame no está instalado.');
}

if (PHP_SAPI !== 'cli'
    && !\GFrame\Foundation\Bootstrap::hasProjectConfiguration(ABSPATH)
    && !is_file(ABSPATH . 'storage/gframe-installed.json')
    && is_file(ABSPATH . 'install.php')) {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/.');
    header('Cache-Control: no-store');
    header('Location: /' . ltrim($base . '/install.php', '/'), true, 302);
    exit;
}

\GFrame\Foundation\Bootstrap::boot(ABSPATH);
