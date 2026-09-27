<?php

return [
    'name' => 'wordpress-styles',
    'type' => 'integration-ui',
    'description' => 'Estilos necesarios para mostrar contenido procedente de WordPress headless.',
    'assets' => [
        ['source' => 'public', 'target' => 'vendors/internal/wp'],
    ],
];
