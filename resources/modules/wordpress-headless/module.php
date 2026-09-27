<?php

return [
    'name' => 'wordpress-headless',
    'type' => 'integration-module',
    'description' => 'Cliente seguro para contenido y taxonomías de WordPress mediante BridgeFrame.',
    'dependencies' => ['wordpress-styles'],
    'environment' => ['WORDPRESS_HEADLESS_URL', 'WORDPRESS_HEADLESS_TOKEN'],
];
