<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class HeartbeatTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    public function testRegistryNormalizesChannelConfiguration(): void
    {
        $registry = new \HeartbeatChannelRegistry();
        $registry->register('notifications.inbox', 'Controller@method', [
            'interval_ms' => 100,
            'run_when_hidden' => true,
            'payload' => ['limit' => 10],
        ]);

        self::assertTrue($registry->has('notifications.inbox'));
        self::assertSame(1000, $registry->get('notifications.inbox')['interval_ms']);
        self::assertTrue($registry->get('notifications.inbox')['run_when_hidden']);
        self::assertSame(['limit' => 10], $registry->get('notifications.inbox')['payload']);
    }

    public function testDispatchRunsRegisteredCallableAndReturnsChannels(): void
    {
        $heartbeat = new TestHeartbeatMaster();
        $heartbeat->addChannel('status', [
            'interval_ms' => 60000,
            'payload' => ['resource' => 'orders'],
        ], static function (array $payload, array $context): array {
            return [
                'status' => 'success',
                'data' => [
                    'resource' => $payload['resource'],
                    'visible' => $context['visible'],
                ],
            ];
        });

        $result = $heartbeat->dispatch();

        self::assertSame('success', $result['status']);
        self::assertSame('orders', $result['data']['channels']['status']['data']['resource']);
        self::assertTrue($result['data']['channels']['status']['data']['visible']);
    }

    public function testHiddenClientOnlyRunsAllowedChannels(): void
    {
        $_POST['hb_visible'] = '0';
        $heartbeat = new TestHeartbeatMaster();
        $heartbeat->addChannel('visible.only', ['run_when_hidden' => false], TestHeartbeatMaster::successHandler());
        $heartbeat->addChannel('background', ['run_when_hidden' => true], TestHeartbeatMaster::successHandler());

        $channels = $heartbeat->dispatch()['data']['channels'];

        self::assertArrayNotHasKey('visible.only', $channels);
        self::assertArrayHasKey('background', $channels);
    }

    public function testForceRunsAChannelBeforeItsIntervalExpires(): void
    {
        $heartbeat = new TestHeartbeatMaster();
        $heartbeat->addChannel('session', ['interval_ms' => 300000], TestHeartbeatMaster::successHandler());

        self::assertArrayHasKey('session', $heartbeat->dispatch()['data']['channels']);
        self::assertSame([], $heartbeat->dispatch()['data']['channels']);

        $_POST['hb_force'] = '1';
        self::assertArrayHasKey('session', $heartbeat->dispatch()['data']['channels']);
    }

    public function testInvalidChannelNamesAreIgnored(): void
    {
        $heartbeat = new TestHeartbeatMaster();
        $heartbeat->addChannel('Invalid Channel', [], TestHeartbeatMaster::successHandler());

        self::assertSame([], $heartbeat->dispatch()['data']['channels']);
    }

    public function testHandlerExceptionsBecomeStableErrorContracts(): void
    {
        $heartbeat = new TestHeartbeatMaster();
        $heartbeat->addChannel('failing', [], static function (): array {
            throw new \RuntimeException('No se pudo consultar el canal.', 503);
        });

        $channel = $heartbeat->dispatch()['data']['channels']['failing'];

        self::assertSame('error', $channel['status']);
        self::assertSame('503', $channel['code']);
        self::assertSame('No se pudo consultar el canal.', $channel['message']);
    }

    public function testTraitBuildsCanonicalChannelResponses(): void
    {
        $handler = new class {
            use \HeartbeatChannelTrait;

            public function success(): array
            {
                return $this->hbSuccess(['unread' => 3], ['code' => 'updated']);
            }

            public function error(): array
            {
                return $this->hbError('not_available', 'Canal no disponible.');
            }

            public function limit(int $value): int
            {
                return $this->hbInt(['limit' => $value], 'limit', 10, 1, 40);
            }
        };

        self::assertSame(
            ['status' => 'success', 'data' => ['unread' => 3], 'code' => 'updated'],
            $handler->success()
        );
        self::assertSame(
            ['status' => 'error', 'code' => 'not_available', 'message' => 'Canal no disponible.'],
            $handler->error()
        );
        self::assertSame(40, $handler->limit(100));
        self::assertSame(1, $handler->limit(-5));
    }
}

final class TestHeartbeatMaster extends \HeartbeatMaster
{
    public function addChannel(string $name, array $options, callable $handler): void
    {
        $this->registerChannel($name, $options, $handler);
    }

    public static function successHandler(): callable
    {
        return static fn(): array => ['status' => 'success', 'data' => []];
    }
}
