<?php
declare(strict_types=1);

// Prueba manual optativa: no guarda credenciales ni modifica el proyecto origen.
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__, 2) . '/packages/autoload.php';
if (count($argv) !== 5 || $argv[1] !== '--send' || !filter_var($argv[3], FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php tests/fixtures/smtp-live.php --send directorio-config destinatario 465|587\n");
    exit(2);
}
$port = (int)$argv[4];
if (!in_array($port, [465, 587], true)) exit(2);
Dotenv\Dotenv::createImmutable($argv[2])->safeLoad();
$_ENV['MAIL_PORT'] = (string)$port;
$_ENV['MAIL_ENCRYPTION'] = $port === 465 ? 'ssl' : 'tls';
$started = microtime(true);
$result = (new GFrame\Mail\MailService())->sendHtml(
    $argv[3],
    "GFrame: prueba SMTP {$port}",
    "<p>Prueba del transporte de correo de GFrame por el puerto {$port}.</p>",
    ['timeout' => 15]
);
echo json_encode(['port' => $port, 'status' => $result['status'], 'code' => $result['code'],
    'seconds' => round(microtime(true) - $started, 2)], JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(($result['status'] ?? '') === 'success' ? 0 : 1);
