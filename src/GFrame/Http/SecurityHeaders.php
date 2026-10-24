<?php

namespace GFrame\Http;

use GFrame\Config\ConfigRepository;

final class SecurityHeaders
{
    public static function values(): array
    {
        $policy = (array)ConfigRepository::get('security.headers', []);
        if (($policy['enabled'] ?? true) === false) return [];
        $headers = ['X-Content-Type-Options' => 'nosniff'];
        foreach (['referrer_policy' => ['Referrer-Policy', 'strict-origin-when-cross-origin'],
            'frame_options' => ['X-Frame-Options', null], 'content_security_policy' => ['Content-Security-Policy', null]] as $key => [$name, $default]) {
            $value = $policy[$key] ?? $default;
            if (is_string($value) && $value !== '' && !preg_match('/[\r\n]/', $value)) $headers[$name] = $value;
        }
        $age = max(0, (int)($policy['hsts_max_age'] ?? 0));
        if ($age > 0 && RequestSecurity::isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=' . $age . (!empty($policy['hsts_include_subdomains']) ? '; includeSubDomains' : '');
        }
        return $headers;
    }

    public static function send(): void
    {
        if (headers_sent()) return;
        $existing = array_map(static fn(string $line): string => strtolower(explode(':', $line, 2)[0]), headers_list());
        foreach (self::values() as $name => $value) {
            if (!in_array(strtolower($name), $existing, true)) header($name . ': ' . $value);
        }
    }
}
