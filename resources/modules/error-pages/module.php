<?php

return [
    'name' => 'error-pages',
    'type' => 'core-ui',
    'description' => 'Plantillas reutilizables para errores web 403, 404, 500 y 503.',
    'default' => true,
    'dependencies' => ['bootstrap'],
    'assets' => [
        ['source' => 'public', 'target' => 'css/modules/error-pages'],
    ],
    'application' => [
        ['source' => 'application/views', 'target' => 'app/views/error'],
        ['source' => 'application/templates/errorTemplate.php', 'target' => 'app/views/templates/errorTemplate.php'],
    ],
];
