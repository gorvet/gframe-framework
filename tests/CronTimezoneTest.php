<?php

namespace GFrame\Tests;

use GFrame\Cron\CronRuntime;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CronTimezoneTest extends TestCase
{
    private string $previousTimezone;
    private PDO $database;
    private TimezoneCronDataProvider $tasks;

    protected function setUp(): void
    {
        $this->previousTimezone = date_default_timezone_get();
        $this->database = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->database->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/cron-runner/database/sqlite.sql'));
        TimezoneCronDataProvider::attach($this->database);
        $this->tasks = new TimezoneCronDataProvider();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);
        \ORM::disconnect('cron_timezone');
    }

    public static function overdueDates(): iterable
    {
        foreach (['UTC', 'America/Havana'] as $zone) {
            foreach ([0, 10, 3600, 21600] as $age) {
                yield $zone . '/' . $age => [$zone, $age];
            }
        }
    }

    #[DataProvider('overdueDates')]
    public function testRecurringOverdueTasksRepeatAfterSixtySecondsAndFutureTasksWait(string $zone, int $age): void
    {
        date_default_timezone_set($zone);
        $service = new \CronTaskService($this->tasks);
        self::assertSame('success', $service->schedule('due', TimezoneCronHandler::class, gmdate('Y-m-d H:i:s', time() - $age), [], 60)['status']);
        $future = gmdate('Y-m-d H:i:s', time() + 3600);
        self::assertSame('success', $service->schedule('future', TimezoneCronHandler::class, $future, [], 60)['status']);

        $before = time();
        $result = (new \CronScheduler($this->tasks, new CronRuntime($this->database)))->runDue();
        $after = time();
        self::assertSame(1, $result['data']['processed']);
        self::assertSame(0, $result['data']['failed']);
        $due = $this->tasks->findByKey('due');
        self::assertSame('pending', $due['status']);
        self::assertGreaterThanOrEqual(gmdate('Y-m-d H:i:s', $before + 60), $due['scheduled_at']);
        self::assertLessThanOrEqual(gmdate('Y-m-d H:i:s', $after + 60), $due['scheduled_at']);
        self::assertSame(1, (int)$due['attempts']);
        self::assertSame($future, $this->tasks->findByKey('future')['scheduled_at']);
        self::assertSame(0, (int)$this->tasks->findByKey('future')['attempts']);
        self::assertSame([], $this->tasks->getDueTasks());
        self::assertSame($zone, date_default_timezone_get());
    }

    public static function seasonalDates(): iterable
    {
        // Future reservation snapshots isolate the previous-date branch of max(now, due).
        $transitions = (new \DateTimeZone('America/Havana'))->getTransitions(
            strtotime('2037-01-01 UTC'), strtotime('2038-01-01 UTC')
        );
        foreach (array_slice($transitions, 1) as $index => $transition) {
            foreach (['UTC', 'America/Havana'] as $zone) {
                foreach ([-30, 30] as $offset) {
                    yield $zone . '/' . $index . '/' . $offset => [$zone, $transition['ts'] + $offset];
                }
            }
        }
    }

    #[DataProvider('seasonalDates')]
    public function testReservedSnapshotKeepsUtcIntervalAcrossBothSeasonalTransitions(string $zone, int $scheduledAt): void
    {
        date_default_timezone_set($zone);
        self::assertGreaterThan(time(), $scheduledAt);
        $service = new \CronTaskService($this->tasks);
        self::assertSame('success', $service->schedule('seasonal', TimezoneCronHandler::class, gmdate('Y-m-d H:i:s', $scheduledAt), [], 60)['status']);
        // Selection is verified separately against the real provider; here inject a reserved row.
        $this->tasks->reservedSnapshot = [$this->tasks->findByKey('seasonal')];
        $result = (new \CronScheduler($this->tasks, new CronRuntime($this->database)))->runDue();
        self::assertSame(1, $result['data']['processed']);
        self::assertSame(gmdate('Y-m-d H:i:s', $scheduledAt + 60), $this->tasks->findByKey('seasonal')['scheduled_at']);
        self::assertSame($zone, date_default_timezone_get());
    }

    public function testRecoveryAndOneShotTasksPreserveUtcAndExistingContracts(): void
    {
        foreach (['UTC', 'America/Havana'] as $zone) {
            date_default_timezone_set($zone);
            $this->database->exec('DELETE FROM cron_tasks');
            $service = new \CronTaskService($this->tasks);
            self::assertSame('success', $service->schedule('once', TimezoneCronHandler::class, gmdate('Y-m-d H:i:s'))['status']);
            self::assertSame('success', $service->schedule('delayed', TimezoneCronHandler::class, gmdate('Y-m-d H:i:s', time() + 18000), [], 60)['status']);
            self::assertSame('success', $service->reschedule('delayed', gmdate('Y-m-d H:i:s'))['status']);
            $this->database->exec("UPDATE cron_tasks SET status = 'processing', locked_at = '2000-01-01 00:00:00' WHERE task_key = 'delayed'");
            self::assertSame(1, $this->tasks->recoverStale(60));
            $result = (new \CronScheduler($this->tasks, new CronRuntime($this->database)))->runDue();
            self::assertSame(2, $result['data']['processed']);
            self::assertSame('completed', $this->tasks->findByKey('once')['status']);
            self::assertSame('pending', $this->tasks->findByKey('delayed')['status']);
            self::assertSame('invalid_cron_task', $service->reschedule('delayed', 'invalid-date')['code']);
            self::assertSame('invalid_cron_task', $service->schedule('invalid', TimezoneCronHandler::class, ' ')['code']);
            self::assertSame($zone, date_default_timezone_get());
        }
    }
}

final class TimezoneCronDataProvider extends \CronDataProvider
{
    protected $connection = 'cron_timezone';
    public ?array $reservedSnapshot = null;

    public static function attach(PDO $pdo): void { self::$pdo['cron_timezone'] = $pdo; }
    protected function dialect(): \DatabaseDialectInterface { return new \SqliteDialect(); }
    public function reserveDue(int $limit): array { return $this->reservedSnapshot ?? parent::reserveDue($limit); }
}

final class TimezoneCronHandler extends \Cron
{
    public function handle(array $task = []): array { return ['status' => 'success']; }
}
