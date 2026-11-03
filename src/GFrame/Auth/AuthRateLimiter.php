<?php

namespace GFrame\Auth;

use GFrame\Config\ConfigRepository;
use GFrame\Security\DatabaseRateLimitStore;

/** Reserva ambos cupos antes de bcrypt/correo; no devuelve cupos ya consumidos. */
final class AuthRateLimiter
{
    public function __construct(private readonly ?\PDO $database = null, private readonly ?\Closure $clock = null) {}

    public function reserve(string $operation, string $identity, string $address): array
    {
        if (ConfigRepository::get('auth.rate_limit.enabled', true) === false) return ['status' => 'success'];
        $driver = ConfigRepository::get('auth.rate_limit.driver', 'database');
        if ($driver !== 'database') return $this->unavailable();
        return $this->reserveDatabase($operation, $identity, $address);
    }

    private function reserveDatabase(string $operation, string $identity, string $address): array
    {
        try {
            $mail = in_array($operation, ['register', 'recovery', 'resend'], true);
            $limits = [
                'ip' => max(1, (int)ConfigRepository::get('auth.rate_limit.ip_attempts', 100)),
                'identity' => max(1, (int)ConfigRepository::get($mail ? 'auth.rate_limit.mail_attempts' : 'auth.rate_limit.login_attempts', $mail ? 5 : 10)),
            ];
            $keys = ['ip' => hash('sha256', 'ip:' . $address),
                'identity' => hash('sha256', ($mail ? 'mail:' : $operation . ':') . mb_strtolower(trim($identity), 'UTF-8'))];
            $store = $this->database !== null ? new DatabaseRateLimitStore($this->database) : DatabaseRateLimitStore::configured('auth.rate_limit');
            $retry = $store->reserveAuth($keys, $limits,
                $this->clock !== null ? ($this->clock)() : time(),
                max(1, (int)ConfigRepository::get('auth.rate_limit.window_seconds', 900)));
            return $retry > 0 ? ['status' => 'error', 'code' => 'auth_rate_limited',
                'message' => 'Has realizado varios intentos. Espera antes de volver a intentarlo.', 'data' => ['retry_after' => $retry]] : ['status' => 'success'];
        } catch (\Exception $exception) {
            return $this->unavailable();
        }
    }

    private function unavailable(): array
    {
        error_log('[GFrame Auth] Almacenamiento del limitador no disponible.');
        return ['status' => 'error', 'code' => 'auth_rate_limit_unavailable', 'message' => 'No se puede procesar la solicitud ahora. Inténtalo más tarde.'];
    }
}
