<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class LogHelperTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/gframe-log-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0775, true);
        if (!defined('ABSPATH')) {
            define('ABSPATH', $this->root);
        }
        ConfigRepository::replace([]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/logs/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->root . '/logs');
        @rmdir($this->root);
    }

    private function contents(string $channel): string
    {
        $file = $this->root . '/logs/' . $channel . '.txt';
        return is_file($file) ? (string)file_get_contents($file) : '';
    }

    public function testWriteKeepsPlainBackwardCompatibleFormat(): void
    {
        \LogHelper::write('mensaje simple', 'legacy');
        \LogHelper::write(['a' => 1], 'legacy');

        $log = $this->contents('legacy');
        self::assertStringContainsString('mensaje simple', $log);
        self::assertStringContainsString('----', $log);
        self::assertStringContainsString('[a] => 1', $log);
    }

    public function testLeveledEntryIncludesLevelAndContextJson(): void
    {
        \LogHelper::info('Usuario autenticado', ['user_id' => 7], 'app');

        $log = $this->contents('app');
        self::assertStringContainsString('INFO Usuario autenticado', $log);
        self::assertStringContainsString('"user_id":7', $log);
    }

    public function testRedactsSensitiveContextKeysRecursively(): void
    {
        \LogHelper::error('Fallo de acceso', [
            'email' => 'ada@example.test',
            'password' => 'secreta',
            'token' => 'abc',
            'nested' => ['api_key' => 'k', 'ok' => 1],
        ], 'sec');

        $log = $this->contents('sec');
        self::assertStringContainsString('ada@example.test', $log);
        self::assertStringContainsString('[redacted]', $log);
        self::assertStringNotContainsString('secreta', $log);
        self::assertStringNotContainsString('abc', $log);
        self::assertStringNotContainsString('"api_key":"k"', $log);
        self::assertStringContainsString('"ok":1', $log);
    }

    public function testRedactsSensitivePropertiesInsideObjects(): void
    {
        $credentials = (object)['user' => 'ada', 'password' => 'secreta'];
        \LogHelper::error('Objeto con secreto', ['credenciales' => $credentials, 'auth' => (object)['token' => 'abc']], 'obj');

        $log = $this->contents('obj');
        self::assertStringContainsString('"user":"ada"', $log);
        self::assertStringContainsString('[redacted]', $log);
        self::assertStringNotContainsString('secreta', $log);
        self::assertStringNotContainsString('abc', $log);
    }

    public function testThresholdSkipsEntriesBelowConfiguredLevel(): void
    {
        ConfigRepository::replace(['logging' => ['level' => 'warning']]);

        \LogHelper::debug('invisible', [], 'th');
        \LogHelper::info('tampoco', [], 'th');
        \LogHelper::warning('visible', [], 'th');
        \LogHelper::error('tambien', [], 'th');

        $log = $this->contents('th');
        self::assertStringNotContainsString('invisible', $log);
        self::assertStringNotContainsString('tampoco', $log);
        self::assertStringContainsString('WARNING visible', $log);
        self::assertStringContainsString('ERROR tambien', $log);
    }

    public function testRotatesWhenFileExceedsMaxBytes(): void
    {
        ConfigRepository::replace(['logging' => ['max_bytes' => 40]]);

        \LogHelper::info(str_repeat('a', 60), [], 'rot');
        \LogHelper::info(str_repeat('b', 60), [], 'rot');

        self::assertFileExists($this->root . '/logs/rot.txt.1');
        self::assertStringContainsString('bbbbb', $this->contents('rot'));
        self::assertStringNotContainsString('aaaaa', $this->contents('rot'));
    }

    public function testRotationDisabledWhenMaxBytesZero(): void
    {
        ConfigRepository::replace(['logging' => ['max_bytes' => 0]]);

        \LogHelper::info(str_repeat('a', 100), [], 'noroll');
        \LogHelper::info(str_repeat('b', 100), [], 'noroll');

        self::assertFileDoesNotExist($this->root . '/logs/noroll.txt.1');
        self::assertStringContainsString('aaaaa', $this->contents('noroll'));
        self::assertStringContainsString('bbbbb', $this->contents('noroll'));
    }
}
