<?php

namespace GFrame\Tests;

use GFrame\Auth\UserModel;
use PHPUnit\Framework\TestCase;
use PDO;

final class AuthPersistenceTest extends TestCase
{
    private PDO $database;
    private UserModel $users;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite no está disponible.');
        }
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE users (user_id INTEGER PRIMARY KEY, status TEXT, token TEXT, token_updated_at TEXT, last_login TEXT)');
        $this->database->exec("INSERT INTO users (user_id, status, token, token_updated_at) VALUES (1, 'verify', 'old-token', '2000-01-01 00:00:00')");
        $this->users = new class($this->database) extends UserModel {
            public function __construct(private PDO $testDatabase) {}
            protected function pdo(): PDO { return $this->testDatabase; }
        };
    }

    public function testSuspensionAndDeactivationRotateTokensInTheSameWrite(): void
    {
        foreach (['suspended', 'disabled'] as $status) {
            $before = $this->database->query('SELECT token FROM users WHERE user_id = 1')->fetchColumn();
            $this->users->updateAuthUser(1, ['status' => $status, 'token' => 'caller-token']);
            $user = $this->database->query('SELECT * FROM users WHERE user_id = 1')->fetch(PDO::FETCH_ASSOC);
            self::assertSame($status, $user['status']);
            self::assertNotSame($before, $user['token']);
            self::assertNotSame('caller-token', $user['token']);
            self::assertSame(64, strlen($user['token']));
            self::assertNotSame('2000-01-01 00:00:00', $user['token_updated_at']);
        }
    }

    public function testAdministrativeDeactivationInvalidatesTheOldToken(): void
    {
        $this->users->setActive(1, false);
        $user = $this->database->query('SELECT * FROM users WHERE user_id = 1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('disabled', $user['status']);
        self::assertNotSame('old-token', $user['token']);
    }

    public function testExistingUnchangedRowIsSuccessful(): void
    {
        $this->users->updateAuthUser(1, ['status' => 'verify']);
        self::assertSame('verify', $this->database->query('SELECT status FROM users WHERE user_id = 1')->fetchColumn());
    }

    public function testMissingRowDoesNotSilentlySucceed(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->users->updateAuthUser(99, ['status' => 'verify']);
    }
}
