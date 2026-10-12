<?php

// Fixture aislada: dos procesos HTTP comparten únicamente sesiones temporales.
if (PHP_SAPI !== 'cli-server') exit;
require dirname(__DIR__, 2) . '/packages/autoload.php';

$fixtureRoot = (string)getenv('GFRAME_SESSION_RACE_ROOT');
if ($fixtureRoot === '' || !is_dir($fixtureRoot . '/sessions')) exit;
ini_set('session.save_path', $fixtureRoot . '/sessions');
\GFrame\Config\ConfigRepository::replace(['session' => ['driver' => 'native', 'name' => 'GFRAME_RACE']]);
$path = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
define('SUPPORTED_LANGS', ['es', 'en']);
define('site_url', 'http://127.0.0.1/');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$isWorker = in_array($path, ['/worker', '/worker-no-session'], true);

if ($isWorker) {
    // La petición ya llegó con A; su arranque espera a que el cliente reciba B.
    file_put_contents($fixtureRoot . '/worker-arrived', 'ready');
    $deadline = microtime(true) + 5;
    while (!is_file($fixtureRoot . '/login-received') && microtime(true) < $deadline) {
        clearstatcache();
        usleep(10000);
    }
    if (!is_file($fixtureRoot . '/login-received') || !is_file($fixtureRoot . '/login-rotated')) {
        http_response_code(500);
        exit('login barrier timeout');
    }
}

// El router real decide si inicia sesión antes de ejecutar la respuesta.
$route = RouteBuilder::get(ltrim($path, '/'), 'fixture/ResourceController@index');
if ($path === '/worker-no-session') $route->noSession();
$route->registerFinal();
$render = new class extends Render {
    public function __construct() {}
    public function renderView(array $routeParams) {
        global $path, $fixtureRoot, $isWorker;
        if ($path === '/login') {
            (new \GFrame\Auth\SessionManager())->login(['id' => 42, 'email' => 'race@example.test']);
            session_write_close();
            file_put_contents($fixtureRoot . '/login-rotated', 'ready');
            header('Content-Type: application/json');
            echo json_encode(['authenticated' => true]);
        } elseif ($isWorker) {
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            header('Content-Type: application/javascript');
            echo '/* public worker fixture */';
        } else {
            header('Content-Type: application/json');
            echo json_encode(['user_id' => (int)($_SESSION['auth']['id'] ?? 0)]);
            session_write_close();
        }
    }
};
(new Router($render, $fixtureRoot))->route();
