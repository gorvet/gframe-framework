<?php
namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class CampaignEmailWorkerTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testImmediateEmailStartsAsyncAndFailureKeepsTheQueuedResult(): void
    {
        define('ABSPATH', __DIR__ . '/fixtures/email-worker-project/');
        require dirname(__DIR__) . '/resources/modules/notification-campaigns/application/app/controllers/notification-campaigns/CampaignController.php';
        $controller = new class extends \GFrame\Modules\NotificationCampaigns\Controllers\CampaignController {
            public int $starts = 0;
            public bool $fail = false;
            public function __construct() {}
            public function trigger(array $response, bool $requested): array { return $this->startEmailDelivery($response, $requested); }
            protected function dispatchEmailWorker(): void {
                if ($this->fail) throw new \RuntimeException('Prueba de fallo del worker');
                $this->starts++;
            }
        };
        $success = ['status' => 'success', 'code' => 'campaign_started'];
        self::assertSame($success, $controller->trigger($success, false));
        self::assertSame(['status' => 'error'], $controller->trigger(['status' => 'error'], true));
        self::assertSame(0, $controller->starts);
        self::assertSame('started', $controller->trigger($success, true)['meta']['email_worker']);
        self::assertSame(1, $controller->starts);
        $controller->fail = true;
        $log = ini_get('error_log');
        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        try { $result = $controller->trigger($success, true); }
        finally { ini_set('error_log', (string)$log); }
        self::assertSame('success', $result['status']);
        self::assertSame('failed', $result['meta']['email_worker']);
        self::assertSame('campaign_started', $result['code']);
    }
}
