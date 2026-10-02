<?php

return [
    'name' => 'lexical-search',
    'type' => 'backend-module',
    'description' => 'Búsqueda léxica con normalización, tolerancia a errores y relevancia ponderada.',
    'runtime' => ['root' => 'application/app', 'namespace' => 'GFrame\\Modules\\LexicalSearch'],
    'assets' => [['source' => 'public', 'target' => 'vendors/internal/lexical-search']],
];
