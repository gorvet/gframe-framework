<?php

namespace GFrame\Auth;

use GFrame\Config\ConfigRepository;

/** Reserva ambos cupos antes de bcrypt/correo; no devuelve cupos ya consumidos. */
final class AuthRateLimiter
{
    public function __construct(private readonly ?string $directory = null, private readonly ?\Closure $clock = null) {}

    public function reserve(string $operation, string $identity, string $address): array
    {
        if (ConfigRepository::get('auth.rate_limit.enabled', true) === false) return ['status' => 'success'];
        $directory = $this->directory ?? (defined('ABSPATH') ? rtrim((string)ABSPATH, '/\\') . '/storage/auth-rate' : '');
        if ($directory === '' || (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory))) return $this->unavailable();
        $file = @fopen($directory . '/attempts.json', 'c+');
        if ($file === false) return $this->unavailable();
        try {
            $deadline = microtime(true) + 2;
            while (!flock($file, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) return $this->unavailable();
                usleep(10000);
            }
            if (fstat($file)['size'] > 4194304) return $this->unavailable();
            $content = stream_get_contents($file);
            if ($content === false) return $this->unavailable();
            $state = $content === '' ? [] : json_decode($content, true);
            if (!is_array($state)) return $this->unavailable();
            $now = $this->clock !== null ? ($this->clock)() : time();
            foreach ($state as $key => $bucket) {
                if (!is_array($bucket) || !isset($bucket['expires'], $bucket['count'])
                    || !is_int($bucket['expires']) || !is_int($bucket['count']) || $bucket['count'] < 0) return $this->unavailable();
                if ($bucket['expires'] <= $now) unset($state[$key]);
            }
            $window = max(1, (int)ConfigRepository::get('auth.rate_limit.window_seconds', 900));
            $mail = in_array($operation, ['register', 'recovery', 'resend'], true);
            $limits = [
                'ip' => max(1, (int)ConfigRepository::get('auth.rate_limit.ip_attempts', 100)),
                'identity' => max(1, (int)ConfigRepository::get($mail ? 'auth.rate_limit.mail_attempts' : 'auth.rate_limit.login_attempts', $mail ? 5 : 10)),
            ];
            $keys = ['ip' => hash('sha256', 'ip:' . $address),
                'identity' => hash('sha256', ($mail ? 'mail:' : $operation . ':') . mb_strtolower(trim($identity), 'UTF-8'))];
            $retry = 0;
            foreach ($keys as $kind => $key) {
                if (($state[$key]['count'] ?? 0) >= $limits[$kind]) $retry = max($retry, $state[$key]['expires'] - $now);
            }
            if ($retry === 0) {
                if (count($state) >= 10000) return $this->unavailable();
                foreach ($keys as $key) {
                    $state[$key] ??= ['expires' => $now + $window, 'count' => 0];
                    $state[$key]['count']++;
                }
            }
            $json = json_encode($state, JSON_THROW_ON_ERROR);
            if (!rewind($file) || !ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) return $this->unavailable();
            return $retry > 0 ? ['status' => 'error', 'code' => 'auth_rate_limited',
                'message' => 'Has realizado varios intentos. Espera antes de volver a intentarlo.', 'data' => ['retry_after' => $retry]] : ['status' => 'success'];
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    private function unavailable(): array
    {
        error_log('[GFrame Auth] Almacenamiento del limitador no disponible.');
        return ['status' => 'error', 'code' => 'auth_rate_limit_unavailable', 'message' => 'No se puede procesar la solicitud ahora. Inténtalo más tarde.'];
    }
}
