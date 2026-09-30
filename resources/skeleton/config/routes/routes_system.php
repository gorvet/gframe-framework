<?php

use RouteBuilder as Route;

if (defined('SEO_ALLOW_INDEXING') && SEO_ALLOW_INDEXING) {
    if (!defined('SEO_ENABLE_SITEMAP_XML') || SEO_ENABLE_SITEMAP_XML) {
        Route::get('sitemap.xml', 'seo/Sitemap@index')
            ->template('raw')
            ->view('raw')
            ->context(['sitemap' => ['include' => false, 'build' => true]])
            ->registerFinal();
    }

    if (!defined('SEO_ENABLE_LLMS_TXT') || SEO_ENABLE_LLMS_TXT) {
        Route::get('llms.txt', 'seo/Llms@index')
            ->template('raw')
            ->view('raw')
            ->context(['sitemap' => ['include' => false, 'build' => false]])
            ->registerFinal();
    }
}

if (!defined('SEO_ENABLE_ROBOTS_TXT') || SEO_ENABLE_ROBOTS_TXT) {
    Route::get('robots.txt', 'seo/Robots@index')
        ->template('raw')
        ->view('raw')
        ->context(['sitemap' => ['include' => false, 'build' => false]])
        ->registerFinal();
}
