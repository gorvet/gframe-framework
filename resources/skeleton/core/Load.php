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

\GFrame\Foundation\Bootstrap::boot(ABSPATH);
