<?php

namespace GFrame\Cron;

use GFrame\Config\Environment;
use RuntimeException;

/** Envío HTTP sin esperar respuesta. No sigue redirects ni acepta destinos de la petición. */
final class CronHttpLauncher
{
    public static function secret(): string
    {
        $secret = (string)Environment::get('GFRAME_CRON_HTTP_SECRET', '');
        if (strlen($secret) < 32 || preg_match('/[^\x21-\x7e]/', $secret)) {
            throw new RuntimeException('Configura GFRAME_CRON_HTTP_SECRET con al menos 32 caracteres ASCII sin espacios.');
        }
        return $secret;
    }

    public function launch(string $ticket): void
    {
        $secret = self::secret();
        $base = rtrim((string)Environment::get('APP_URL', ''), '/');
        $url = parse_url($base);
        if (!is_array($url) || !in_array($url['scheme'] ?? '', ['https', 'http'], true)
            || empty($url['host']) || isset($url['user']) || isset($url['pass'])
            || isset($url['query']) || isset($url['fragment']) || preg_match('/[\r\n]/', $base)) {
            throw new RuntimeException('APP_URL debe ser una URL absoluta sin credenciales, query ni fragmento.');
        }
        $host = $url['host'];
        $port = $url['port'] ?? ($url['scheme'] === 'https' ? 443 : 80);
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => trim($host, '[]'), 'SNI_enabled' => true]]);
        $socket = @stream_socket_client(($url['scheme'] === 'https' ? 'tls' : 'tcp') . '://' . $host . ':' . $port, $error, $message, 2, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) throw new RuntimeException('No se pudo conectar al ejecutor HTTP del cron.');
        try {
            stream_set_timeout($socket, 2);
            $path = rtrim($url['path'] ?? '', '/') . '/gframe-cron.php';
            $request = "POST {$path} HTTP/1.1\r\nHost: {$host}:{$port}\r\nAuthorization: Bearer {$secret}\r\nX-GFrame-Cron-Ticket: {$ticket}\r\nContent-Length: 0\r\nConnection: close\r\n\r\n";
            $offset = 0;
            while ($offset < strlen($request)) {
                $written = fwrite($socket, substr($request, $offset));
                if ($written === false || $written === 0) throw new RuntimeException('No se pudo enviar la solicitud del cron HTTP.');
                $offset += $written;
            }
        } finally {
            fclose($socket);
        }
    }
}
