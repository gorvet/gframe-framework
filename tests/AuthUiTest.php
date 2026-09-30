<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class AuthUiTest extends TestCase
{
    private string $modulePath;
    private string $frameworkPath;

    protected function setUp(): void
    {
        $this->frameworkPath = dirname(__DIR__);
        $this->modulePath = $this->frameworkPath . '/resources/modules/auth-ui';
    }

    public function testManifestDeclaresTheCompleteAuthenticationInterface(): void
    {
        $manifest = require $this->modulePath . '/module.php';
        $targets = array_column($manifest['application'], 'target');

        self::assertSame('auth-ui', $manifest['name']);
        self::assertContains('auth', $manifest['requires_schema']);
        self::assertContains('app/controllers/auth/AuthController.php', $targets);
        self::assertContains('config/routes/routes_auth.php', $targets);
        self::assertContains('config/routes/routes_ajax_auth.php', $targets);
        self::assertContains('app/views/auth/authLogin.php', $targets);
        self::assertContains('app/views/auth/authRegister.php', $targets);
        self::assertContains('app/views/auth/authRecovery.php', $targets);
        self::assertContains('app/views/auth/authReset.php', $targets);
        self::assertContains('app/views/auth/authVerify.php', $targets);
    }

    public function testRoutesProtectGuestAndAuthenticatedOperations(): void
    {
        $web = (string)file_get_contents($this->modulePath . '/application/routes/routes_auth.php');
        $ajax = (string)file_get_contents($this->modulePath . '/application/routes/routes_ajax_auth.php');

        self::assertSame(5, substr_count($web, "->middleware(['guest'])"));
        self::assertStringContainsString("Route::post('ajax/logout'", $ajax);
        self::assertStringContainsString("->middleware(['auth'])", $ajax);
        self::assertSame(5, substr_count($ajax, "->middleware(['honeypot', 'guest'])"));
        self::assertSame(5, substr_count($ajax, "->excludeMiddleware(['CSRF'])"));
        self::assertStringContainsString("'auth/AuthController@resendVerification'", $ajax);
    }

    public function testAuthLayerUsesExceptionContractsInsteadOfThrowable(): void
    {
        $files = glob($this->frameworkPath . '/src/GFrame/Auth/*.php') ?: [];
        $files[] = $this->modulePath . '/application/controllers/AuthController.php';

        foreach ($files as $file) {
            self::assertStringNotContainsString('Throwable', (string)file_get_contents($file), $file);
        }
    }

    public function testForcedPasswordChangeHasAnExplicitRedirect(): void
    {
        $controller = (string)file_get_contents($this->modulePath . '/application/controllers/AuthController.php');
        $defaults = require $this->frameworkPath . '/config/defaults.php';

        self::assertSame('account', $defaults['auth']['password_change_redirect']);
        self::assertStringContainsString("'auth.password_change_redirect'", $controller);
        self::assertStringContainsString("'must_change_password' => \$mustChangePassword", $controller);
    }

    public function testClientDoesNotExposeCaughtTechnicalErrors(): void
    {
        $javascript = (string)file_get_contents($this->modulePath . '/javascript/auth.js');

        self::assertStringNotContainsString('error.message', $javascript);
        self::assertStringContainsString("ajaxError(status, '')", $javascript);
        self::assertStringContainsString('validationFeedback($(form), messages)', $javascript);
        self::assertStringContainsString('passwordValidate(', $javascript);
    }

    public function testReturnDestinationCannotLeaveTheApplication(): void
    {
        require_once $this->modulePath . '/application/controllers/AuthController.php';
        $method = new \ReflectionMethod(\AuthController::class, 'returnUrl');
        $method->setAccessible(true);
        $base = 'https://example.test/app/';
        self::assertSame($base . 'admin/items?page=2', $method->invoke(null, 'admin/items?page=2', $base));
        foreach (['https://evil.test', '//evil.test', '../admin', '%2e%2e/admin', '%252e%252e/admin', 'admin\\x', "admin\n", '/admin'] as $path) {
            self::assertNull($method->invoke(null, $path, $base), $path);
        }
    }

    public function testMetaConnectsTheOriginalPasswordAndValidationHelpers(): void
    {
        $meta = (string)file_get_contents($this->modulePath . '/application/views/auth.group.meta.php');
        foreach (['public/css/variables.css', 'passwordUtils/passwordUtils.css', 'passwordUtils/passwordUtils.js', 'utils/forms.js', 'utils/errors.js'] as $asset) self::assertStringContainsString($asset, $meta);
        $admin = (string)file_get_contents($this->frameworkPath . '/resources/modules/admin-panel/javascript/admin.js');
        self::assertStringNotContainsString("'ajax/logout'", $admin);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testResendVerificationQueuesTheSharedTemplateWithoutExposingToken(): void
    {
        require_once __DIR__ . '/AuthServiceTest.php';
        require_once $this->modulePath . '/application/controllers/AuthController.php';
        define('site_url', 'https://example.test/app/');
        $users = new InMemoryUserModel();
        $auth = new \GFrame\Auth\AuthService($users);
        $auth->register('user@example.test', 'Password-123');
        $async = new class extends \Async {
            public ?\Closure $job = null;
            public function create(\Closure $closure): void { $this->job = $closure; }
        };
        $controller = new \AuthController($auth, null, new \GFrame\Mail\MailService(null, null, $async));
        $_POST = ['login_email' => 'user@example.test'];
        $response = $controller->resendVerification();
        self::assertSame('verification_requested', $response['code']);
        self::assertArrayNotHasKey('token', $response);
        self::assertInstanceOf(\Closure::class, $async->job);
        $captured = (new \ReflectionFunction($async->job))->getStaticVariables();
        self::assertSame('mailTemplate', $captured['template']);
        self::assertStringStartsWith('https://example.test/app/login/verify?v=', $captured['variables']['aHref']);
    }
}
