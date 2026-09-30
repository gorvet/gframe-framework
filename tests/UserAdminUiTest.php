<?php

namespace GFrame\Tests;

use GFrame\Auth\Contracts\RoleAdministrationRepository;
use GFrame\Auth\Contracts\UserAdministrationRepository;
use GFrame\Auth\RoleModel;
use GFrame\Auth\UserModel;
use PHPUnit\Framework\TestCase;

final class UserAdminUiTest extends TestCase
{
    private string $modulePath;

    protected function setUp(): void
    {
        $this->modulePath = dirname(__DIR__) . '/resources/modules/user-admin';
    }

    public function testDefaultModelsProvideAdministrationContracts(): void
    {
        self::assertContains(UserAdministrationRepository::class, class_implements(UserModel::class));
        self::assertContains(RoleAdministrationRepository::class, class_implements(RoleModel::class));
    }

    public function testManifestDoesNotIncludeUnusedSelectorDependency(): void
    {
        $manifest = require $this->modulePath . '/module.php';
        self::assertSame('user-admin', $manifest['name']);
        self::assertNotContains('gfselect', $manifest['dependencies']);
        self::assertContains('self-account', $manifest['dependencies']);
    }

    public function testRoutesSeparateViewAndManagementPermissions(): void
    {
        $web = (string)file_get_contents($this->modulePath . '/application/routes/routes_admin_users.php');
        $ajax = (string)file_get_contents($this->modulePath . '/application/routes/routes_ajax_admin_users.php');

        self::assertStringContainsString("can:users.view", $web);
        self::assertStringContainsString("can:users.view", $ajax);
        self::assertStringContainsString("can:users.manage", $ajax);
        self::assertStringNotContainsString('role:superadministrator', $web . $ajax);
    }

    public function testViewHasFiltersEmptyStateAndCsrfTokens(): void
    {
        $view = (string)file_get_contents($this->modulePath . '/application/views/usersIndex.php');

        self::assertStringContainsString('name="role"', $view);
        self::assertStringContainsString('name="status"', $view);
        self::assertStringContainsString('No se encontraron usuarios.', $view);
        self::assertStringContainsString("\$_SESSION['csrfToken']", $view);
        self::assertStringContainsString("\$_SESSION['csrfTimestamp']", $view);
        self::assertStringContainsString('$canManage', $view);
    }

    public function testJavascriptRestoresRolesAndHandlesTransportFailures(): void
    {
        $javascript = (string)file_get_contents($this->modulePath . '/javascript/user-admin.js');

        self::assertStringContainsString('previous-value', $javascript);
        self::assertSame(2, substr_count($javascript, '.fail(function ()'));
        self::assertStringContainsString("select.val(select.data('previous-value'))", $javascript);
    }

    public function testControllerUsesCanonicalDataContract(): void
    {
        $controller = (string)file_get_contents($this->modulePath . '/application/controllers/UserAdminController.php');

        self::assertStringContainsString("'status' => 'success'", $controller);
        self::assertStringContainsString("'code' => 'users_loaded'", $controller);
        self::assertStringContainsString("'data' => [", $controller);
        self::assertStringNotContainsString('Throwable', $controller);
    }
}
