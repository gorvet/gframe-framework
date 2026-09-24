<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Config\Environment;
use GFrame\Foundation\Bootstrap;
use GFrame\Notifications\NotificationBatchProcessor;
use GFrame\Notifications\NotificationQueueWorker;
use PHPUnit\Framework\TestCase;

final class FrameworkSmokeTest extends TestCase
{
    public function testCoreClassesAreAutoloadable(): void
    {
        self::assertTrue(class_exists(Bootstrap::class));
        self::assertTrue(class_exists(\RouteBuilder::class));
        self::assertTrue(class_exists(\DatabaseManager::class));
        self::assertTrue(class_exists(\Middleware::class));
        self::assertTrue(class_exists(\Render::class));
    }

    public function testExternalDependenciesAreManagedByComposer(): void
    {
        self::assertTrue(class_exists(\PHPMailer\PHPMailer\PHPMailer::class));
        self::assertTrue(class_exists(\Hhxsv5\SSE\SSE::class));
        self::assertTrue(class_exists(\Opis\Closure\SerializableClosure::class));
        self::assertTrue(class_exists(\Wamania\Snowball\StemmerFactory::class));
    }

    public function testClosuresCanBeSerializedForBackgroundJobs(): void
    {
        $serialized = serialize(new \Opis\Closure\SerializableClosure(
            static fn(int $value): int => $value * 2
        ));
        $wrapper = unserialize($serialized);

        self::assertInstanceOf(\Opis\Closure\SerializableClosure::class, $wrapper);
        self::assertSame(8, $wrapper->getClosure()(4));
    }

    public function testConfigurationSupportsNestedValuesAndOverrides(): void
    {
        ConfigRepository::replace([
            'app' => ['name' => 'GFrame', 'debug' => false, 'languages' => ['es', 'en']],
            'database' => ['default' => 'main'],
        ]);
        ConfigRepository::merge(['app' => ['debug' => true, 'languages' => ['es']]]);

        self::assertSame('GFrame', ConfigRepository::get('app.name'));
        self::assertTrue(ConfigRepository::get('app.debug'));
        self::assertSame(['es'], ConfigRepository::get('app.languages'));
        self::assertSame('fallback', ConfigRepository::get('app.missing', 'fallback'));
    }

    public function testEnvironmentParsesTypedValues(): void
    {
        $_ENV['GFRAME_TEST_BOOLEAN'] = 'false';
        $_ENV['GFRAME_TEST_INTEGER'] = '42';

        self::assertFalse(Environment::bool('GFRAME_TEST_BOOLEAN', true));
        self::assertSame(42, Environment::int('GFRAME_TEST_INTEGER'));

        unset($_ENV['GFRAME_TEST_BOOLEAN'], $_ENV['GFRAME_TEST_INTEGER']);
    }

    public function testNotificationQueueWorkerNormalizesAndProcessesBatches(): void
    {
        $processor = new class implements NotificationBatchProcessor {
            public function processNotificationBatch(int $batch): array
            {
                return ['status' => 'success', 'processed' => $batch];
            }
        };

        $result = (new NotificationQueueWorker())->run($processor, 2);

        self::assertSame('success', $result['status']);
        self::assertSame(NotificationQueueWorker::MIN_BATCH, $result['batch']);
        self::assertSame(NotificationQueueWorker::MIN_BATCH, $result['processed']);
    }

    public function testNotificationQueueWorkerConvertsFailuresIntoStableResponses(): void
    {
        $processor = new class implements NotificationBatchProcessor {
            public function processNotificationBatch(int $batch): array
            {
                throw new \RuntimeException('queue_failed');
            }
        };

        $result = (new NotificationQueueWorker())->run($processor);

        self::assertSame('error', $result['status']);
        self::assertSame('queue_failed', $result['message']);
        self::assertSame(NotificationQueueWorker::DEFAULT_BATCH, $result['batch']);
    }
}
