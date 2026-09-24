<?php

return [
    'app' => [
        'name' => 'GFrame',
        'environment' => 'production',
        'debug' => false,
        'url' => null,
        'timezone' => 'UTC',
        'language' => 'es',
        'supported_languages' => ['es'],
    ],
    'database' => [
        'default' => 'main',
        'connections' => [],
    ],
    'session' => [
        'name' => null,
    ],
    'tenancy' => [
        'key' => null,
        'table' => null,
    ],
    'seo' => [
        'metricool' => false,
        'allow_indexing' => true,
        'sitemap' => true,
        'robots' => true,
        'llms' => true,
    ],
    'mail' => [
        'host' => '',
        'port' => 465,
        'username' => '',
        'password' => '',
        'encryption' => 'ssl',
        'from' => 'noreply@example.test',
        'from_name' => 'GFrame',
    ],
];
