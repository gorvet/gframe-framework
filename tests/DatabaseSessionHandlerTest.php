<?php

namespace GFrame\Tests;

use GFrame\Session\DatabaseSessionHandler;
use GFrame\Session\SessionRuntime;
use PDO;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseSessionHandlerTest extends TestCase
{
    private PDO $database;
    private DatabaseSessionHandler $handler;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE roles (role_id INTEGER PRIMARY KEY, security_version INTEGER NOT NULL DEFAULT 1)');
        $this->database->exec("CREATE TABLE users (user_id INTEGER PRIMARY KEY, status TEXT NOT NULL DEFAULT 'verify', authorization_version INTEGER NOT NULL DEFAULT 1)");
        $this->database->exec('INSERT INTO users (user_id) VALUES (7), (8)');
        $this->database->exec('CREATE TABLE gframe_sessions (session_hash TEXT PRIMARY KEY, user_id INTEGER NULL, role_id INTEGER NULL, role_version INTEGER NOT NULL DEFAULT 1, authorization_version INTEGER NOT NULL DEFAULT 1, tenant_role_id INTEGER NULL, tenant_role_version INTEGER NOT NULL DEFAULT 1, payload BLOB NOT NULL, last_activity INTEGER NOT NULL, expires_at INTEGER NOT NULL)');
        $this->handler = new DatabaseSessionHandler($this->database, 1800);
        SessionRuntime::clearAuthorizationStale();
    }

    public function testRevocationRemovesEveryDevice(): void
    {
        $this->handler->register(7, 'desktop');
        $this->handler->write('desktop', 'desktop-data');
        $this->handler->register(7, 'mobile');
        $this->handler->write('mobile', 'mobile-data');
        $this->handler->register(8, 'other');
        $this->handler->write('other', 'other-data');

        self::assertSame(2, $this->handler->revokeUser(7, true));
        self::assertFalse($this->handler->validateId('desktop'));
        self::assertFalse($this->handler->validateId('mobile'));
        self::assertTrue($this->handler->validateId('other'));
    }

    public function testInactiveAccountCannotCreateOrRestoreSessions(): void
    {
        $this->database->exec("UPDATE users SET status = 'suspended' WHERE user_id = 7");
        $this->handler->revokeUser(7, true);

        $this->expectException(RuntimeException::class);
        $this->handler->register(7, 'new-session');
    }

    public function testSecurityRevocationWithoutBlockingAllowsANewLogin(): void
    {
        $this->handler->register(7, 'old-session');
        $this->handler->write('old-session', 'old');
        $this->handler->revokeUser(7);
        $this->handler->register(7, 'new-session');
        $this->handler->write('new-session', 'new');

        self::assertFalse($this->handler->validateId('old-session'));
        self::assertSame('new', $this->handler->read('new-session'));
    }

    public function testRequestStartedBeforeRevocationCannotRestoreItsSession(): void
    {
        $this->handler->register(7, 'stale-session');
        $this->handler->write('stale-session', 'before');

        $this->handler->revokeUser(7);
        self::assertTrue($this->handler->write('stale-session', 'after'));

        self::assertFalse($this->handler->validateId('stale-session'));
    }

    public function testRegistrationCreatesTheOnlyAuthenticatedSessionRow(): void
    {
        $this->handler->register(7, 'login-session');
        self::assertTrue($this->handler->validateId('login-session'));
        self::assertSame(1, (int)$this->database->query('SELECT COUNT(*) FROM gframe_sessions WHERE user_id = 7')->fetchColumn());

        $this->handler->write('login-session', 'first');
        $this->handler->write('login-session', 'second');
        self::assertSame(1, (int)$this->database->query('SELECT COUNT(*) FROM gframe_sessions WHERE user_id = 7')->fetchColumn());
        self::assertSame('second', $this->handler->read('login-session'));
    }

    #[RunInSeparateProcess]
    public function testPhpLoginLifecycleCreatesThenUpdatesOneSessionRow(): void
    {
        session_set_save_handler($this->handler, true);
        self::assertTrue(session_start());
        self::assertTrue(session_regenerate_id(true));
        $_SESSION['auth'] = ['id' => 7];
        $sessionID = session_id();
        $this->handler->register(7, $sessionID);
        $_SESSION['lastActivity'] = time();
        self::assertTrue(session_write_close());

        self::assertTrue($this->handler->validateId($sessionID));
        self::assertSame(1, (int)$this->database->query('SELECT COUNT(*) FROM gframe_sessions WHERE user_id = 7')->fetchColumn());
    }

    public function testConcurrentRequestCannotRestoreALoggedOutSession(): void
    {
        $this->handler->register(7, 'shared-browser');
        $this->handler->write('shared-browser', 'before');
        $otherTab = new DatabaseSessionHandler($this->database, 1800);
        self::assertSame('before', $otherTab->read('shared-browser'));

        $this->handler->unregister(7, 'shared-browser');
        self::assertTrue($otherTab->updateTimestamp('shared-browser', 'after'));
        self::assertFalse($otherTab->validateId('shared-browser'));
    }

    public function testTimestampRefreshDoesNotRewritePayloadOrCreateMissingSession(): void
    {
        $this->handler->register(7, 'timestamp-session');
        $this->handler->write('timestamp-session', 'original');
        self::assertTrue($this->handler->updateTimestamp('timestamp-session', 'ignored'));
        self::assertSame('original', $this->handler->read('timestamp-session'));

        $this->handler->unregister(7, 'timestamp-session');
        self::assertTrue($this->handler->updateTimestamp('timestamp-session', 'ignored'));
        self::assertFalse($this->handler->validateId('timestamp-session'));
    }

    public function testDirectStatusChangeRejectsExistingSession(): void
    {
        $this->handler->register(7, 'active-session');
        $this->handler->write('active-session', 'before');
        $this->database->exec("UPDATE users SET status = 'disabled' WHERE user_id = 7");

        $reader = new DatabaseSessionHandler($this->database, 1800);
        self::assertSame('', $reader->read('active-session'));
        self::assertFalse($reader->validateId('active-session'));
    }

    public function testRoleVersionChangeMarksAuthorizationForRefresh(): void
    {
        $this->database->exec('INSERT INTO roles (role_id, security_version) VALUES (3, 1)');
        $this->handler->register(7, 'authorized-session', ['role_id' => 3, 'role_version' => 1]);
        $this->handler->write('authorized-session', 'payload');
        $this->database->exec('UPDATE roles SET security_version = 2 WHERE role_id = 3');

        $reader = new DatabaseSessionHandler($this->database, 1800);
        self::assertSame('payload', $reader->read('authorized-session'));
        self::assertTrue(SessionRuntime::authorizationStale());
    }

    public function testUserAuthorizationVersionChangeMarksAuthorizationForRefresh(): void
    {
        $this->handler->register(7, 'user-authorization', ['authorization_version' => 1]);
        $this->handler->write('user-authorization', 'payload');
        $this->database->exec('UPDATE users SET authorization_version = 2 WHERE user_id = 7');

        $reader = new DatabaseSessionHandler($this->database, 1800);
        self::assertSame('payload', $reader->read('user-authorization'));
        self::assertTrue(SessionRuntime::authorizationStale());
    }

    public function testTenantRoleVersionChangeMarksAuthorizationForRefresh(): void
    {
        $this->database->exec('INSERT INTO roles (role_id, security_version) VALUES (4, 1)');
        $this->handler->register(7, 'tenant-session', ['authorization_version' => 1]);
        $this->handler->write('tenant-session', 'payload');
        $this->handler->updateTenantAuthorization(7, 'tenant-session', 4, 1);
        $this->database->exec('UPDATE roles SET security_version = 2 WHERE role_id = 4');

        $reader = new DatabaseSessionHandler($this->database, 1800);
        self::assertSame('payload', $reader->read('tenant-session'));
        self::assertTrue(SessionRuntime::authorizationStale());
    }
}
