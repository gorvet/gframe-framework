<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class SessionDocumentationTest extends TestCase
{
    public function testExamplesUseExistingSessionContracts(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/docs/sesiones.md');
        preg_match_all('/```php\R(.*?)\R```/s', $source, $matches);
        self::assertCount(7, $matches[1]);
        foreach ($matches[1] as $code) {
            token_get_all(str_starts_with($code, '<?php') ? $code : '<?php return [' . $code . '];', TOKEN_PARSE);
        }
        foreach (['login', 'logout', 'updateIdentity'] as $method) {
            self::assertTrue(method_exists(\GFrame\Auth\SessionManager::class, $method));
        }
        foreach (['revokeUser', 'allowUser'] as $method) {
            self::assertTrue(method_exists(\GFrame\Session\ActiveSessionRegistry::class, $method));
        }
        self::assertTrue(method_exists(\GFrame\Session\SessionRuntime::class, 'registry'));
    }
}
