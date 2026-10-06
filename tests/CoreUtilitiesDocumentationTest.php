<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class CoreUtilitiesDocumentationTest extends TestCase
{
    public function testSanitizerExampleAndDocumentedMaintenanceApis(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/docs/html-sanitizer.md');
        preg_match_all('/```php\R(.*?)\R```/s', $source, $matches);
        foreach ($matches[1] as $code) token_get_all(str_starts_with($code, '<?php') ? $code : '<?php ' . $code, TOKEN_PARSE);
        $previous = $_POST;
        try {
            $_POST['content'] = '<p>Hola<script>alert(1)</script><strong> mundo</strong></p>';
            $result = eval(preg_replace('/^<\?php\s*/', '', $matches[1][1]));
            self::assertSame('success', $result['status']);
            self::assertStringNotContainsString('script', $result['data']['content']);
            self::assertStringContainsString('<strong>', $result['data']['content']);
        } finally { $_POST = $previous; }
        self::assertTrue(method_exists(\GFrame\Session\DatabaseSessionHandler::class, 'gc'));
        self::assertTrue(method_exists(\GFrame\Notifications\NotificationService::class, 'cleanup'));
        $heartbeat = file_get_contents(dirname(__DIR__) . '/docs/heartbeat.md');
        preg_match_all('/```php\R(.*?)\R```/s', $heartbeat, $examples);
        foreach ($examples[1] as $code) {
            $php = str_starts_with($code, '<?php') ? $code : (str_starts_with($code, "'session'") ? '<?php return [' . $code . '];' : '<?php ' . $code);
            if (str_starts_with($code, 'public function')) $php = '<?php class ExampleChannel { ' . $code . ' }';
            token_get_all($php, TOKEN_PARSE);
        }
    }
}
