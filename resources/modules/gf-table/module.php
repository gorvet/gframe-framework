<?php

return [
    'name' => 'gf-table',
    'default' => true,
    'type' => 'internal-ui',
    'description' => 'Búsqueda y ordenación local de tablas mediante jQuery.',
    'dependencies' => ['jquery'],
    'assets' => [
        ['source' => 'public/gf-table.js', 'target' => 'vendors/internal/gf-table/gf-table.js'],
    ],
];
