<?php

return [
    'name' => 'jquery-ui',
    'type' => 'external-ui',
    'description' => 'Interacciones heredadas de jQuery UI.',
    'dependencies' => ['jquery'],
    'assets' => [
        ['source' => 'public', 'target' => 'vendors/external/jquery-ui'],
    ],
];
