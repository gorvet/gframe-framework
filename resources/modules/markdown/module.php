<?php

return [
    'name' => 'markdown',
    'type' => 'backend-module',
    'description' => 'Conversión reutilizable entre Markdown y HTML en PHP y JavaScript.',
    'assets' => [
        ['source' => 'public/markdown.js', 'target' => 'vendors/internal/markdown/markdown.js'],
    ],
];
