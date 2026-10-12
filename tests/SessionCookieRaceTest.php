<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SessionCookieRaceTest extends TestCase
{
    public static function workerRoutes(): array
    {
        return [['/worker', true], ['/worker-no-session', false]];
    }

    #[DataProvider('workerRoutes')]
    public function testLatePublicResponseOnlyReplacesTheLoginCookieWhenItStartsASession(string $workerPath, bool $replacesCookie): void
    {
        $root = sys_get_temp_dir() . '/gframe-session-race-' . bin2hex(random_bytes(6));
        mkdir($root . '/sessions', 0775, true);
        $processes = [];
        $worker = null;
        try {
            $ports = [];
            for ($i = 0; $i < 2; $i++) {
                $listener = stream_socket_server('tcp://127.0.0.1:0');
                $address = stream_socket_get_name($listener, false);
                $port = (int)substr($address, strrpos($address, ':') + 1);
                fclose($listener);
                $ports[] = $port;
                $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, dirname(__DIR__) . '/tests/fixtures/session-race.php'], [0 => ['pipe', 'r'], 1 => ['file', $root . '/http.log', 'a'], 2 => ['file', $root . '/http.log', 'a']], $pipes, dirname(__DIR__), array_merge(getenv(), ['GFRAME_SESSION_RACE_ROOT' => $root]));
                self::assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = $process;
                $ready = false;
                for ($attempt = 0; $attempt < 50; $attempt++) {
                    $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $error, $message, 0.1);
                    if (is_resource($socket)) { fclose($socket); $ready = true; break; }
                    usleep(100000);
                }
                self::assertTrue($ready, 'El servidor HTTP de pruebas debe iniciar.');
            }
            $base = 'http://127.0.0.1:' . $ports[0];
            [$seedHeaders, $seedBody] = $this->request($base . '/seed');
            $cookieA = $this->cookie($seedHeaders);
            self::assertNotSame('', $cookieA);
            self::assertSame(0, json_decode($seedBody, true)['user_id']);

            $worker = stream_socket_client('tcp://127.0.0.1:' . $ports[1]);
            stream_set_timeout($worker, 10);
            fwrite($worker, "GET {$workerPath} HTTP/1.1\r\nHost: 127.0.0.1\r\nCookie: {$cookieA}\r\nConnection: close\r\n\r\n");
            for ($attempt = 0; $attempt < 100; $attempt++) {
                clearstatcache();
                if (is_file($root . '/worker-arrived')) break;
                usleep(10000);
            }
            self::assertFileExists($root . '/worker-arrived');
            [$loginHeaders] = $this->request($base . '/login', $cookieA);
            $cookieB = $this->cookie($loginHeaders);
            self::assertNotSame('', $cookieB);
            self::assertNotSame($cookieA, $cookieB);
            file_put_contents($root . '/login-received', 'ready');

            $workerResponse = stream_get_contents($worker);
            self::assertStringContainsString('200 OK', $workerResponse);
            $cookieC = $this->cookie(explode("\r\n", explode("\r\n\r\n", $workerResponse, 2)[0]));
            if ($replacesCookie) {
                self::assertNotSame('', $cookieC);
                self::assertNotSame($cookieA, $cookieC);
                self::assertNotSame($cookieB, $cookieC);
            } else {
                self::assertSame('', $cookieC);
            }
            [, $withLoginCookie] = $this->request($base . '/identity', $cookieB);
            [, $withLateCookie] = $this->request($base . '/identity', $cookieC !== '' ? $cookieC : $cookieB);
            self::assertSame(42, json_decode($withLoginCookie, true)['user_id']);
            self::assertSame($replacesCookie ? 0 : 42, json_decode($withLateCookie, true)['user_id']);
        } finally {
            if (is_resource($worker)) fclose($worker);
            foreach (array_reverse($processes) as $process) { proc_terminate($process); proc_close($process); }
            self::assertStringStartsWith(sys_get_temp_dir() . '/gframe-session-race-', $root);
            foreach (glob($root . '/sessions/*') ?: [] as $file) unlink($file);
            rmdir($root . '/sessions');
            foreach (glob($root . '/*') ?: [] as $file) unlink($file);
            rmdir($root);
        }
    }

    private function request(string $url, string $cookie = ''): array
    {
        $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true, 'header' => $cookie !== '' ? 'Cookie: ' . $cookie : '']]);
        $body = file_get_contents($url, false, $context);
        return [$http_response_header, $body];
    }

    private function cookie(array $headers): string
    {
        foreach ($headers as $header) {
            if (preg_match('/^Set-Cookie:\s*(GFRAME_RACE=[^;]+)/i', $header, $match)) return $match[1];
        }
        return '';
    }
}
