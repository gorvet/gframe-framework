<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class PermissionsDocumentationTest extends TestCase
{
    public function testExamplesAreValidPhpAndServicesExist(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/docs/permisos.md');
        preg_match_all('/```php\R(.*?)\R```/s', $source, $matches);
        self::assertCount(6, $matches[1]);
        foreach ($matches[1] as $code) {
            token_get_all(str_starts_with($code, '<?php') ? $code : '<?php ' . $code, TOKEN_PARSE);
        }
        foreach (['authorize', 'createRole', 'grantPermission', 'revokePermission', 'assignRole', 'deleteRole'] as $method) {
            self::assertTrue(method_exists(\GFrame\Auth\RolePermissionService::class, $method));
        }
        foreach (['setOverride', 'removeOverride', 'assignTenantRole', 'deactivateTenantMembership'] as $method) {
            self::assertTrue(method_exists(\GFrame\Auth\UserPermissionService::class, $method));
        }
        $code = preg_replace('/^<\?php\s*/', '', $matches[1][1]);
        $route = eval(str_replace(['Route::get(', '->registerFinal();'], ['return Route::get(', ';'], $code));
        self::assertInstanceOf(\RouteBuilder::class, $route);
    }
}
