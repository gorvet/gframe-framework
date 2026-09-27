<?php

return [
    'name' => 'owl-carousel',
    'type' => 'external-ui',
    'version' => '2.3.4',
    'description' => 'Carrusel tradicional basado en jQuery.',
    'dependencies' => ['jquery'],
    'assets' => [
        ['source' => 'public', 'target' => 'vendors/external/owl.carousel'],
    ],
];
