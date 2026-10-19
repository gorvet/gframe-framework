<?php
return [
    'name' => 'content-preview', 'type' => 'internal-ui',
    'install_profiles' => ['managed', 'intranet', 'saas'],
    'description' => 'Vista previa privada con la plantilla pública y actualización automática.',
    'dependencies' => ['content-autosave'],
    'runtime' => ['root' => 'application/app', 'namespace' => 'GFrame\\Modules\\ContentPreview'],
    'assets' => [['source' => 'public', 'target' => 'js/app/admin/components']],
];
