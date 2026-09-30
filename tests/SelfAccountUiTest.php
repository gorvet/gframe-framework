<?php

namespace GFrame\Tests;

use GFrame\Auth\Contracts\AccountDeactivationPolicy;
use GFrame\Auth\Contracts\SelfAccountRepository;
use GFrame\Auth\UserModel;
use PHPUnit\Framework\TestCase;

final class SelfAccountUiTest extends TestCase
{
    private string $modulePath;

    protected function setUp(): void
    {
        $this->modulePath = dirname(__DIR__) . '/resources/modules/self-account';
    }

    public function testDefaultModelProvidesBothExtensionContracts(): void
    {
        self::assertContains(SelfAccountRepository::class, class_implements(UserModel::class));
        self::assertContains(AccountDeactivationPolicy::class, class_implements(UserModel::class));
    }

    public function testManifestPublishesTheCompleteModule(): void
    {
        $manifest = require $this->modulePath . '/module.php';
        $targets = array_column($manifest['application'], 'target');

        self::assertSame('self-account', $manifest['name']);
        self::assertContains('auth-ui', $manifest['dependencies']);
        self::assertContains('auth', $manifest['requires_schema']);
        self::assertContains('app/controllers/account/SelfAccountController.php', $targets);
        self::assertContains('config/routes/routes_account.php', $targets);
        self::assertContains('config/routes/routes_ajax_account.php', $targets);
        self::assertContains('app/views/account', $targets);
        self::assertContains('app/views/templates/accountTemplate.php', $targets);
    }

    public function testAllRoutesRequireAuthentication(): void
    {
        $web = (string)file_get_contents($this->modulePath . '/application/routes/routes_account.php');
        $ajax = (string)file_get_contents($this->modulePath . '/application/routes/routes_ajax_account.php');

        self::assertSame(1, substr_count($web, "->middleware(['auth'])"));
        self::assertSame(2, substr_count($ajax, "->middleware(['auth'])"));
    }

    public function testViewEscapesAccountDataAndPublishesCsrfTokens(): void
    {
        $view = (string)file_get_contents($this->modulePath . '/application/views/accountIndex.php');

        self::assertStringContainsString("\$data['data']['account']", $view);
        self::assertGreaterThanOrEqual(4, substr_count($view, 'htmlspecialchars('));
        self::assertStringContainsString("\$_SESSION['csrfToken']", $view);
        self::assertStringContainsString("\$_SESSION['csrfTimestamp']", $view);
        self::assertStringContainsString("\$_SESSION['must_change_password']", $view);
    }

    public function testControllerReturnsCanonicalDataAndClearsPasswordRequirement(): void
    {
        $controller = (string)file_get_contents($this->modulePath . '/application/controllers/SelfAccountController.php');

        self::assertStringContainsString("'data' => ['account' => \$response['data']]", $controller);
        self::assertStringContainsString("unset(\$_SESSION['must_change_password'])", $controller);
        self::assertStringNotContainsString('Throwable', $controller);
    }

    public function testJavascriptHandlesTransportFailuresForBothMutations(): void
    {
        $javascript = (string)file_get_contents($this->modulePath . '/javascript/self-account.js');

        self::assertSame(2, substr_count($javascript, '.fail(function ()'));
        self::assertStringContainsString('alertToast(', $javascript);
        self::assertStringContainsString('swalAlert(', $javascript);
    }
}
