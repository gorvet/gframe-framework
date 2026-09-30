<?php

return [
    'metaTags' => ['robots' => 'noindex,nofollow'],
    'css' => [
        'public/vendors/external/bootstrap/css/bootstrap.min.css',
        'public/css/variables.css',
        'public/vendors/internal/gframe-icons/style.css',
        'public/vendors/internal/gframe-alerts/alertToast.css',
        'public/css/modules/admin-panel/admin.css',
    ],
    'hjs' => ['public/js/modules/admin-panel/preload.js'],
    'js' => [
        'public/vendors/external/jquery/jquery.min.js',
        'public/vendors/external/bootstrap/js/bootstrap.bundle.min.js',
        'public/vendors/internal/gframe-alerts/alertToast.js',
        'public/js/modules/admin-panel/admin.js',
    ],
];
