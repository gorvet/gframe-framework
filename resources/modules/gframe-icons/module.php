<?php

return [
    'name' => 'gframe-icons',
    'type' => 'internal-ui',
    'description' => 'Iconos Bootstrap con compatibilidad para las clases gicon y los iconos multicolor originales.',
    'dependencies' => ['bootstrap-icons'],
    'default' => true,
    'assets' => [
        ['source' => 'public', 'target' => 'vendors/internal/gframe-icons'],
    ],
];
