<?php

namespace GFrame\Http;

use GFrame\Config\ConfigRepository;

/** Las cabeceras reenviadas solo son válidas detrás de proxies configurados. */
final class RequestSecurity
{
    public static function trustedProxy(string $address): bool
    {
        $packed = @inet_pton($address);
        if ($packed === false) return false;
        foreach ((array)ConfigRepository::get('security.trusted_proxies', []) as $network) {
            $parts = explode('/', (string)$network, 2);
            $base = @inet_pton($parts[0]);
            if ($base === false || strlen($base) !== strlen($packed)) continue;
            $bits = isset($parts[1]) && ctype_digit($parts[1]) ? (int)$parts[1] : (isset($parts[1]) ? -1 : strlen($base) * 8);
            if ($bits < 0 || $bits > strlen($base) * 8) continue;
            $bytes = intdiv($bits, 8); $remaining = $bits % 8;
            if (substr($base, 0, $bytes) === substr($packed, 0, $bytes)
                && ($remaining === 0 || ((ord($base[$bytes]) ^ ord($packed[$bytes])) & (255 << (8 - $remaining))) === 0)) return true;
        }
        return false;
    }

    public static function clientAddress(): string
    {
        $address = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if (!self::trustedProxy($address)) return filter_var($address, FILTER_VALIDATE_IP) ? $address : 'unknown';
        $chain = explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        foreach (array_reverse($chain) as $candidate) {
            if (!self::trustedProxy($address)) break;
            $candidate = trim($candidate);
            if (!filter_var($candidate, FILTER_VALIDATE_IP)) return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $address = $candidate;
        }
        return $address;
    }

    public static function isSecure(): bool
    {
        if (self::trustedProxy((string)($_SERVER['REMOTE_ADDR'] ?? ''))) {
            $proto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
            if (in_array($proto, ['http', 'https'], true)) return $proto === 'https';
        }
        $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
        return ($https !== '' && $https !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    public static function origin(): string
    {
        $configured = \UrlHelper::canonicalBase();
        if ($configured !== null) return $configured;
        if (defined('site_url') && parse_url((string)constant('site_url'), PHP_URL_HOST)) return (string)constant('site_url');
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if (self::trustedProxy((string)($_SERVER['REMOTE_ADDR'] ?? ''))) {
            $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
            if ($forwarded !== '' && !preg_match('/[\s,\/\\\\@?#]/', $forwarded)) $host = $forwarded;
        }
        return (self::isSecure() ? 'https://' : 'http://') . $host;
    }
}
