<?php

return [
    'name' => 'jquery',
    'type' => 'external-ui',
    'version' => '3.5.1',
    'description' => 'Compatibilidad con los componentes históricos basados en jQuery.',
    'default' => true,
    'assets' => [
        ['source' => 'public', 'target' => 'vendors/external/jquery'],
    ],
];
