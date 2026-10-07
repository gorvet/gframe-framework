<?php

namespace GFrame\Tests;

use GFrame\Auth\AuthModel;
use GFrame\Auth\RegistrationAdminNotifier;
use GFrame\Config\ConfigRepository;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class RegistrationAdminNotifierTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testRegistrationTargetsOnlyActiveAdministratorsAndDoesNotRepeatAtVerification(): void
    {
        // Expected transport failures must not become subprocess stderr errors in CI.
        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        define('DB_DEFAULT_CONNECTION', 'registration_notice');
        define('DB_CONNECTIONS', ['registration_notice' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        \ORM::disconnect();
        $pdo = \DatabaseManager::connection('registration_notice');
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/database/schema/sqlite/auth.sql'));
        $pdo->exec("INSERT INTO users (email,password,role_id,status) SELECT 'root@example.test','hash',role_id,'verify' FROM roles WHERE slug='superadministrator'");
        $pdo->exec("INSERT INTO users (email,password,role_id,status) SELECT 'blocked@example.test','hash',role_id,'disabled' FROM roles WHERE slug='superadministrator'");
        $pdo->exec("INSERT INTO users (email,password,role_id,status) SELECT 'member@example.test','hash',role_id,'verify' FROM roles WHERE slug='registered'");
        $notifier = new RecordingRegistrationNotifier();
        self::assertCount(1, $notifier->selectedRecipients());
        $auth = new AuthModel(registrationNotifier: $notifier);
        ConfigRepository::replace([]);
        self::assertSame('account_registered', $auth->registerAcount('first@example.test', 'Password-123')['code']);
        self::assertSame([], $notifier->deliveries);
        ConfigRepository::replace(['auth' => ['registration_admin_notice' => ['enabled' => true, 'channels' => ['email', 'inbox', 'email']]]]);
        $registered = $auth->registerAcount('second@example.test', 'Password-123');
        self::assertSame('account_registered', $registered['code']);
        self::assertCount(2, $notifier->deliveries);
        self::assertSame(['email', 'inbox'], array_column($notifier->deliveries, 'channel'));
        self::assertSame(['root@example.test', 'root@example.test'], array_column($notifier->deliveries, 'recipient'));
        self::assertSame('account_verified', $auth->validateAcount($registered['data']['token'])['code']);
        self::assertSame('user_exists', $auth->registerAcount('second@example.test', 'Password-123')['code']);
        self::assertCount(2, $notifier->deliveries);
        self::assertStringNotContainsString($registered['data']['token'], json_encode($notifier->deliveries));
        ConfigRepository::replace(['auth' => ['registration_admin_notice' => ['enabled' => true, 'user_ids' => [3], 'channels' => ['inbox']]]]);
        $notifier->notify(100, 'new@example.test');
        self::assertCount(2, $notifier->deliveries);
        ConfigRepository::replace(['auth' => ['registration_admin_notice' => ['enabled' => true, 'user_ids' => [1], 'channels' => ['email', 'inbox']]]]);
        $notifier->failEmail = true;
        self::assertSame('account_registered', $auth->registerAcount('third@example.test', 'Password-123')['code']);
        self::assertCount(4, $notifier->deliveries);
        self::assertSame('inbox', $notifier->deliveries[3]['channel']);
    }
}

final class RecordingRegistrationNotifier extends RegistrationAdminNotifier
{
    public array $deliveries = [];
    public bool $failEmail = false;

    public function selectedRecipients(): array { return $this->recipients([]); }

    protected function deliver(string $channel, array $recipient, string $email): array
    {
        $this->deliveries[] = ['channel' => $channel, 'recipient' => $recipient['email'], 'registered_email' => $email];
        if ($this->failEmail && $channel === 'email') throw new \RuntimeException('Transport unavailable');
        return ['status' => 'success'];
    }
}
