<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Modules\AuthUi\Controllers\AuthController;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SecurityPriorityTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testJsonLdCannotCreateHtmlButPreservesTheOriginalText(): void
    {
        define('site_url', 'https://example.test/');
        $payload = '</ScRiPt><script>alert(1)</script><!-- & "texto"';
        $json = (new \JsonLD())->renderSchema(
            ['type' => 'WebPage', 'title' => $payload],
            ['title' => $payload],
            ['lang' => 'es', 'currentURL' => site_url]
        );
        self::assertStringNotContainsString('<', $json);
        self::assertStringNotContainsString('>', $json);
        $graph = json_decode($json, true, 512, JSON_THROW_ON_ERROR)['@graph'];
        $page = array_values(array_filter($graph, static fn(array $node): bool => in_array('WebPage', (array)($node['@type'] ?? []), true)))[0];
        self::assertSame($payload, $page['name']);
        define('ABSPATH', sys_get_temp_dir() . '/gframe-meta-no-config/');
        define('GFRAME_PATH', dirname(__DIR__) . '/src/');
        $meta = new \Meta();
        $meta->applyMetaConfig(['schema' => ['type' => 'WebPage'], 'metaTags' => ['title' => $payload]]);
        $html = $meta->renderSchema();
        self::assertSame(1, substr_count(strtolower($html), '</script>'));
        $document = new \DOMDocument();
        $document->loadHTML($html);
        self::assertSame(1, $document->getElementsByTagName('script')->length);
        self::assertSame('application/ld+json', $document->getElementsByTagName('script')->item(0)->getAttribute('type'));
    }

    #[RunInSeparateProcess]
    public function testSecurityBaseRejectsAmbiguousUrlsAndNeverUsesRequestHost(): void
    {
        putenv('APP_URL');
        unset($_ENV['APP_URL'], $_SERVER['APP_URL']);
        $_SERVER['HTTP_HOST'] = 'evil.test';
        foreach (['', '/relative', '//evil.test', 'file:///tmp/file', 'https://user:pass@example.test',
            'https://example.test?redirect=evil', 'https://example.test#fragment',
            'https://example.test\\evil', "https://example.test/\npath", 'https://example.test:99999'] as $url) {
            ConfigRepository::replace(['app' => ['url' => $url]]);
            self::assertNull(\UrlHelper::canonicalBase(), $url);
        }
        ConfigRepository::replace(['app' => ['url' => 'https://example.test:8443/app/']]);
        self::assertSame('https://example.test:8443/app/', \UrlHelper::canonicalBase());
        ConfigRepository::replace([]);
        $_ENV['APP_URL'] = 'https://legacy.test/app';
        self::assertSame('https://legacy.test/app/', \UrlHelper::canonicalBase());
    }

    #[RunInSeparateProcess]
    public function testRealMailBuilderUsesCanonicalBaseAndBlocksUnconfiguredMail(): void
    {
        require_once __DIR__ . '/AuthModelTest.php';
        require_once dirname(__DIR__) . '/resources/modules/auth-ui/application/app/controllers/auth-ui/AuthController.php';
        require_once __DIR__ . '/NotificationEmailTest.php';
        define('site_url', 'https://evil.test/');
        putenv('APP_URL');
        unset($_ENV['APP_URL'], $_SERVER['APP_URL']);
        $sender = new MemoryMailSender();
        $controller = new class(new InMemoryAuthModel(), accessMail: new \GFrame\Mail\AccessMailService($sender)) extends AuthController {
            public function sendTestLink(): bool
            {
                return $this->sendAccessMail('user@example.test', 'Access', 'Open', '/login/resetpassword?rp=test-token', 'Test');
            }
        };
        ConfigRepository::replace(['app' => ['url' => 'https://trusted.test/app']]);
        self::assertTrue($controller->sendTestLink());
        $mail = $sender->last;
        self::assertSame('https://trusted.test/app/login/resetpassword?rp=test-token', $mail['variables']['aHref']);
        $sender->last = [];
        ConfigRepository::replace(['app' => ['url' => '']]);
        self::assertFalse($controller->sendTestLink());
        self::assertSame([], $sender->last);
    }

    #[RunInSeparateProcess]
    public function testMissingMailConfigurationDoesNotIdentifyRecoveryAccounts(): void
    {
        require_once __DIR__ . '/AuthModelTest.php';
        require_once dirname(__DIR__) . '/resources/modules/auth-ui/application/app/controllers/auth-ui/AuthController.php';
        putenv('APP_URL');
        unset($_ENV['APP_URL'], $_SERVER['APP_URL']);
        ConfigRepository::replace(['app' => ['url' => '']]);
        $auth = new InMemoryAuthModel();
        $auth->registerAcount('existing@example.test', 'Password-123');
        require_once __DIR__ . '/NotificationEmailTest.php';
        $sender = new MemoryMailSender();
        $sender->fail = true;
        $controller = new AuthController($auth, accessMail: new \GFrame\Mail\AccessMailService($sender));
        foreach (['', 'https://trusted.test/app/'] as $base) {
            ConfigRepository::replace(['app' => ['url' => $base]]);
            $_POST = ['recovery_email' => 'existing@example.test'];
            $existing = $controller->recovery();
            $_POST = ['recovery_email' => 'absent@example.test'];
            self::assertSame($existing, $controller->recovery());
            self::assertSame('recovery_requested', $existing['code']);
            self::assertArrayNotHasKey('token', (array)($existing['data'] ?? []));
        }
        self::assertSame('existing@example.test', $sender->last['recipient']);
    }
}
