<?php

final class HttpClient
{
    private function __construct()
    {
    }

    public static function request(array $args): array
    {
        $url = isset($args['url']) ? trim((string)$args['url']) : '';
        if ($url === '') {
            return [
                'ok' => false,
                'status' => 0,
                'headers' => [],
                'body' => '',
                'json' => null,
                'error' => 'URL is required',
            ];
        }

        $method = strtoupper((string)($args['method'] ?? 'GET'));
        $headers = array_values(array_filter((array)($args['headers'] ?? []), 'is_string'));
        $timeout = max(0, (int)($args['timeout'] ?? 300));
        $followLocation = array_key_exists('follow_location', $args) ? (bool)$args['follow_location'] : true;
        $maxRedirects = max(0, (int)($args['max_redirects'] ?? 10));
        $verifyPeer = array_key_exists('verify_peer', $args) ? (bool)$args['verify_peer'] : true;
        $verifyHost = array_key_exists('verify_host', $args) ? (bool)$args['verify_host'] : true;
        $body = $args['body'] ?? null;
        $query = $args['query'] ?? null;

        if (is_array($query) && !empty($query)) {
            $url = self::appendQuery($url, $query);
        }

        if ($method === 'GET' && is_array($body) && !empty($body)) {
            $url = self::appendQuery($url, $body);
            $body = null;
        }

        $payload = null;
        $hasContentType = self::hasHeader($headers, 'Content-Type');

        if ($body !== null && !in_array($method, ['GET', 'DELETE'], true)) {
            if (is_array($body)) {
                $payload = http_build_query($body);
                if (!$hasContentType) {
                    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                }
            } elseif (is_object($body)) {
                $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!$hasContentType) {
                    $headers[] = 'Content-Type: application/json';
                }
            } else {
                $payload = (string)$body;
                if (!$hasContentType) {
                    $headers[] = 'Content-Type: application/json';
                }
            }
        }

        $responseHeaders = [];
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => $maxRedirects,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => $followLocation,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => $verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $verifyHost ? 2 : 0,
            CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$responseHeaders): int {
                $trimmed = trim($headerLine);
                if ($trimmed === '' || strpos($trimmed, ':') === false) {
                    return strlen($headerLine);
                }

                [$name, $value] = explode(':', $trimmed, 2);
                $responseHeaders[trim($name)] = trim($value);
                return strlen($headerLine);
            },
        ]);

        if ($payload !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }

        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        if (!empty($args['user_agent'])) {
            curl_setopt($curl, CURLOPT_USERAGENT, (string)$args['user_agent']);
        }

        $bodyText = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        if ($bodyText === false) {
            $bodyText = '';
        }

        curl_close($curl);

        $decoded = null;
        if ($bodyText !== '') {
            $decoded = json_decode($bodyText);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = null;
            }
        }

        return [
            'ok' => $error === '' && $status >= 200 && $status < 300,
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $bodyText,
            'json' => $decoded,
            'error' => $error,
        ];
    }

    public static function requestCompat(array $args): array
    {
        $response = self::request($args);

        return [
            'response' => $response['json'],
            'httpCode' => $response['status'],
            'error' => $response['error'],
            'body' => $response['body'],
            'ok' => $response['ok'],
        ];
    }

    private static function appendQuery(string $url, array $query): string
    {
        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url . $separator . http_build_query($query);
    }

    private static function hasHeader(array $headers, string $name): bool
    {
        $prefix = strtolower($name) . ':';
        foreach ($headers as $header) {
            if (stripos($header, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
