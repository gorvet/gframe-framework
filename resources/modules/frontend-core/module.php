<?php

return [
    'name' => 'frontend-core',
    'type' => 'internal-ui',
    'description' => 'Utilidades JavaScript comunes de formularios, errores, helpers y paginación.',
    'default' => true,
    'dependencies' => ['jquery'],
    'assets' => [
        ['source' => 'public', 'target' => 'js/core'],
    ],
];
