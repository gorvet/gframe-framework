<?php

return [
    'name' => 'error-pages',
    'type' => 'core-ui',
    'description' => 'Plantillas reutilizables para errores web 403, 404, 500 y 503.',
    'default' => true,
    'dependencies' => ['bootstrap'],
    'runtime' => ['root' => 'application/app', 'namespace' => 'GFrame\\Modules\\ErrorPages', 'templates' => ['error']],
    'assets' => [
        ['source' => 'public', 'target' => 'css/404'],
    ],
];
