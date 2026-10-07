<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class FrameworkContextCommandTest extends TestCase
{
    private string $temporary;

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir() . '/gframe-context-' . bin2hex(random_bytes(8));
        mkdir($this->temporary);
    }

    protected function tearDown(): void
    {
        $resolved = realpath($this->temporary);
        $prefix = rtrim((string)realpath(sys_get_temp_dir()), '/\\') . DIRECTORY_SEPARATOR;
        if ($resolved === false || !str_starts_with($resolved, $prefix) || !preg_match('/^gframe-context-[a-f0-9]{16}$/', basename($resolved))) throw new \RuntimeException('Ruta temporal inválida.');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporary, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->temporary);
    }

    private function write(string $path, string $content): void
    {
        $path = $this->temporary . '/' . $path;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
        file_put_contents($path, $content);
    }

    private function context(string $project): array
    {
        $process = proc_open([PHP_BINARY, '-n', dirname(__DIR__) . '/bin/gframe-context.php', '--project=' . $project], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame('', $errors);
        return [proc_close($process), json_decode($output, true, 512, JSON_THROW_ON_ERROR)];
    }

    public function testCustomVendorMetadataSelectsItsOwnSkillsWithoutExecutingPhp(): void
    {
        $this->write('project with spaces/composer.json', '{"name":"project/app","config":{"vendor-dir":"libraries"}}');
        $this->write('project with spaces/libraries/composer/installed.json', '{"packages":[{"name":"gorvet/gframe","version":"1.2.3","install-path":"../gorvet/gframe","source":{"reference":"fixture-ref"}}]}');
        $this->write('project with spaces/libraries/gorvet/gframe/composer.json', '{"name":"gorvet/gframe"}');
        $this->write('project with spaces/libraries/gorvet/gframe/skills/gframe-backend/SKILL.md', 'Fixture');
        $this->write('project with spaces/storage/gframe-installed.json', '{"modules":["auth-ui","media-library"]}');
        $this->write('project with spaces/libraries/autoload.php', '<?php throw new RuntimeException("No debe ejecutarse");');
        $this->write('project with spaces/config/app.php', '<?php throw new RuntimeException("No debe ejecutarse");');
        $this->write('project with spaces/.env', 'PRIVATE_SECRET=must-not-be-read');
        [$status, $result] = $this->context($this->temporary . '/project with spaces');
        self::assertSame(0, $status);
        self::assertSame('application', $result['scope']);
        self::assertSame('1.2.3', $result['package']['version']);
        self::assertSame('fixture-ref', $result['package']['reference']);
        self::assertSame('gframe-backend', $result['canonical_skills'][0]['name']);
        self::assertSame(['auth-ui', 'media-library'], $result['installed_modules']);
        self::assertTrue($result['autoload']['exists']);
        self::assertFalse($result['application_booted']);
        self::assertStringNotContainsString('must-not-be-read', json_encode($result));
    }

    public function testFrameworkDoesNotInventAReleaseVersion(): void
    {
        [$status, $result] = $this->context(dirname(__DIR__));
        self::assertSame(0, $status);
        self::assertSame('framework', $result['scope']);
        self::assertNull($result['package']['version']);
        self::assertCount(14, $result['canonical_skills']);
    }

    public function testMissingInstalledMetadataDoesNotFallBackToThisCheckout(): void
    {
        $this->write('composer.json', '{"name":"project/app"}');
        [$status, $result] = $this->context($this->temporary);
        self::assertSame(1, $status);
        self::assertSame('context_unavailable', $result['code']);
        self::assertArrayNotHasKey('canonical_skills', $result);
    }

    public function testMalformedPackageListReturnsAControlledError(): void
    {
        $this->write('composer.json', '{"name":"project/app"}');
        $this->write('vendor/composer/installed.json', '{"packages":"invalid"}');
        [$status, $result] = $this->context($this->temporary);
        self::assertSame(1, $status);
        self::assertSame('context_unavailable', $result['code']);
    }
}
