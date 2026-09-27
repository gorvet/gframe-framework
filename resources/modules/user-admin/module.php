<?php

return [
    'name' => 'user-admin',
    'type' => 'backend-module',
    'description' => 'Administración de usuarios y asignación protegida de roles.',
    'dependencies' => ['alerts', 'frontend-core', 'gfselect'],
    'requires_schema' => ['auth'],
];
