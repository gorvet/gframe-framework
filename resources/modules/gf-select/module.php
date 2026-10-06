<?php

return [
    'name' => 'gf-select',
    'default' => true,
    'type' => 'internal-ui',
    'description' => 'Selector enriquecido propio de GFrame.',
    'dependencies' => ['bootstrap'],
    'assets' => [
        ['source' => 'public/gf-select.js', 'target' => 'vendors/internal/gf-select/gf-select.js'],
        ['source' => 'public/gf-select.css', 'target' => 'vendors/internal/gf-select/gf-select.css'],
        // Rutas de compatibilidad para metas de proyectos anteriores.
        ['source' => 'public/gf-select.js', 'target' => 'vendors/internal/gfselect/gf-select.js'],
        ['source' => 'public/gf-select.css', 'target' => 'vendors/internal/gfselect/gf-select.css'],
    ],
];
