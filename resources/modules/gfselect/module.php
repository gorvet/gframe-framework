<?php

return [
    'name' => 'gfselect',
    'default' => true,
    'type' => 'internal-ui',
    'description' => 'Selector enriquecido propio de GFrame.',
    'dependencies' => ['bootstrap'],
    'assets' => [
        ['source' => 'public/gf-select.js', 'target' => 'vendors/internal/gfselect/gf-select.js'],
        ['source' => 'public/gf-select.css', 'target' => 'vendors/internal/gfselect/gf-select.css'],
    ],
];
