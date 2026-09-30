<?php

return [
    'metaTags' => ['title' => 'Biblioteca multimedia - ' . site_name, 'robots' => 'noindex, nofollow'],
    'css' => [
        'public/vendors/external/bootstrap/css/bootstrap.min.css',
        'public/vendors/internal/gframe-alerts/alertToast.css',
        'public/css/modules/media-library/media-library.css',
    ],
    'js' => [
        'public/vendors/external/jquery/jquery.min.js',
        'public/vendors/external/bootstrap/js/bootstrap.bundle.min.js',
        'public/vendors/external/sweetalert2/sweetalert2.all.min.js',
        'public/vendors/internal/gframe-alerts/alertToast.js',
        'public/js/modules/media-library/media-library.js',
        'public/js/modules/media-library/media-picker.js',
        'public/js/modules/media-library/media-field.js',
    ],
];
