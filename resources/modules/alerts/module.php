<?php

return [
    'name' => 'alerts',
    'type' => 'internal-ui',
    'description' => 'API compatible de alertas, confirmaciones, toast y estados de carga.',
    'default' => true,
    'dependencies' => ['bootstrap', 'jquery', 'sweetalert2', 'gframe-icons'],
    'assets' => [
        ['source' => 'public', 'target' => 'vendors/internal/gframe-alerts'],
    ],
];
