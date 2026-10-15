<?php

namespace GFrame\Cron;

use Throwable;

final class VirtualCron
{
    private static bool $registered = false;

    public static function register(string $channel): void
    {
        if (self::$registered || PHP_SAPI === 'cli' || !in_array($channel, ['web', 'ajax'], true)) return;
        self::$registered = true;
        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            self::kick();
        });
    }

    /** El lanzador inyectable permite probar sin efectos externos. Nunca recibe datos HTTP. */
    public static function kick(?CronRuntime $runtime = null, ?callable $launcher = null): array
    {
        $token = null;
        try {
            $settings = CronRuntime::settings();
            if ($settings['mode'] !== 'virtual') return ['status' => 'success', 'code' => 'cron_real'];
            $runtime ??= CronRuntime::frameworkDefault();
            $token = $runtime->reserveLaunch($settings['min_interval']);
            if ($token === null) return ['status' => 'success', 'code' => 'cron_skipped'];
            $limit = $settings['batch_limit'];
            $job = static function () use ($limit, $token): void { CronExecutor::run($limit, $token); };
            if ($launcher !== null) $launcher($job);
            else (new \Async())->create($job);
            return ['status' => 'success', 'code' => 'cron_launched'];
        } catch (Throwable $exception) {
            if ($token !== null && $runtime !== null) {
                try { $runtime->abandonLaunch($token, $exception->getMessage()); } catch (Throwable) { /* El ticket tiene caducidad. */ }
            }
            // Frontera de error de segundo plano: nunca reemplaza la respuesta de la petición.
            self::logFailure($exception->getMessage());
            return ['status' => 'error', 'code' => 'cron_unavailable'];
        }
    }

    private static function logFailure(string $message): void
    {
        // Una marca compartida por host limita los logs incluso cuando falta el esquema.
        $root = defined('ABSPATH') ? (string)ABSPATH : __DIR__;
        $path = sys_get_temp_dir() . '/gframe-cron-error-' . hash('sha256', $root);
        $file = @fopen($path, 'c+');
        if ($file === false) return;
        try {
            if (!flock($file, LOCK_EX | LOCK_NB)) return;
            $last = (int)stream_get_contents($file);
            if (time() - $last < 60) return;
            rewind($file); ftruncate($file, 0); fwrite($file, (string)time());
            error_log('[gframe.cron] ' . $message);
        } finally {
            fclose($file);
        }
    }
}
