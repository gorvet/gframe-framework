<?php

return [
    'static' => [
        'name' => 'Sitio sin base de datos',
        'description' => 'Landing, documentación o sitio público sin administración.',
        'database' => false,
        'auth' => false,
        'tenancy' => false,
        'modules' => [],
    ],
    'managed' => [
        'name' => 'Aplicación administrada',
        'description' => 'Aplicación con base de datos, autenticación y administración global.',
        'database' => true,
        'auth' => true,
        'tenancy' => false,
        'modules' => ['user-admin', 'media-library'],
    ],
    'saas' => [
        'name' => 'Aplicación SaaS',
        'description' => 'Aplicación multitenant con una base de datos compartida.',
        'database' => true,
        'auth' => true,
        'tenancy' => true,
        'modules' => ['user-admin', 'media-library', 'notifications'],
    ],
];
