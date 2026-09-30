<?php

namespace GFrame\Tests;

use GFrame\Headless\WordPressClient;
use GFrame\Install\ProjectConfigWriter;
use GFrame\Modules\ModuleAssetPublisher;
use GFrame\Modules\ModuleCatalog;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WordPressHeadlessTest extends TestCase
{
    private array $environment = [];

    protected function tearDown(): void
    {
        foreach ($this->environment as $key => $value) {
            if ($value === null) unset($_ENV[$key], $_SERVER[$key]); else $_ENV[$key] = $value;
            putenv($value === null ? $key : $key . '=' . $value);
        }
    }

    public function testClientLoadsConfigurationFromEnvironment(): void
    {
        $this->setEnvironment('WORDPRESS_HEADLESS_URL', 'https://cms.example.test');
        $this->setEnvironment('WORDPRESS_HEADLESS_TOKEN', 'secret-token');
        $request = [];
        $envelope = $this->envelope(['id' => 7, 'html' => '<p>Contenido</p>']);
        $client = WordPressClient::fromEnvironment(static function (array $args) use (&$request, $envelope): array {
            $request = $args;
            return ['ok' => true, 'status' => 200, 'json' => $envelope];
        });

        $result = $client->content('inicio');
        self::assertSame('wordpress_content_loaded', $result['code']);
        self::assertSame(7, $result['data']['id']);
        self::assertSame('https://cms.example.test/wp-json/bridgeframe/v2/html', $request['url']);
        self::assertContains('Authorization: Bearer secret-token', $request['headers']);
        self::assertTrue($request['verify_peer']);
        self::assertTrue($request['verify_host']);
    }

    public function testClientSupportsListTermsMenuAndSchemaEndpoints(): void
    {
        $endpoints = [];
        $envelope = $this->envelope(['items' => []]);
        $client = new WordPressClient('https://cms.example.test', 'token', static function (array $args) use (&$endpoints, $envelope): array {
            $endpoints[] = $args['url'];
            return ['ok' => true, 'status' => 200, 'json' => $envelope];
        });
        self::assertSame('wordpress_content_listed', $client->contents(['type' => 'post'])['code']);
        self::assertSame('wordpress_terms_loaded', $client->terms()['code']);
        self::assertSame('wordpress_menu_loaded', $client->menu(['location' => 'primary'])['code']);
        self::assertSame('wordpress_schema_loaded', $client->schema()['code']);
        self::assertStringEndsWith('/list', $endpoints[0]);
        self::assertStringEndsWith('/terms', $endpoints[1]);
        self::assertStringEndsWith('/menu', $endpoints[2]);
        self::assertStringEndsWith('/schema', $endpoints[3]);
    }

    public function testClientNormalizesConfigurationAndHttpErrors(): void
    {
        self::assertSame('wordpress_url_not_configured', (new WordPressClient())->content('inicio')['code']);
        self::assertSame('insecure_wordpress_url', (new WordPressClient('http://cms.test', 'token'))->content('inicio')['code']);
        self::assertSame('wordpress_token_not_configured', (new WordPressClient('https://cms.test'))->content('inicio')['code']);
        $unauthorized = new WordPressClient('https://cms.test', 'bad', static fn(): array => ['ok' => false, 'status' => 403, 'json' => ['error' => 'Token inválido']]);
        self::assertSame('wordpress_unauthorized', $unauthorized->content('inicio')['code']);
        $missing = new WordPressClient('https://cms.test', 'token', static fn(): array => ['ok' => false, 'status' => 404, 'json' => ['error' => 'No encontrado']]);
        self::assertSame('wordpress_not_found', $missing->content('inicio')['code']);
    }

    public function testClientConvertsConnectionFailuresIntoStableContract(): void
    {
        $client = new WordPressClient('https://cms.test', 'token', static function (): array {
            throw new RuntimeException('Connection details');
        });
        self::assertSame(['status' => 'error', 'code' => 'wordpress_connection_failed'], $client->content('inicio'));
    }

    public function testClientRejectsLegacyOrUnversionedResponses(): void
    {
        $client = new WordPressClient('https://cms.test', 'token', static fn(): array => [
            'ok' => true, 'status' => 200, 'json' => ['id' => 1, 'html' => '<p>Legacy</p>'],
        ]);
        self::assertSame('wordpress_contract_mismatch', $client->content('inicio')['code']);
    }

    public function testModuleEnvironmentKeysAreWrittenDuringInstallation(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gframe-wp-' . bin2hex(random_bytes(5));
        mkdir($root, 0775, true);
        $files = (new ProjectConfigWriter())->write(
            $root,
            ['app_name' => 'WordPress client'],
            ['wordpress-headless'],
            false,
            ['WORDPRESS_HEADLESS_URL', 'WORDPRESS_HEADLESS_TOKEN']
        );
        $environment = (string)file_get_contents($files['environment']);
        self::assertStringContainsString('WORDPRESS_HEADLESS_URL=""', $environment);
        self::assertStringContainsString('WORDPRESS_HEADLESS_TOKEN=""', $environment);
        $this->remove($root);
    }

    public function testWordPressHeadlessPublishesItsOwnStyles(): void
    {
        $catalog = ModuleCatalog::frameworkDefault();
        self::assertArrayNotHasKey('wordpress-styles', $catalog->all());

        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gframe-wp-styles-' . bin2hex(random_bytes(5));
        mkdir($root, 0775, true);
        try {
            $result = (new ModuleAssetPublisher($catalog))->publish(['wordpress-headless'], $root);
            self::assertContains('wordpress-headless', $result['modules']);
            $destination = $root . DIRECTORY_SEPARATOR . 'vendors' . DIRECTORY_SEPARATOR . 'internal'
                . DIRECTORY_SEPARATOR . 'wp' . DIRECTORY_SEPARATOR;
            self::assertFileExists($destination . 'style.min.css');
            self::assertFileExists($destination . 'wordpress-theme.css');
            self::assertStringContainsString('--bs-primary', (string)file_get_contents($destination . 'wordpress-theme.css'));
        } finally {
            $this->remove($root);
        }
    }

    private function setEnvironment(string $key, string $value): void
    {
        $this->environment[$key] = $_ENV[$key] ?? null;
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }

    private function envelope(array $data): array
    {
        return ['status' => 'success', 'code' => 'ok', 'data' => $data, 'meta' => ['contract_version' => '2.0']];
    }

    private function remove(string $path): void
    {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
            $target = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($target)) $this->remove($target); else unlink($target);
        }
        rmdir($path);
    }
}
