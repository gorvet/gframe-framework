<?php

namespace GFrame\Tests;

use GFrame\Notifications\Campaigns\AccountDeactivationLifecycle;
use GFrame\Notifications\Campaigns\AccountDeactivationModel;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AccountDeactivationLifecycleTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testRecordedDeactivationNotifiesAndCannotDeleteBeforeDeliveredWarning(): void
    {
        define('DB_DEFAULT_CONNECTION', 'lifecycle_test');
        define('DB_CONNECTIONS', ['lifecycle_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        $root = dirname(__DIR__);
        foreach (['resources/database/schema/sqlite/auth.sql', 'resources/modules/notifications/database/sqlite.sql', 'resources/modules/notification-campaigns/database/sqlite.sql', 'resources/modules/cron-runner/database/sqlite.sql'] as $file) $pdo->exec((string)file_get_contents($root . '/' . $file));
        $pdo->exec("INSERT INTO users (email, password, role_id, status) VALUES ('ada@example.test', 'test-only', 2, 'disabled'), ('legacy@example.test', 'test-only', 2, 'disabled'), ('root@example.test', 'test-only', 1, 'disabled')");
        date_default_timezone_set('America/Havana');
        $lifecycle = new AccountDeactivationLifecycle(60, 72);
        self::assertSame('account_deactivation_registered', $lifecycle->register(1, 'https://example.test')['code']);
        $model = new AccountDeactivationModel();
        $record = $model->pending(1);
        self::assertEqualsWithDelta(time() + 60 * 86400, strtotime($record['delete_at'] . ' UTC'), 2);
        self::assertNotEmpty($record['confirmation_id']);
        $payload = json_decode((string)$pdo->query('SELECT payload_json FROM notification_queue WHERE notification_id = ' . (int)$record['confirmation_id'])->fetchColumn(), true);
        self::assertSame('Tu cuenta ha sido desactivada', $payload['subject']);
        self::assertStringStartsWith("Hola, ada.\n\n", $payload['variables']['message']);
        $html = (new \GFrame\Mail\MailTemplateRegistry($root . '/resources/modules/notifications-email/application/mail'))->render($payload['template'], $payload['variables']);
        self::assertStringContainsString('Hola, ada.', $html);
        self::assertStringContainsString('Todos los derechos reservados.', $html);
        self::assertSame('account_deactivation_ineligible', $lifecycle->register(3, 'https://example.test')['code']);
        self::assertSame('account_deactivation_registered', $lifecycle->register(1, 'https://example.test')['code']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM notification_queue')->fetchColumn());
        $model->change(1, ['delete_at' => gmdate('Y-m-d H:i:s', time() + 48 * 3600)]);
        self::assertSame(1, $lifecycle->process('https://example.test')['data']['queued']);
        $record = $model->pending(1);
        self::assertNotEmpty($record['warning_id']);
        self::assertGreaterThanOrEqual(time() + 72 * 3600 - 2, strtotime($record['delete_at'] . ' UTC'));
        $model->change(1, ['delete_at' => gmdate('Y-m-d H:i:s', time() - 1)]);
        self::assertFalse($model->deleteDueAccount(1, 72));
        $pdo->exec("UPDATE notification_queue SET status = 'sent', sent_at = '" . date('Y-m-d H:i:s', time() - 3600) . "' WHERE notification_id = " . (int)$record['warning_id']);
        self::assertFalse($model->deleteDueAccount(1, 72));
        $pdo->exec("UPDATE notification_queue SET sent_at = '" . date('Y-m-d H:i:s', time() - 73 * 3600) . "' WHERE notification_id = " . (int)$record['warning_id']);
        $pdo->exec('INSERT INTO tenant_memberships (user_id, tenant_id, role_id, is_active) VALUES (1, 9, 2, 1)');
        self::assertTrue($model->deleteDueAccount(1, 72));
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM users WHERE user_id = 1')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM tenant_memberships WHERE user_id = 1')->fetchColumn());
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        self::assertNull($model->pending(2));
        self::assertNull($model->pending(1));
    }

    #[RunInSeparateProcess]
    public function testReactivationCancelsTheRecordedDeletion(): void
    {
        define('DB_DEFAULT_CONNECTION', 'lifecycle_cancel_test');
        define('DB_CONNECTIONS', ['lifecycle_cancel_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        foreach (['resources/database/schema/sqlite/auth.sql', 'resources/modules/notification-campaigns/database/sqlite.sql'] as $file) $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/' . $file));
        $pdo->exec("INSERT INTO users (email, password, role_id, status) VALUES ('active@example.test', 'test-only', 2, 'verify')");
        $model = new AccountDeactivationModel();
        $model->record(1, '2026-01-01 00:00:00', '2026-02-01 00:00:00');
        self::assertSame(1, (new AccountDeactivationLifecycle(60, 72))->process('https://example.test')['data']['cancelled']);
        self::assertNull($model->pending(1));
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    #[RunInSeparateProcess]
    public function testNewDeactivationRestartsRetentionAndDeletionFailureRollsBackRelations(): void
    {
        define('DB_DEFAULT_CONNECTION', 'lifecycle_integrity_test');
        define('DB_CONNECTIONS', ['lifecycle_integrity_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        foreach (['resources/database/schema/sqlite/auth.sql', 'resources/modules/notifications/database/sqlite.sql', 'resources/modules/notification-campaigns/database/sqlite.sql', 'resources/modules/cron-runner/database/sqlite.sql'] as $file) $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/' . $file));
        $pdo->exec("INSERT INTO users (email, password, role_id, status) VALUES ('ada@example.test', 'test-only', 2, 'disabled')");
        $model = new AccountDeactivationModel();
        $model->record(1, '2026-01-01 00:00:00', '2026-02-01 00:00:00');
        self::assertSame('success', (new AccountDeactivationLifecycle(60, 72))->register(1, 'https://example.test', true)['status']);
        self::assertEqualsWithDelta(time() + 60 * 86400, strtotime($model->pending(1)['delete_at'] . ' UTC'), 2);
        $warning = $model->pending(1)['confirmation_id'];
        $model->change(1, ['delete_at' => gmdate('Y-m-d H:i:s', time() - 1), 'warning_id' => $warning]);
        $pdo->exec("UPDATE notification_queue SET status = 'sent', sent_at = '" . gmdate('Y-m-d H:i:s', time() - 73 * 3600) . "'");
        $pdo->exec('INSERT INTO tenant_memberships (user_id, tenant_id, role_id, is_active) VALUES (1, 9, 2, 1)');
        $pdo->exec("CREATE TRIGGER prevent_account_delete BEFORE DELETE ON users BEGIN SELECT RAISE(ABORT, 'test-only dependent resource'); END");
        try {
            $model->deleteDueAccount(1, 72);
            self::fail('The dependent resource must veto account deletion');
        } catch (\Exception $exception) {
            self::assertStringContainsString('test-only dependent resource', $exception->getMessage());
        }
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM tenant_memberships')->fetchColumn());
        self::assertNotNull($model->pending(1));
    }
}
