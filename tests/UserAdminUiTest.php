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
        self::assertNotContains('gf-select', $manifest['dependencies']);
        self::assertNotContains('self-account', $manifest['dependencies']);
        self::assertContains('admin-panel', $manifest['dependencies']);
        $menu = (string)file_get_contents($this->modulePath . '/application/admin/users-menu.php');
        self::assertStringContainsString('<span>Gestión de usuarios</span>', $menu);
        self::assertStringContainsString('<li class="nav-heading">Usuarios</li>', $menu);
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
        $view = (string)file_get_contents($this->modulePath . '/application/app/views/user-admin/user-adminIndex.php');
        $list = (string)file_get_contents($this->modulePath . '/application/app/views/user-admin/_userList.php');

        self::assertStringContainsString('name="role"', $view);
        self::assertStringContainsString('name="status"', $view);
        self::assertStringContainsString('No se encontraron usuarios.', $list);
        self::assertStringContainsString('id="userFilters"', $view);
        self::assertStringContainsString('id="clearUserFilters"', $view);
        self::assertStringContainsString('id="userListMount"', $view);
        self::assertStringContainsString('class="pagetitle"', $view);
        self::assertStringNotContainsString('shadow-sm', $view . $list);
        self::assertStringNotContainsString('py-lg-5', $view);
        self::assertStringContainsString("\$_SESSION['csrfToken']", $view);
        self::assertStringContainsString("\$_SESSION['csrfTimestamp']", $view);
        self::assertStringContainsString('$canManage', $view);
    }

    public function testJavascriptRestoresRolesAndHandlesTransportFailures(): void
    {
        $javascript = (string)file_get_contents($this->modulePath . '/javascript/user-admin.js');

        self::assertStringContainsString("$('#userRoleForm').on('submit'", $javascript);
        self::assertSame(1, substr_count($javascript, '.fail(function ()'));
        self::assertStringContainsString('result.isConfirmed', $javascript);
        self::assertStringContainsString("confirmed: '1'", $javascript);
        self::assertStringContainsString("$('#clearUserFilters').on('click'", $javascript);
        self::assertStringContainsString("$('#userSearch').on('input'", $javascript);
        self::assertStringContainsString('}, 300)', $javascript);
        self::assertStringContainsString("form.on('change', 'select'", $javascript);
        self::assertStringContainsString(".prop('hidden'", $javascript);
        self::assertStringContainsString('pending.abort()', $javascript);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testListEscapesDataAndProtectsSelfAndSuperadministrator(): void
    {
        \GFrame\Modules\ModuleRuntime::initialize(\GFrame\Modules\ModuleCatalog::frameworkDefault(), ['user-admin'], dirname(__DIR__));
        $previousSession = $_SESSION ?? [];
        $_SESSION['auth']['id'] = 2;
        $data = ['data' => [
            'can_manage' => true,
            'roles' => [['role_id' => 2, 'slug' => 'user', 'name' => 'Usuario']],
            'users' => ['data' => [
                ['user_id' => 1, 'email' => 'root@example.test', 'role' => 'superadministrator', 'status' => 'verify'],
                ['user_id' => 2, 'email' => 'self@example.test', 'role' => 'user', 'role_id' => 2, 'status' => 'verify'],
                ['user_id' => 3, 'email' => '<other>@example.test', 'role' => 'user', 'role_id' => 2, 'status' => 'unverify'],
            ], 'meta' => ['total_pages' => 1]],
        ]];
        ob_start();
        try {
            include $this->modulePath . '/application/app/views/user-admin/user-adminIndex.php';
            $html = (string)ob_get_contents();
        } finally {
            ob_end_clean();
            $_SESSION = $previousSession;
        }
        self::assertStringContainsString('Superadministrador', $html);
        self::assertStringContainsString('Tu cuenta', $html);
        self::assertStringContainsString('&lt;other&gt;@example.test', $html);
        self::assertStringNotContainsString('<other>', $html);
        self::assertSame(1, substr_count($html, 'class="btn btn-sm btn-outline-secondary btn-list-actions js-user-actions"'));
        self::assertStringNotContainsString('>Desactivar</button>', $html);
        self::assertStringContainsString('id="userModal"', $html);
        self::assertStringContainsString('id="managedUserStatus"', $html);
        self::assertStringContainsString('class="input-group mb-4"', $html);
        self::assertStringContainsString('class="btn btn-warning js-user-operation"', $html);
        self::assertStringNotContainsString('btn-outline-success js-user-operation', $html);
        self::assertStringContainsString('border-top pt-3', $html);
        self::assertStringContainsString('Sin verificar', $html);
        self::assertStringNotContainsString('id="all_items_pagination"', $html);
        self::assertStringNotContainsString('card-body p-0', $html);
        self::assertStringNotContainsString('>Filtrar</button>', $html);
        self::assertStringContainsString('gicon-close', $html);
    }

    public function testControllerUsesCanonicalDataContract(): void
    {
        $controller = (string)file_get_contents($this->modulePath . '/application/app/controllers/user-admin/UserAdminController.php');

        self::assertStringContainsString("'status' => 'success'", $controller);
        self::assertStringContainsString("'code' => 'users_loaded'", $controller);
        self::assertStringContainsString("'data' => [", $controller);
        self::assertStringNotContainsString('Throwable', $controller);
    }
}
