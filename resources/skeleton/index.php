<?php

require_once __DIR__ . '/core/Load.php';

ErrorHandler::register();
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

\GFrame\Session\SessionRuntime::start(ABSPATH);

if (defined('SEO_ALLOW_INDEXING') && !SEO_ALLOW_INDEXING && !headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
}

$render = new Render();
ErrorHandler::setRender($render);
(new Router($render))->route();
