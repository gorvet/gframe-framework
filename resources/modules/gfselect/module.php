<?php

return [
    'name' => 'gfselect',
    'type' => 'internal-ui',
    'description' => 'Selector enriquecido propio de GFrame.',
    'dependencies' => ['bootstrap', 'jquery'],
    'assets' => [
        ['source' => 'public', 'target' => 'vendors/internal/gfselect'],
    ],
];
