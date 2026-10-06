<?php

namespace GFrame\Mail;

use Closure;
use GFrame\Config\ConfigRepository;
use GFrame\Config\Environment;
use Throwable;

final class MailRateLimiter
{
    public function __construct(private readonly ?string $directory = null, private readonly ?Closure $clock = null) {}

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

        $directory = $this->directory ?? (defined('ABSPATH') ? rtrim((string)ABSPATH, '/\\') . '/storage/mail-rate' : '');
        if ($directory === '' || (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory))) return $this->failure();
        $key = hash_hmac('sha256', json_encode([$scope, $identity], JSON_THROW_ON_ERROR), (string)Environment::get('APP_KEY', 'gframe-mail'));
        $file = @fopen($directory . '/' . $key . '.json', 'c+');
        if ($file === false) return $this->failure();
        try {
            if (!@flock($file, LOCK_EX)) return $this->failure();
            $now = $this->clock !== null ? ($this->clock)() : time();
            $content = @stream_get_contents($file);
            if ($content === false) return $this->failure();
            $attempts = $content === '' ? [] : json_decode((string)$content, true);
            if (!is_array($attempts) || !array_is_list($attempts)) return $this->failure();
            foreach ($attempts as $attempt) {
                if (!is_int($attempt)) return $this->failure();
            }
            $attempts = array_values(array_filter($attempts, static fn(int $at): bool => $at > $now - $window));
            if (count($attempts) >= $maximum) {
                sort($attempts, SORT_NUMERIC);
                $retry = max(1, $attempts[count($attempts) - $maximum] + $window - $now);
                return ['status' => 'error', 'code' => 'mail_rate_limited',
                    'message' => 'Has enviado varios mensajes. Inténtalo de nuevo en ' . $retry . ' segundos.',
                    'data' => ['retry_after' => $retry]];
            }

            // Reserva el cupo bajo el mismo bloqueo que protege el envío.
            if (!$this->write($file, [...$attempts, $now])) return $this->failure();
            try {
                $result = $send();
            } catch (Throwable $exception) {
                $this->write($file, $attempts);
                throw $exception;
            }
            if (($result['status'] ?? '') !== 'success' && !$this->write($file, $attempts)) return $this->failure();
            return $result;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    private function write($file, array $attempts): bool
    {
        $content = json_encode($attempts, JSON_THROW_ON_ERROR);
        return @rewind($file) && @ftruncate($file, 0) && @fwrite($file, $content) === strlen($content) && @fflush($file);
    }

    private function failure(): array
    {
        return ['status' => 'error', 'code' => 'mail_rate_limit_unavailable'];
    }
}
