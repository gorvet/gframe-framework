<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class CronDocumentationTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testDocumentedHandlerRegistrationControlsAndExecution(): void
    {
        define('DB_DEFAULT_CONNECTION', 'cron_docs');
        define('DB_CONNECTIONS', ['cron_docs' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $root = dirname(__DIR__);
        $pdo = \DatabaseManager::connection();
        foreach (['cron-runner', 'notifications'] as $module) {
            $pdo->exec(file_get_contents($root . '/resources/modules/' . $module . '/database/sqlite.sql'));
        }
        $notifications = new \GFrame\Notifications\NotificationService(new \GFrame\Notifications\NotificationModel());
        $notifications->notify(1, ['title' => 'Expired', 'message' => 'Old notice', 'expires_at' => '-1 day']);
        preg_match_all('/```php\R(.*?)\R```/s', file_get_contents($root . '/docs/cron-runner.md'), $blocks);
        self::assertCount(4, $blocks[1]);
        eval(preg_replace('/^<\?php\s*/', '', $blocks[1][0]));
        eval($blocks[1][1]);
        self::assertSame('cron_task_scheduled', $result['code']);
        eval($blocks[1][1]);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM cron_tasks')->fetchColumn());
        eval($blocks[1][2]);
        self::assertSame('cron_task_paused', $paused['code']);
        self::assertSame('cron_task_resumed', $resumed['code']);
        self::assertSame('cron_task_rescheduled', $changed['code']);
        eval($blocks[1][3]);
        self::assertSame(1, $result['data']['processed']);
        self::assertSame(0, $result['data']['failed']);
        self::assertSame(1, (int)$pdo->query('SELECT is_deleted FROM user_notifications')->fetchColumn());
        self::assertSame('pending', (new \CronDataProvider())->findByKey('maintenance.notifications.cleanup')['status']);
    }

    #[RunInSeparateProcess]
    public function testReturnedErrorAndExceptionBothFailScheduler(): void
    {
        define('DB_DEFAULT_CONNECTION', 'cron_result_docs');
        define('DB_CONNECTIONS', ['cron_result_docs' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        \DatabaseManager::connection()->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/cron-runner/database/sqlite.sql'));
        $service = new \CronTaskService();
        $service->schedule('docs.returned-error', ReturnedCronError::class, gmdate('Y-m-d H:i:s'));
        $service->schedule('docs.thrown-error', ThrownCronError::class, gmdate('Y-m-d H:i:s'));
        $result = (new \CronScheduler())->runDue();
        self::assertSame('success', $result['status']);
        self::assertSame(2, $result['data']['failed']);
        self::assertSame('error', (new \CronDataProvider())->findByKey('docs.returned-error')['status']);
        self::assertSame('error', (new \CronDataProvider())->findByKey('docs.thrown-error')['status']);
    }
}

final class ReturnedCronError extends \Cron
{
    public function handle(array $task = []): array { return ['status' => 'error']; }
}

final class ThrownCronError extends \Cron
{
    public function handle(array $task = []): array { throw new \RuntimeException('Test failure'); }
}
