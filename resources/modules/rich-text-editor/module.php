<?php

return [
    'name' => 'rich-text-editor',
    'type' => 'internal-ui',
    'description' => 'Componente TinyMCE reutilizable con limpieza de contenido pegado desde Word.',
    'dependencies' => ['jquery', 'tinymce'],
    'assets' => [
        ['source' => 'public', 'target' => 'js/app/admin/components'],
    ],
    'application' => [
        ['source' => 'application/views', 'target' => 'app/views/admin/components'],
    ],
];
