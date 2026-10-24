<?php

/**
 * Panel web del canal de coordinación entre asistentes.
 *
 * Servidor local (solo lectura/escritura del mismo canal que agent-coord.php).
 * Muestra en vivo los mensajes y las reservas, y permite que la persona escriba.
 *
 * Arrancar:
 *   php -S 127.0.0.1:8787 bin/agent-coord-server.php
 * y abrir http://127.0.0.1:8787
 *
 * Usa el mismo almacén que la CLI (variable GFRAME_AGENT_CHANNEL o el valor por
 * defecto). Delega en agent-coord.php para no duplicar la lógica del canal.
 */

/** Ejecuta la CLI del canal con argumentos como array (sin shell, seguro en Windows). */
function cli(array $args): array
{
    $cmd = array_merge([PHP_BINARY, __DIR__ . '/agent-coord.php'], $args);
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proc)) {
        return ['out' => '', 'err' => 'no se pudo ejecutar la CLI', 'code' => 1];
    }
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    foreach ($pipes as $p) { fclose($p); }
    $code = proc_close($proc);
    return ['out' => (string)$out, 'err' => (string)$err, 'code' => $code];
}

function json_out($value, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/api/state' && $method === 'GET') {
    $res = cli(['status', '--json']);
    $data = json_decode($res['out'], true);
    if ($res['code'] !== 0 || !is_array($data)) {
        json_out(['error' => trim($res['err']) ?: 'No se pudo leer el canal'], 503);
        return true;
    }
    json_out($data);
    return true;
}

if ($path === '/api/say' && $method === 'POST') {
    $body = json_decode((string)file_get_contents('php://input'), true);
    $agent = is_array($body) ? trim((string)($body['agent'] ?? '')) : '';
    $text = is_array($body) ? trim((string)($body['text'] ?? '')) : '';
    if ($agent === '') { $agent = 'usuario'; }
    if ($text === '') { json_out(['ok' => false, 'error' => 'texto vacío'], 400); return true; }
    $res = cli(['say', '--agent=' . $agent, '--text=' . $text]);
    json_out(['ok' => $res['code'] === 0, 'error' => $res['code'] === 0 ? null : trim($res['err'])], $res['code'] === 0 ? 200 : 503);
    return true;
}

if ($path === '/') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/agent-coord-panel.html');
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "No encontrado";
return true;
