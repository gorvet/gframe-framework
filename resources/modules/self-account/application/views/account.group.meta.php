<?php

return [
    'metaTags' => [
        'title' => 'Mi cuenta - ' . site_name,
        'description' => 'Administra los datos de acceso de tu cuenta.',
        'robots' => 'noindex, nofollow',
    ],
    'css' => [
        'public/vendors/external/bootstrap/css/bootstrap.min.css',
        'public/vendors/internal/gframe-alerts/alertToast.css',
        'public/css/modules/self-account/self-account.css',
    ],
    'js' => [
        'public/vendors/external/jquery/jquery.min.js',
        'public/vendors/external/bootstrap/js/bootstrap.bundle.min.js',
        'public/vendors/external/sweetalert2/sweetalert2.all.min.js',
        'public/vendors/internal/gframe-alerts/alertToast.js',
        'public/js/modules/self-account/self-account.js',
    ],
];
