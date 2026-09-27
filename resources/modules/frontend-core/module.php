<?php

return [
    'name' => 'frontend-core',
    'type' => 'internal-ui',
    'description' => 'Utilidades JavaScript comunes de formularios, errores, paginación, tablas y Markdown.',
    'default' => true,
    'dependencies' => ['jquery'],
    'assets' => [
        ['source' => 'public', 'target' => 'js/core'],
    ],
];
