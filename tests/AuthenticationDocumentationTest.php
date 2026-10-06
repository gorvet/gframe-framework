<?php

namespace GFrame\Tests;

use GFrame\Auth\AuthModel;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AuthenticationDocumentationTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testExamplesMatchTheAuthenticationContracts(): void
    {
        define('DB_DEFAULT_CONNECTION', 'auth_docs');
        define('DB_CONNECTIONS', ['auth_docs' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        \ORM::disconnect();
        $pdo = \DatabaseManager::connection('auth_docs');
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/resources/database/schema/sqlite/auth.sql'));
        $auth = new AuthModel();
        $email = 'doc@example.test';
        $password = 'Password-123';
        $registered = $auth->registerAcount($email, $password);
        self::assertSame('account_registered', $registered['code']);
        self::assertSame('account_verified', $auth->validateAcount($registered['data']['token'])['code']);
        $source = file_get_contents(dirname(__DIR__) . '/docs/autenticacion.md');
        preg_match_all('/```php\s*\n(.*?)\n```/s', $source, $blocks);
        self::assertCount(5, $blocks[1]);
        try {
            foreach ($blocks[1] as $code) self::assertNotEmpty(token_get_all('<?php ' . $code, TOKEN_PARSE));
            eval($blocks[1][0]);
            self::assertSame('authenticated', $result['code']);
            self::assertSame($email, $user['email']);
            self::assertFalse($mustChangePassword);
            self::assertArrayNotHasKey('password', $user);
            self::assertArrayNotHasKey('token', $user);
            $_SESSION['auth'] = ['id' => (int)$user['user_id'], 'email' => $email];
            eval($blocks[1][1]);
            self::assertSame((int)$user['user_id'], $userID);
            eval($blocks[1][2]);
            self::assertSame(['auth'], \RouteBuilder::all()['GET']['area']['middleware']);
            eval($blocks[1][3]);
            self::assertInstanceOf(AuthModel::class, $model);
            $settings = eval($blocks[1][4]);
            self::assertSame('account', $settings['auth']['password_change_redirect']);
        } finally {
            \ORM::disconnect();
            unset($_SESSION['auth']);
        }
    }
}
