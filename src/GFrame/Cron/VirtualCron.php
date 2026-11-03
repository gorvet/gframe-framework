<?php

namespace GFrame\Cron;

use Throwable;

final class VirtualCron
{
    private static bool $registered = false;

    public static function register(string $channel): void
    {
        if (self::$registered || PHP_SAPI === 'cli' || !in_array($channel, ['web', 'ajax'], true)) return;
        try {
            if (CronRuntime::settings()['mode'] !== 'virtual') return;
        } catch (Throwable $exception) {
            self::logFailure($exception->getMessage());
            return;
        }
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
            elseif ($settings['executor'] === 'http') (new CronHttpLauncher())->launch($token);
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
        try {
            if (!\GFrame\Storage\DatabaseStateStore::configured()->claim('diagnostic', 'cron', 60)) return;
        } catch (\Exception) { /* Los logs siguen disponibles si no hay conexión. */ }
        error_log('[gframe.cron] ' . $message);
    }
}
