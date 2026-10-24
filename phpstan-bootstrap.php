<?php

/**
 * Bootstrap solo para análisis estático (PHPStan).
 *
 * Declara las constantes de ejecución que el framework define en tiempo de
 * arranque (config, bootstrap, router) para que PHPStan no las marque como
 * indefinidas. No se carga en producción ni en pruebas; sus valores son
 * representativos, no la configuración real.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
if (!defined('GFRAME_PATH')) {
    define('GFRAME_PATH', __DIR__ . '/src/');
}
if (!defined('DebugMode')) {
    define('DebugMode', false);
}
if (!defined('site_url')) {
    define('site_url', 'https://example.test/');
}
if (!defined('session_name')) {
    define('session_name', 'GFrame');
}
if (!defined('APP_LANG')) {
    define('APP_LANG', 'es');
}
if (!defined('SUPPORTED_LANGS')) {
    define('SUPPORTED_LANGS', ['es']);
}
if (!defined('TENANT')) {
    define('TENANT', '');
}
if (!defined('TENANT_TABLE')) {
    define('TENANT_TABLE', '');
}
if (!defined('SITEMAP_BUILD')) {
    define('SITEMAP_BUILD', false);
}
if (!defined('SEO_ALLOW_INDEXING')) {
    define('SEO_ALLOW_INDEXING', true);
}
if (!defined('WEBHOOK_DEFAULT_SECRET')) {
    define('WEBHOOK_DEFAULT_SECRET', '');
}
if (!defined('DB_DEFAULT_CONNECTION')) {
    define('DB_DEFAULT_CONNECTION', 'main');
}
if (!defined('DB_CONNECTIONS')) {
    define('DB_CONNECTIONS', []);
}
