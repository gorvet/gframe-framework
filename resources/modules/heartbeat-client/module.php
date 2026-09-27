<?php

return [
    'name' => 'heartbeat-client',
    'type' => 'internal-ui',
    'description' => 'Cliente compartido para heartbeat, sesión y canales activos.',
    'dependencies' => ['jquery'],
    'assets' => [
        ['source' => 'public', 'target' => 'js/core'],
    ],
];
