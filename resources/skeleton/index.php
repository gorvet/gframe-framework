<?php

require_once __DIR__ . '/core/Load.php';

ErrorHandler::register();
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

session_name(session_name);
session_start();

if (defined('SEO_ALLOW_INDEXING') && !SEO_ALLOW_INDEXING && !headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
}

$render = new Render();
ErrorHandler::setRender($render);
(new Router($render))->route();
