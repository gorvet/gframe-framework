<?php

namespace GFrame\Tests;

use GFrame\Auth\{AuthModel, RoleModel, UserModel, UserAdministrationService};
use GFrame\Config\ConfigRepository;
use GFrame\Mail\AccessMailService;
use GFrame\Mail\Contracts\MailSender;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class UserInvitationTest extends TestCase
{
    private \PDO $pdo;
    private InvitationMailSender $mail;
    private UserAdministrationService $service;

    protected function setUp(): void
    {
        define('DB_DEFAULT_CONNECTION', 'invitation');
        define('DB_CONNECTIONS', ['invitation' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        \ORM::disconnect();
        $this->pdo = \DatabaseManager::connection('invitation');
        $this->pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/database/schema/sqlite/auth.sql'));
        $this->pdo->exec("INSERT INTO roles(role_id,name,slug,permissions_json) VALUES
            (3,'Creador','creator','{\"users.create\":true,\"users.view\":true}'),
            (4,'Administrador','administrator','{\"admin.access\":true}'),
            (5,'Gestor','manager','{\"users.manage\":true}')");
        $this->pdo->exec("INSERT INTO users(user_id,email,password,role_id,status) VALUES
            (1,'root@example.test','hash',1,'verify'),(2,'creator@example.test','hash',3,'verify'),
            (3,'manager@example.test','hash',5,'verify')");
        ConfigRepository::replace(['app' => ['url' => 'https://example.test/subdir/']]);
        $this->mail = new InvitationMailSender();
        $this->service = new UserAdministrationService(new UserModel(), new RoleModel(), accessMail: new AccessMailService($this->mail));
    }

    protected function tearDown(): void
    {
        \ORM::disconnect();
        ConfigRepository::replace([]);
    }

    public function testInvitationDefinesPasswordVerifiesAndCannotBeReused(): void
    {
        $result = $this->service->create(1, ' Invited@Example.test ', 2);
        self::assertSame('user_invited', $result['code']);
        self::assertArrayNotHasKey('token', $result['data']);
        $auth = new AuthModel();
        $user = $auth->findByEmail('invited@example.test');
        self::assertSame('unverify', $user['status']);
        self::assertCount(1, $this->mail->messages);
        self::assertSame('mailTemplate', $this->mail->messages[0]['template']);
        self::assertStringStartsWith('https://example.test/subdir/login/resetpassword?rp=invite_', $this->mail->messages[0]['variables']['aHref']);
        $token = $user['token'];
        self::assertSame('invalid_token', $auth->validateAcount($token)['code']);
        self::assertSame('invalid_token', $auth->validateAcount(strtoupper($token))['code']);
        self::assertSame('invalid_password', $auth->resetPassword($token, 'short')['code']);
        $password = '  Exact-password-123  ';
        self::assertSame('password_reset', $auth->resetPassword($token, $password)['code']);
        self::assertSame('verify', $auth->findByEmail('invited@example.test')['status']);
        self::assertSame('success', $auth->login('invited@example.test', $password)['status']);
        self::assertSame('invalid_token', $auth->resetPassword($token, 'Other-password-123')['code']);
        self::assertSame(0, (int)$this->pdo->query('SELECT count(*) FROM tenant_memberships')->fetchColumn());
    }

    public function testPermissionHierarchyDuplicatesAndValidationPreventWrites(): void
    {
        self::assertSame('forbidden', $this->service->create(3, 'new@example.test', 2)['code']);
        self::assertSame('forbidden', $this->service->create(0, 'new@example.test', 2)['code']);
        self::assertSame('invalid_role_assignment', $this->service->create(1, 'new@example.test', 1)['code']);
        self::assertSame('invalid_role_assignment', $this->service->create(2, 'new@example.test', 4)['code']);
        self::assertSame('invalid_role_assignment', $this->service->create(1, 'new@example.test', 99)['code']);
        self::assertSame('invalid_email', $this->service->create(1, 'broken', 2)['code']);
        self::assertSame('user_exists', $this->service->create(1, 'ROOT@example.test', 2)['code']);
        self::assertSame(3, (int)$this->pdo->query('SELECT count(*) FROM users')->fetchColumn());
        self::assertSame([], $this->mail->messages);
        $roleIDs = array_map('intval', array_column($this->service->creationForm(2)['data']['roles'], 'role_id'));
        sort($roleIDs);
        self::assertSame([2, 3, 5], $roleIDs);
        self::assertSame('user_invited', $this->service->create(1, 'admin@example.test', 4)['code']);
    }

    public function testExpiryBlockedAccountsAndReplacementTokens(): void
    {
        $this->service->create(2, 'invited@example.test', 2);
        $auth = new AuthModel();
        $user = $auth->findByEmail('invited@example.test');
        $oldToken = $user['token'];
        $this->pdo->exec("UPDATE users SET token_updated_at='2000-01-01 00:00:00' WHERE user_id=" . (int)$user['user_id']);
        self::assertSame('invalid_token', $auth->resetPassword($oldToken, 'Password-123')['code']);
        $recovery = $auth->recoveryAcount('invited@example.test');
        $token = $recovery['data']['token'];
        self::assertStringStartsWith('invite_', $token);
        self::assertSame('invalid_token', $auth->resetPassword($oldToken, 'Password-123')['code']);
        $resend = $auth->verifyAcount('invited@example.test');
        self::assertStringStartsWith('invite_', $resend['data']['token']);
        self::assertSame('invalid_token', $auth->resetPassword($token, 'Password-123')['code']);
        $this->pdo->exec("UPDATE users SET status='suspended' WHERE user_id=" . (int)$user['user_id']);
        self::assertSame('suspended_account', $auth->resetPassword($resend['data']['token'], 'Password-123')['code']);
    }

    public function testMailFailureDoesNotLoseTheCreatedAccountOrExposeToken(): void
    {
        $this->mail->fail = true;
        $result = $this->service->create(1, 'pending@example.test', 2);
        self::assertSame('success', $result['status']);
        self::assertSame('user_created_mail_failed', $result['code']);
        self::assertSame(['user_id'], array_keys($result['data']));
        self::assertSame('unverify', (new AuthModel())->findByEmail('pending@example.test')['status']);
        self::assertSame('user_exists', $this->service->create(1, 'pending@example.test', 2)['code']);
    }

    public function testMissingCanonicalUrlDoesNotCreateAnAccount(): void
    {
        ConfigRepository::replace(['app' => ['url' => '/relative']]);
        self::assertSame('invitation_url_missing', $this->service->create(1, 'pending@example.test', 2)['code']);
        self::assertNull((new AuthModel())->findByEmail('pending@example.test'));
        self::assertSame([], $this->mail->messages);
    }

    public function testAdministrativeVerificationDoesNotStrandTheInvitation(): void
    {
        $created = $this->service->create(1, 'verified@example.test', 2);
        $auth = new AuthModel();
        $token = $auth->findByEmail('verified@example.test')['token'];
        self::assertSame('user_verified', $this->service->moderate(1, $created['data']['user_id'], 'verify')['code']);
        self::assertSame('password_reset', $auth->resetPassword($token, 'Password-123')['code']);
        self::assertSame('success', $auth->login('verified@example.test', 'Password-123')['status']);
    }

    public function testInvitationCanBeQueuedAndSupersededMailIsRejected(): void
    {
        $this->pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/notifications/database/sqlite.sql'));
        $queue = new \GFrame\Notifications\NotificationQueueModel();
        $service = new UserAdministrationService(new UserModel(), new RoleModel(), accessMail: new AccessMailService($this->mail, $queue));
        self::assertSame('user_invited', $service->create(1, 'queued@example.test', 2)['code']);
        self::assertSame([], $this->mail->messages);
        $record = $this->pdo->query('SELECT * FROM notification_queue')->fetch(\PDO::FETCH_ASSOC);
        $record['payload'] = json_decode($record['payload_json'], true);
        $transport = new \GFrame\Notifications\Email\EmailNotificationTransport($this->mail);
        $transport->send($record);
        self::assertCount(1, $this->mail->messages);
        (new AuthModel())->recoveryAcount('queued@example.test');
        $this->expectException(\GFrame\Mail\ExpiredAccessMail::class);
        $transport->send($record);
    }

    public function testControllerUsesSessionActorAndKeepsTokensPrivate(): void
    {
        require_once dirname(__DIR__) . '/resources/modules/user-admin/application/app/controllers/user-admin/UserAdminController.php';
        $controller = new \GFrame\Modules\UserAdmin\Controllers\UserAdminController($this->service, new RoleModel());
        $_SESSION['auth']['id'] = 3;
        $_POST = ['email' => 'boundary@example.test', 'role_id' => '2', 'actor_id' => '1'];
        self::assertSame('forbidden', $controller->store()['code']);
        $_SESSION['auth']['id'] = 1;
        $result = $controller->store();
        self::assertSame('user_invited', $result['code']);
        self::assertNotEmpty($result['message']);
        self::assertSame(['user_id'], array_keys($result['data']));
        $_SESSION = [];
        $_POST = [];
    }
}

final class InvitationMailSender implements MailSender
{
    public array $messages = [];
    public bool $fail = false;
    public function sendTemplate(string $recipient, string $subject, string $template, array $variables = [], array $options = []): array
    {
        $this->messages[] = compact('recipient', 'subject', 'template', 'variables', 'options');
        return ['status' => $this->fail ? 'error' : 'success'];
    }
    public function sendHtml(string $recipient, string $subject, string $html, array $options = []): array
    {
        throw new \LogicException('Las invitaciones deben usar plantillas.');
    }
}
