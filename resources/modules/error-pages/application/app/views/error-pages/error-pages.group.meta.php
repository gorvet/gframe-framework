<?php
$actionName = 'Error ' . (string)($routeParams['actionName'] ?? '');
  
return [

    // Meta etiquetas para SEO
    'metaTags' =>  [ 
            'title' => trim($actionName) . ' - ' . site_name,
            'description' => 'No fue posible mostrar la página solicitada.',
            'robots' => 'noindex, nofollow',
            'tolink' => isset($routeParams['tolink']) ? $routeParams['tolink'] : '',
            'infoMsg' =>isset($routeParams['infoMsg']) ? $routeParams['infoMsg'] : '',
        ],

    // Estilos CSS necesarios para esta vista
    'css' => ['public/css/404/404.css'],

    // Scripts JS necesarios
    'js' => [],

    
];
