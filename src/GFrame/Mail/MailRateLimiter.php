<?php

namespace GFrame\Mail;

use Closure;
use GFrame\Config\ConfigRepository;
use GFrame\Config\Environment;
use GFrame\Security\DatabaseRateLimitStore;
use Throwable;

final class MailRateLimiter
{
    public function __construct(private readonly ?\PDO $database = null, private readonly ?Closure $clock = null) {}

    public function run(string $scope, string $identity, Closure $send): array
    {
        $defaults = (require dirname(__DIR__, 3) . '/config/defaults.php')['mail']['rate_limit'];
        $configuration = array_replace($defaults, (array)ConfigRepository::get('mail.rate_limit', []));
        if (!Environment::bool('MAIL_RATE_LIMIT_ENABLED', (bool)$configuration['enabled'])) return $send();
        $maximum = Environment::int('MAIL_RATE_LIMIT_MAX_ATTEMPTS', (int)$configuration['max_attempts']);
        $window = Environment::int('MAIL_RATE_LIMIT_WINDOW_SECONDS', (int)$configuration['window_seconds']);
        if ($maximum < 1 || $window < 1 || trim($scope) === '' || trim($identity) === '') {
            return ['status' => 'error', 'code' => 'mail_rate_limit_invalid'];
        }

        $driver = $configuration['driver'];
        if ($driver !== 'database') return $this->failure();
        return $this->runDatabase($scope, $identity, $maximum, $window, $send);
    }

    private function runDatabase(string $scope, string $identity, int $maximum, int $window, Closure $send): array
    {
        try {
            $store = $this->database !== null ? new DatabaseRateLimitStore($this->database) : DatabaseRateLimitStore::configured('mail.rate_limit');
            $key = hash_hmac('sha256', json_encode([$scope, $identity], JSON_THROW_ON_ERROR), (string)Environment::get('APP_KEY', 'gframe-mail'));
            $reservation = $store->reserveMail($key, $maximum, $window, $this->clock !== null ? ($this->clock)() : time());
        } catch (\Exception $exception) {
            return $this->failure();
        }
        $retry = $reservation['retry'];
        if ($retry > 0) return ['status' => 'error', 'code' => 'mail_rate_limited',
            'message' => 'Has enviado varios mensajes. Inténtalo de nuevo en ' . $retry . ' segundos.',
            'data' => ['retry_after' => $retry]];
        try {
            $result = $send();
        } catch (Throwable $exception) {
            try { $store->releaseMail($reservation['token']); }
            catch (\Exception $releaseException) { error_log('[GFrame Mail] No se pudo devolver el cupo.'); }
            throw $exception;
        }
        if (($result['status'] ?? '') !== 'success') {
            try { $store->releaseMail($reservation['token']); }
            catch (\Exception $exception) { return $this->failure(); }
        }
        return $result;
    }

    private function failure(): array
    {
        return ['status' => 'error', 'code' => 'mail_rate_limit_unavailable'];
    }
}
