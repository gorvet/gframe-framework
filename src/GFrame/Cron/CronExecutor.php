<?php

namespace GFrame\Cron;

final class CronExecutor
{
    public static function run(int $limit = 10, ?string $token = null): array
    {
        $result = (new \CronScheduler())->runDue($limit, $token, static function (): void {
            foreach (glob(ABSPATH . 'config/cron/*.php') ?: [] as $registration) {
                require_once $registration;
            }
        });
        if (($result['status'] ?? '') !== 'success' || (int)($result['data']['failed'] ?? 0) > 0) {
            error_log('[gframe.cron] ' . (string)($result['message'] ?? 'cron_handlers_failed'));
        }
        return $result;
    }
}
