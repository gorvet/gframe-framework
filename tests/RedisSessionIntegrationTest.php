<?php

namespace GFrame\Tests;

use GFrame\Session\RedisSessionHandler;
use GFrame\Session\SessionRuntime;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class RedisSessionIntegrationTest extends TestCase
{
    private \Redis $redis;
    private string $prefix;

    protected function setUp(): void
    {
        if (getenv('GFRAME_TEST_REDIS') !== '1') self::markTestSkipped('Requiere Redis de pruebas.');
        self::assertTrue(extension_loaded('redis'), 'La integración solicitada requiere phpredis.');
        $this->prefix = 'gframe-test:' . bin2hex(random_bytes(12)) . ':';
        $this->redis = new \Redis();
        self::assertTrue($this->redis->connect(getenv('GFRAME_TEST_REDIS_HOST') ?: '127.0.0.1', (int)(getenv('GFRAME_TEST_REDIS_PORT') ?: 6379), 2));
        define('DB_DEFAULT_CONNECTION', 'redis-test');
        define('DB_CONNECTIONS', ['redis-test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $database = \DatabaseManager::connection();
        self::assertInstanceOf(\PDO::class, $database);
        $database->exec("CREATE TABLE users(user_id INTEGER PRIMARY KEY,status TEXT); INSERT INTO users VALUES(7,'verify'),(8,'verify'),(9,'suspended')");
        SessionRuntime::clearAuthorizationStale();
    }

    protected function tearDown(): void
    {
        if (isset($this->redis) && $this->redis->isConnected()) {
            $keys = $this->redis->keys($this->prefix . '*');
            if ($keys !== []) $this->redis->del($keys);
            $this->redis->close();
        }
        \DatabaseManager::disconnect();
        SessionRuntime::clearAuthorizationStale();
    }

    public function testRevocationAcrossHandlersDoesNotResurrectSessions(): void
    {
        $writer = new RedisSessionHandler($this->redis, $this->prefix, 60);
        foreach (['desktop', 'mobile'] as $id) {
            $writer->register(7, $id);
            self::assertTrue($writer->write($id, $id . '-payload'));
        }
        $writer->register(8, 'other');
        $writer->write('other', 'other-payload');
        $stale = new RedisSessionHandler($this->redis, $this->prefix, 60);
        self::assertSame('desktop-payload', $stale->read('desktop'));
        $revoker = new RedisSessionHandler($this->redis, $this->prefix, 60);
        self::assertSame(2, $revoker->revokeUser(7));
        self::assertTrue($stale->write('desktop', 'late-payload'));
        self::assertTrue($stale->updateTimestamp('desktop', 'late-payload'));
        self::assertFalse($writer->validateId('desktop'));
        self::assertFalse($writer->validateId('mobile'));
        self::assertSame('', $writer->read('desktop'));
        self::assertSame('other-payload', $writer->read('other'));
        $writer->register(7, 'new-login');
        $writer->write('new-login', 'new-payload');
        self::assertSame('new-payload', $writer->read('new-login'));
    }

    public function testBlockingAndAuthorizationVersionsUseRealRedisState(): void
    {
        $handler = new RedisSessionHandler($this->redis, $this->prefix, 60);
        $handler->register(7, 'authorized', ['role_id' => 3, 'role_version' => 1, 'authorization_version' => 1]);
        $handler->write('authorized', 'payload');
        $handler->publishRoleVersion(3, 2);
        self::assertSame('payload', $handler->read('authorized'));
        self::assertTrue(SessionRuntime::authorizationStale());
        SessionRuntime::clearAuthorizationStale();
        $handler->updateAuthorization(7, 'authorized', 3, 2, 1);
        $handler->publishUserAuthorizationVersion(7, 2);
        $handler->read('authorized');
        self::assertTrue(SessionRuntime::authorizationStale());
        SessionRuntime::clearAuthorizationStale();
        $handler->updateAuthorization(7, 'authorized', 3, 2, 2);
        $handler->updateTenantAuthorization(7, 'authorized', 4, 1);
        $handler->publishRoleVersion(4, 2);
        $handler->read('authorized');
        self::assertTrue(SessionRuntime::authorizationStale());
        self::assertSame(1, $handler->revokeUser(7, true));
        try {
            $handler->register(7, 'blocked');
            self::fail('Una cuenta bloqueada no debe registrar una sesión.');
        } catch (\RuntimeException $exception) {
            self::assertSame('La cuenta no admite nuevas sesiones.', $exception->getMessage());
        }
        $handler->allowUser(7);
        $handler->register(7, 'allowed');
        self::assertTrue($handler->validateId('allowed'));
        $this->expectException(\RuntimeException::class);
        $handler->register(9, 'suspended');
    }

    public function testStoredSessionExpiresInRedis(): void
    {
        $handler = new RedisSessionHandler($this->redis, $this->prefix, 2);
        $handler->register(7, 'short');
        $handler->write('short', 'expires');
        self::assertSame('expires', $handler->read('short'));
        $deadline = microtime(true) + 5;
        while ($handler->validateId('short') && microtime(true) < $deadline) usleep(50000);
        self::assertFalse($handler->validateId('short'));
        self::assertSame('', $handler->read('short'));
        self::assertTrue($handler->write('short', 'late'));
        self::assertFalse($handler->validateId('short'));
    }
}
