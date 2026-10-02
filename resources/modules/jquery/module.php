<?php

return [
    'name' => 'jquery',
    'homepage' => 'https://jquery.com/',
    'type' => 'external-ui',
    'version' => '3.5.1',
    'description' => 'Compatibilidad con los componentes históricos basados en jQuery.',
    'default' => true,
    'assets' => [
        ['source' => 'public', 'target' => 'vendors/external/jquery'],
    ],
];
