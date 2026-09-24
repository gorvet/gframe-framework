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
