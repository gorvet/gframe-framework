<?php

return [
    'name' => 'wordpress-headless',
    'type' => 'integration-module',
    'description' => 'Cliente seguro para contenido y taxonomías de WordPress mediante BridgeFrame.',
    'assets' => [
        ['source' => 'public/style.min.css', 'target' => 'vendors/internal/wp/style.min.css'],
        ['source' => 'public/wordpress-theme.css', 'target' => 'vendors/internal/wp/wordpress-theme.css'],
    ],
    'environment' => ['WORDPRESS_HEADLESS_URL', 'WORDPRESS_HEADLESS_TOKEN'],
    'bridgeframe' => ['namespace' => 'bridgeframe/v2', 'contract' => '2.0'],
];
