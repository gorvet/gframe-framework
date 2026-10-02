<?php

namespace GFrame\Tests;

use GFrame\Notifications\Campaigns\AutomaticCampaignModel;
use GFrame\Notifications\Campaigns\AutomaticCampaignDispatcher;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AutomaticCampaignsTest extends TestCase
{
    public function testCooldownMigrationUpgradesExistingRulesWithoutLosingTheirContent(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $directory = dirname(__DIR__) . '/resources/modules/notification-campaigns/database/migrations/sqlite/';
        $pdo->exec((string)file_get_contents($directory . '202609300001_notification_campaign_rules.sql'));
        $pdo->exec("INSERT INTO notification_campaign_rules (scope_id, rule_key, is_active, title, message, updated_at) VALUES (0, 'account_suspended', 1, 'Original', 'Contenido original', '2026-09-30 00:00:00')");
        $pdo->exec((string)file_get_contents($directory . '202609300002_campaign_cooldown.sql'));
        $rule = $pdo->query('SELECT * FROM notification_campaign_rules')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('Original', $rule['title']);
        self::assertSame('Contenido original', $rule['message']);
        self::assertSame(7, (int)$rule['cooldown_days']);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM notification_campaign_deliveries')->fetchColumn());
    }

    #[RunInSeparateProcess]
    public function testRulesPersistAndEventsRespectStateScopeAndDeduplication(): void
    {
        define('DB_DEFAULT_CONNECTION', 'automatic_test');
        define('DB_CONNECTIONS', ['automatic_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        $root = dirname(__DIR__);
        foreach (['resources/database/schema/sqlite/auth.sql', 'resources/modules/notifications/database/sqlite.sql', 'resources/modules/notification-campaigns/database/sqlite.sql'] as $file) $pdo->exec((string)file_get_contents($root . '/' . $file));
        $pdo->exec("INSERT INTO users (email, password, role_id, status) VALUES ('ada@example.test', 'test-only', 2, 'suspended')");
        $rules = new AutomaticCampaignModel();
        self::assertCount(4, $rules->rules());
        self::assertSame('automatic_campaign_disabled', AutomaticCampaignDispatcher::emit('account_suspended', 1, 'suspend-1')['code']);
        $rules->saveRule(0, 'account_suspended', true, 'Hola {{user_name}}', 'Cuenta {{user_email}} suspendida');
        self::assertSame(1, (int)$rules->rules()[0]['is_active']);
        self::assertSame('automatic_campaign_queued', AutomaticCampaignDispatcher::emit('account_suspended', 1, 'suspend-1')['code']);
        self::assertSame('automatic_campaign_suppressed', AutomaticCampaignDispatcher::emit('account_suspended', 1, 'manual-1', [], null, true)['code']);
        $handler = new \GFrame\Notifications\Campaigns\AutomaticCampaignCronHandler();
        self::assertSame(1, $handler->handle()['data']['suppressed']);
        self::assertSame('automatic_campaign_suppressed', AutomaticCampaignDispatcher::emit('account_suspended', 1, 'suspend-1')['code']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM notification_queue')->fetchColumn());
        $job = $pdo->query('SELECT * FROM notification_queue')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('email', $job['channel']);
        $payload = json_decode($job['payload_json'], true);
        self::assertSame('Hola ada', $payload['subject']);
        self::assertSame('Cuenta ada@example.test suspendida', $payload['message']);
        $pdo->exec("UPDATE notification_campaign_deliveries SET queued_at = '2000-01-01 00:00:00'");
        self::assertSame(1, $handler->handle()['data']['queued']);
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM notification_queue')->fetchColumn());
        self::assertSame(1, $handler->handle()['data']['suppressed']);
        $rules->saveRule(0, 'account_suspended', false, 'Manual', 'Aviso', 3);
        self::assertSame('automatic_campaign_disabled', AutomaticCampaignDispatcher::emit('account_suspended', 1, 'disabled-auto')['code']);
        $pdo->exec("UPDATE notification_campaign_deliveries SET queued_at = '2000-01-01 00:00:00'");
        self::assertSame('automatic_campaign_queued', AutomaticCampaignDispatcher::emit('account_suspended', 1, 'manual-2', [], null, true)['code']);
        self::assertSame('automatic_campaign_suppressed', AutomaticCampaignDispatcher::emit('account_suspended', 1, 'manual-3', [], null, true)['code']);
        self::assertSame('manual', $pdo->query('SELECT source FROM notification_campaign_deliveries')->fetchColumn());
        $rules->saveRule(0, 'account_suspended', true, 'Aviso', 'Aviso');
        $rules->saveRule(7, 'account_suspended', true, 'Tenant', 'Aviso');
        self::assertSame('automatic_campaign_ineligible', AutomaticCampaignDispatcher::emit('account_suspended', 1, 'tenant-event', [], 7)['code']);
        $pdo->exec("UPDATE users SET status = 'verify' WHERE user_id = 1");
        self::assertSame('automatic_campaign_ineligible', AutomaticCampaignDispatcher::emit('account_suspended', 1, 'suspend-2')['code']);
        $rules->saveRule(0, 'account_verification', true, 'Verifica', '{{verification_url}}');
        $pdo->exec("UPDATE users SET status = 'unverify' WHERE user_id = 1");
        self::assertSame('verification_url_required', AutomaticCampaignDispatcher::emit('account_verification', 1, 'verify-1')['code']);
        self::assertSame('automatic_campaign_queued', AutomaticCampaignDispatcher::emit('account_verification', 1, 'verify-1', ['verification_url' => 'https://example.test/verify/individual-token'])['code']);
        $pdo->exec("UPDATE notification_campaign_deliveries SET queued_at = '2000-01-01 00:00:00' WHERE rule_key = 'account_verification'");
        self::assertSame('automatic_campaign_queued', AutomaticCampaignDispatcher::emit('account_verification', 1, 'verify-manual', ['site_url' => 'https://example.test/project/'], null, true)['code']);
        $token = $pdo->query('SELECT token FROM users WHERE user_id = 1')->fetchColumn();
        self::assertNotEmpty($token);
        $verificationPayload = json_decode($pdo->query('SELECT payload_json FROM notification_queue ORDER BY notification_id DESC LIMIT 1')->fetchColumn(), true);
        self::assertSame('https://example.test/project/login/verify?v=' . $token, $verificationPayload['variables']['verification_url']);
        self::assertSame('automatic_campaign_suppressed', AutomaticCampaignDispatcher::emit('account_verification', 1, 'verify-again', ['site_url' => 'https://example.test/project/'], null, true)['code']);
        self::assertSame($token, $pdo->query('SELECT token FROM users WHERE user_id = 1')->fetchColumn());
        self::assertSame(1, $handler->handle(['site_url' => 'https://example.test/project/'])['data']['suppressed']);
        $pdo->exec("UPDATE notification_campaign_deliveries SET queued_at = '2000-01-01 00:00:00' WHERE rule_key = 'account_verification'");
        $pdo->exec("CREATE TRIGGER fail_notification BEFORE INSERT ON notification_queue BEGIN SELECT RAISE(ABORT, 'test-only queue failure'); END");
        $previousLog = ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        try {
            self::assertSame('automatic_campaign_failed', AutomaticCampaignDispatcher::emit('account_verification', 1, 'verify-failed', ['site_url' => 'https://example.test/project/'], null, true)['code']);
        } finally {
            ini_set('error_log', (string)$previousLog);
        }
        self::assertSame($token, $pdo->query('SELECT token FROM users WHERE user_id = 1')->fetchColumn());
        self::assertSame('2000-01-01 00:00:00', $pdo->query("SELECT queued_at FROM notification_campaign_deliveries WHERE rule_key = 'account_verification'")->fetchColumn());
        $pdo->exec('DROP TRIGGER fail_notification');
        $rules->saveRule(0, 'account_deletion_reminder', true, 'Cuenta', '{{deletion_date}}');
        $pdo->exec("UPDATE users SET status = 'disabled' WHERE user_id = 1");
        self::assertSame('deletion_policy_required', AutomaticCampaignDispatcher::emit('account_deletion_reminder', 1, 'disabled-1')['code']);
        self::assertSame('automatic_campaign_queued', AutomaticCampaignDispatcher::emit('account_deletion_reminder', 1, 'disabled-1', ['deletion_policy' => 'project-policy', 'deletion_date' => gmdate('Y-m-d H:i:s', time() + 86400)])['code']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $rules->saveRule(0, 'account_suspended', false, 'Desactivada', 'Aviso');
        self::assertSame(0, (int)$rules->rules()[0]['is_active']);
    }

    #[RunInSeparateProcess]
    public function testSeparateViewEscapesContentAndUsesRightAlignedSaveActions(): void
    {
        define('site_url', 'https://example.test/');
        $data = ['data' => ['rules' => [['rule_key' => 'account_suspended', 'name' => 'Cuenta suspendida', 'description' => 'Aviso', 'title' => '<script>', 'message' => '<img>', 'is_active' => 1]]]];
        ob_start();
        include dirname(__DIR__) . '/resources/modules/notification-campaigns/application/app/views/notification-campaigns/automatic.php';
        $html = (string)ob_get_clean();
        self::assertStringContainsString('<h1>Campañas automáticas</h1>', $html);
        self::assertStringContainsString('<span class="d-block">Avisos de cuenta por correo</span>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('modal-footer justify-content-end', $html);
        self::assertStringContainsString('data-bs-target="#edit-account_suspended"', $html);
        self::assertStringContainsString('data-automatic-send="account_suspended"', $html);
        self::assertStringContainsString('btn btn-outline-secondary btn-list-actions btn-sm', $html);
        self::assertStringContainsString('btn btn-outline-primary btn-sm', $html);
        self::assertStringNotContainsString('id="send-account_suspended"', $html);
        self::assertStringNotContainsString('/automatic/history', $html);
        self::assertStringContainsString('<table', $html);
        self::assertStringContainsString('name="cooldown_days"', $html);
        self::assertStringContainsString('/campaigns/automatic/save', $html);
        self::assertStringNotContainsString('<img>', $html);
    }
}
