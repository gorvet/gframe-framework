<?php

return [
    'name' => 'jquery-ui',
    'homepage' => 'https://jqueryui.com/',
    'type' => 'external-ui',
    'description' => 'Componentes para arrastrar, reordenar y redimensionar elementos.',
    'dependencies' => ['jquery'],
    'assets' => [
        ['source' => 'public', 'target' => 'vendors/external/jquery-ui'],
    ],
];
