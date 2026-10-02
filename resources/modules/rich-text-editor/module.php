<?php

return [
    'name' => 'rich-text-editor',
    'type' => 'internal-ui',
    'install_profiles' => ['managed', 'intranet', 'saas'],
    'description' => 'Componente TinyMCE reutilizable con limpieza de contenido pegado desde Word.',
    'dependencies' => ['jquery', 'tinymce'],
    'runtime' => ['root' => 'application/app', 'namespace' => 'GFrame\\Modules\\RichTextEditor'],
    'assets' => [
        ['source' => 'public', 'target' => 'js/app/admin/components'],
    ],
];
