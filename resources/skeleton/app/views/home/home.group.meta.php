<?php

return [
    'metaTags' => [
        'title' => site_name,
        'description' => 'Aplicación construida con GFrame.',
        'ogtype' => 'website',
        'ogsite_name' => site_name,
    ],
    'css' => [
        'public/vendors/external/bootstrap/css/bootstrap.min.css',
        'public/css/app/home.css',
    ],
    'js' => [
        'public/vendors/external/bootstrap/js/bootstrap.bundle.min.js',
        'public/js/app/home/mngnoadmin.js',
    ],
    'schema' => [
        'type' => 'WebSite',
        'siteName' => site_name,
        'title' => site_name,
        'description' => 'Aplicación construida con GFrame.',
    ],
];
