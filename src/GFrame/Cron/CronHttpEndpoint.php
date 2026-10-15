<?php

namespace GFrame\Cron;

use Throwable;

final class CronHttpEndpoint
{
    /** Sin sesión, sin parámetros ejecutables y sin credenciales en URLs. */
    public static function handle(array $server): array
    {
        try {
            $settings = CronRuntime::settings();
            if ($settings['executor'] !== 'http') return ['http_code' => 404, 'code' => 'not_found'];
            try { $secret = CronHttpLauncher::secret(); }
            catch (\RuntimeException) { return ['http_code' => 404, 'code' => 'not_found']; }
            if (($server['REQUEST_METHOD'] ?? '') !== 'POST') return ['http_code' => 405, 'code' => 'method_not_allowed'];
            $authorization = $server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
            if (!is_string($authorization) || !hash_equals('Bearer ' . $secret, $authorization)) return ['http_code' => 403, 'code' => 'forbidden'];
            $ticket = $server['HTTP_X_GFRAME_CRON_TICKET'] ?? null;
            if ($settings['mode'] === 'virtual' && (!is_string($ticket) || !preg_match('/^[a-f0-9]{48}$/D', $ticket))) {
                return ['http_code' => 403, 'code' => 'invalid_cron_ticket'];
            }
            $result = CronExecutor::run($settings['batch_limit'], $settings['mode'] === 'virtual' ? $ticket : null);
            $ok = ($result['status'] ?? '') === 'success' && (int)($result['data']['failed'] ?? 0) === 0;
            $skipped = !empty($result['data']['skipped']) || !empty($result['data']['busy']);
            return ['http_code' => $ok ? 200 : 500, 'status' => $ok ? 'success' : 'error', 'code' => $ok ? ($skipped ? 'cron_skipped' : 'cron_processed') : 'cron_failed'];
        } catch (Throwable $exception) {
            error_log('[gframe.cron.http] ' . $exception->getMessage());
            return ['http_code' => 503, 'status' => 'error', 'code' => 'cron_unavailable'];
        }
    }
}
