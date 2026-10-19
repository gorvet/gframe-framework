<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/public/')) {
    $file = realpath(__DIR__ . $path);
    if ($file && str_starts_with($file, realpath(__DIR__ . '/public') . DIRECTORY_SEPARATOR)) return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
