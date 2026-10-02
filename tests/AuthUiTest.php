<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;
use GFrame\Modules\AuthUi\Controllers\AuthController;

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
        self::assertSame('application/app', $manifest['runtime']['root']);
        self::assertSame('GFrame\\Modules\\AuthUi', $manifest['runtime']['namespace']);
        self::assertContains('config/routes/routes_auth.php', $targets);
        self::assertContains('config/routes/routes_ajax_auth.php', $targets);
        self::assertFileExists($this->modulePath . '/application/app/views/auth-ui/authLogin.php');
        self::assertFileExists($this->modulePath . '/application/app/views/auth-ui/authRegister.php');
        self::assertFileExists($this->modulePath . '/application/app/views/auth-ui/authRecovery.php');
        self::assertFileExists($this->modulePath . '/application/app/views/auth-ui/authReset.php');
        self::assertFileExists($this->modulePath . '/application/app/views/auth-ui/authVerify.php');
    }

    public function testRoutesProtectGuestAndAuthenticatedOperations(): void
    {
        $web = (string)file_get_contents($this->modulePath . '/application/routes/routes_auth.php');
        $ajax = (string)file_get_contents($this->modulePath . '/application/routes/routes_ajax_auth.php');

        self::assertSame(5, substr_count($web, "->middleware(['guest'])"));
        self::assertStringContainsString("Route::get('login/lostpassword'", $web);
        self::assertStringContainsString("Route::get('login/resetpassword'", $web);
        self::assertStringContainsString("Route::post('ajax/logout'", $ajax);
        self::assertStringContainsString("->middleware(['auth'])", $ajax);
        self::assertSame(9, substr_count($ajax, "->middleware(['honeypot', 'guest'])"));
        self::assertSame(9, substr_count($ajax, "->excludeMiddleware(['CSRF'])"));
        foreach (['ajax/verifyacount', 'ajax/validateacount', 'ajax/lostpassword', 'ajax/resetpassword'] as $route) {
            self::assertStringContainsString("Route::post('" . $route . "'", $ajax);
        }
        self::assertStringContainsString("'auth-ui/AuthController@resendVerification'", $ajax);
    }

    public function testAuthLayerUsesExceptionContractsInsteadOfThrowable(): void
    {
        $files = glob($this->frameworkPath . '/src/GFrame/Auth/*.php') ?: [];
        $files[] = $this->modulePath . '/application/app/controllers/auth-ui/AuthController.php';

        foreach ($files as $file) {
            self::assertStringNotContainsString('Throwable', (string)file_get_contents($file), $file);
        }
    }

    public function testForcedPasswordChangeHasAnExplicitRedirect(): void
    {
        $controller = (string)file_get_contents($this->modulePath . '/application/app/controllers/auth-ui/AuthController.php');
        $defaults = require $this->frameworkPath . '/config/defaults.php';

        self::assertSame('account', $defaults['auth']['password_change_redirect']);
        self::assertStringContainsString("'auth.password_change_redirect'", $controller);
        self::assertStringContainsString("\$path = 'admin'", $controller);
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
        require_once $this->modulePath . '/application/app/controllers/auth-ui/AuthController.php';
        $method = new \ReflectionMethod(AuthController::class, 'returnUrl');
        $method->setAccessible(true);
        self::assertSame('admin/items?page=2', $method->invoke(null, 'admin/items?page=2'));
        foreach (['https://evil.test', '//evil.test', '../admin', '%2e%2e/admin', '%252e%252e/admin', 'admin\\x', "admin\n", '/admin'] as $path) {
            self::assertNull($method->invoke(null, $path), $path);
        }
    }

    public function testMetaConnectsTheOriginalPasswordAndValidationHelpers(): void
    {
        $meta = (string)file_get_contents($this->modulePath . '/application/app/views/auth-ui/auth-ui.group.meta.php');
        foreach (['public/css/variables.css', 'passwordUtils/passwordUtils.css', 'passwordUtils/passwordUtils.js', 'utils/forms.js', 'utils/errors.js'] as $asset) self::assertStringContainsString($asset, $meta);
        foreach (['AuthLogin.js', 'AuthRegister.js', 'AuthLostpassword.js', 'AuthResetpassword.js'] as $script) {
            self::assertStringContainsString($script, $meta);
            self::assertFileExists($this->modulePath . '/javascript/' . $script);
        }
        self::assertStringContainsString('site_url + response.redirect', (string)file_get_contents($this->modulePath . '/javascript/AuthLogin.js'));
        $admin = (string)file_get_contents($this->frameworkPath . '/resources/modules/admin-panel/javascript/admin.js');
        self::assertStringNotContainsString("'ajax/logout'", $admin);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testOriginalPostedTokenFieldsVerifyAndResetTheAccount(): void
    {
        require_once __DIR__ . '/AuthModelTest.php';
        require_once $this->modulePath . '/application/app/controllers/auth-ui/AuthController.php';
        $users = new InMemoryAuthModel();
        $auth = $users;
        $registered = $auth->registerAcount('user@example.test', 'Password-123');
        $controller = new AuthController($auth);
        $_POST = ['vtoken' => $registered['data']['token']];
        self::assertSame('account_verified', $controller->validateAcount()['code']);
        $recovery = $auth->recoveryAcount('user@example.test');
        $_POST = ['rpuser_token' => $recovery['data']['token'], 'reset_password' => 'New-password-456'];
        self::assertSame('password_reset', $controller->resetPassword()['code']);
        self::assertSame('success', $auth->login('user@example.test', 'New-password-456')['status']);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testResendVerificationQueuesTheSharedTemplateWithoutExposingToken(): void
    {
        require_once __DIR__ . '/AuthModelTest.php';
        require_once $this->modulePath . '/application/app/controllers/auth-ui/AuthController.php';
        define('site_url', 'https://example.test/app/');
        $users = new InMemoryAuthModel();
        $auth = $users;
        $async = new class extends \Async {
            public ?\Closure $job = null;
            public function create(\Closure $closure): void { $this->job = $closure; }
        };
        $controller = new AuthController($auth, null, new \GFrame\Mail\MailService(null, null, $async));
        $_POST = ['register_email' => 'user@example.test', 'register_password' => 'Password-123'];
        $registration = $controller->register();
        self::assertSame('account_registered', $registration['code']);
        self::assertArrayNotHasKey('token', (array)($registration['data'] ?? []));
        self::assertInstanceOf(\Closure::class, $async->job);
        $initialMail = (new \ReflectionFunction($async->job))->getStaticVariables();
        self::assertStringStartsWith('https://example.test/app/login/verify?v=', $initialMail['variables']['aHref']);
        self::assertStringContainsString('Gracias por registrarte.', $initialMail['variables']['p1']);
        $_POST = ['login_email' => 'user@example.test'];
        $response = $controller->resendVerification();
        self::assertSame('verification_requested', $response['code']);
        self::assertArrayNotHasKey('token', $response);
        self::assertArrayNotHasKey('token', (array)($response['data'] ?? []));
        self::assertInstanceOf(\Closure::class, $async->job);
        $captured = (new \ReflectionFunction($async->job))->getStaticVariables();
        self::assertSame('mailTemplate', $captured['template']);
        self::assertSame('Hola, user.', $captured['variables']['greeting']);
        self::assertSame('Verifica tu cuenta · GFrame', $captured['subject']);
        self::assertSame('user', $captured['options']['recipient_name']);
        self::assertStringStartsWith('https://example.test/app/login/verify?v=', $captured['variables']['aHref']);
        $_POST = ['recovery_email' => 'user@example.test'];
        $response = $controller->recovery();
        self::assertSame('recovery_requested', $response['code']);
        self::assertArrayNotHasKey('token', (array)($response['data'] ?? []));
        $recoveryMail = (new \ReflectionFunction($async->job))->getStaticVariables();
        self::assertSame('Recupera tu cuenta · GFrame', $recoveryMail['subject']);
        self::assertSame('Hola, user.', $recoveryMail['variables']['greeting']);
        self::assertSame('Establecer una contraseña', $recoveryMail['variables']['aText']);
        self::assertStringStartsWith('https://example.test/app/login/resetpassword?rp=', $recoveryMail['variables']['aHref']);
        $registry = new \GFrame\Mail\MailTemplateRegistry($this->frameworkPath . '/resources/skeleton/app/views/templates/mail');
        foreach ([$initialMail, $captured, $recoveryMail] as $mail) {
            $html = $registry->render($mail['template'], $mail['variables']);
            self::assertStringContainsString('Hola, user.', $html);
            self::assertStringContainsString('Todos los derechos reservados.', $html);
            self::assertStringContainsString('Construido con GFrame.', $html);
            self::assertStringNotContainsString('{{', $html);
            self::assertStringContainsString($mail['variables']['h1'], $html);
        }
    }
}
