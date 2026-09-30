<?php

return [
    'name' => 'password-utils',
    'type' => 'backend-module',
    'description' => 'Política de contraseña y utilidades visuales propias de GFrame.',
    'dependencies' => ['jquery'],
    'assets' => [
        ['source' => 'public', 'target' => 'vendors/internal/passwordUtils'],
    ],
];
