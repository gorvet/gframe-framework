<?php

namespace GFrame\Tests;

use GFrame\Notifications\Campaigns\CampaignUserAudience;
use GFrame\Notifications\Campaigns\CampaignPlaceholders;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class CampaignAudienceTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testOnlyVerifiedActiveUsersAreEligibleEvenForManualSelectionAndCron(): void
    {
        define('DB_DEFAULT_CONNECTION', 'audience_test');
        define('DB_CONNECTIONS', ['audience_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/resources/database/schema/sqlite/auth.sql'));
        $insert = $pdo->prepare('INSERT INTO users (email, password, role_id, status) VALUES (?, ?, ?, ?)');
        foreach ([['root@example.test', 1, 'verify'], ['ada@example.test', 2, 'verify'], ['unverified@example.test', 2, 'unverify'], ['disabled@example.test', 2, 'disabled'], ['suspended@example.test', 2, 'suspended']] as [$email, $role, $status]) $insert->execute([$email, 'test-only', $role, $status]);
        $pdo->exec('INSERT INTO tenant_memberships (user_id, tenant_id, role_id, is_active) VALUES (2, 7, 2, 1), (1, 7, 1, 0), (5, 7, 2, 1)');
        $audience = new CampaignUserAudience();
        self::assertCount(2, $audience->users());
        self::assertCount(1, $audience->users(null, [], 'administrators'));
        self::assertSame([], $audience->users(null, [], 'manual'));
        self::assertCount(1, $audience->users(null, [2, 3, 4, 5], 'manual'));
        self::assertCount(1, $audience->users(7));
        self::assertSame([], $audience->users(8));
        $recipients = iterator_to_array($audience->recipients(['scope' => 'manual', 'user_ids' => [2, 3, 4, 5], 'channels' => ['inbox', 'email'], 'site_url' => 'https://example.test/project/']));
        self::assertCount(1, $recipients);
        self::assertSame(['inbox' => '2', 'email' => 'ada@example.test'], $recipients[0]['recipients']);
        self::assertSame('ada', $recipients[0]['variables']['user_name']);
        self::assertSame('Hola ada: ada@example.test', CampaignPlaceholders::render('Hola {{user_name}}: {{user_email}}', $recipients[0]['variables']));
        self::assertSame('https://example.test/project/admin', CampaignPlaceholders::render('{{dashboard_url}}', $recipients[0]['variables']));
        $recipient = ['channel' => 'inbox', 'recipient' => '2', 'variables' => $recipients[0]['variables']];
        self::assertTrue($audience->allows($recipient, 7));
        self::assertFalse($audience->allows($recipient, 8));
        $pdo->exec("UPDATE users SET status = 'disabled' WHERE user_id = 2");
        self::assertFalse($audience->allows($recipient, 7));
        self::assertCount(0, $audience->users(null, [2], 'manual'));
    }
}
