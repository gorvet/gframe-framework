<?php

use GFrame\Config\ConfigRepository;
use GFrame\Config\Environment;

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        return ConfigRepository::get($key, $default);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Environment::get($key, $default);
    }
}

if (!function_exists('env_bool')) {
    function env_bool(string $key, bool $default = false): bool
    {
        return Environment::bool($key, $default);
    }
}

if (!function_exists('env_int')) {
    function env_int(string $key, int $default = 0): int
    {
        return Environment::int($key, $default);
    }
}

if (!function_exists('project_path')) {
    function project_path(string $path = ''): string
    {
        $path = trim($path);
        if ($path === '') {
            return defined('ABSPATH') ? rtrim((string)ABSPATH, '\\/') : '';
        }
        if (preg_match('/^[A-Za-z]:[\\\\\/]|^[\\\\\/]/', $path) === 1) {
            return $path;
        }
        $root = defined('ABSPATH') ? rtrim((string)ABSPATH, '\\/') . DIRECTORY_SEPARATOR : '';
        return $root . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
