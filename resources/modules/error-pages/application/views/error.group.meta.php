<?php

$actionName = 'Error ' . (string)($routeParams['actionName'] ?? '');

return [
    'metaTags' => [
        'title' => trim($actionName) . ' - ' . site_name,
        'description' => 'No fue posible mostrar la página solicitada.',
        'robots' => 'noindex, nofollow',
    ],
    'css' => ['public/css/modules/error-pages/error-pages.css'],
    'js' => [],
];
