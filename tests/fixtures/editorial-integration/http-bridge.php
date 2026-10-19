<?php
// PHP loopback transport for hosts where Node/Chrome cannot open local sockets.
$request = json_decode(base64_decode($argv[1]), true, flags: JSON_THROW_ON_ERROR);
if (parse_url($request['url'], PHP_URL_HOST) !== '127.0.0.1') throw new RuntimeException('Solo loopback de pruebas.');
$headers = [];
foreach ($request['headers'] as $key => $value) {
    if (!in_array(strtolower($key), ['host', 'connection', 'content-length', 'accept-encoding'], true)) $headers[] = $key . ': ' . $value;
}
$context = stream_context_create(['http' => ['method' => $request['method'], 'header' => implode("\r\n", $headers), 'content' => $request['body'] ?? '', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15]]);
$body = file_get_contents($request['url'], false, $context);
if ($body === false) throw new RuntimeException('Falló HTTP de loopback.');
preg_match('/\s(\d{3})\s/', $http_response_header[0], $status);
$responseHeaders = [];
foreach (array_slice($http_response_header, 1) as $line) {
    [$key, $value] = explode(':', $line, 2);$key = strtolower($key);
    if (in_array($key, ['transfer-encoding', 'content-length', 'connection'], true)) continue;
    $responseHeaders[$key] = isset($responseHeaders[$key]) ? $responseHeaders[$key] . "\n" . trim($value) : trim($value);
}
echo json_encode(['status' => (int)$status[1], 'headers' => $responseHeaders, 'body' => base64_encode($body)], JSON_THROW_ON_ERROR);
