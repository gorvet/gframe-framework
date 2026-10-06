<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LintCommandTest extends TestCase
{
    private string $temporary;

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir() . '/gframe lint fixture-' . bin2hex(random_bytes(8));
        mkdir($this->temporary);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporary, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->temporary);
    }

    private function write(string $relative, string $body): void
    {
        $path = $this->temporary . '/' . $relative;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
        file_put_contents($path, $body);
    }

    private function runLint(?array $arguments = null): array
    {
        $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/bin/lint.php', ...($arguments ?? ['--root', $this->temporary])], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output];
    }

    public static function newlyCoveredSources(): array
    {
        return [
            ['config/defaults.php'],
            ['resources/install/profiles.php'],
            ['resources/skeleton/app/views/home/home.php'],
            ['maintenance/internal.php'],
            ['bin/gframe-update'],
        ];
    }

    #[DataProvider('newlyCoveredSources')]
    public function testMalformedPublishedAndInternalPhpFailsLint(string $path): void
    {
        $this->write($path, "<?php if (\n");
        [$status, $output] = $this->runLint();
        self::assertSame(1, $status);
        self::assertStringContainsString(basename($path), $output);
    }

    public function testSyntaxCheckDoesNotExecuteCodeAndAcceptsShebang(): void
    {
        $this->write('config/defaults.php', "<?php file_put_contents(__DIR__ . '/executed.txt', 'unexpected'); throw new RuntimeException('Do not execute');\n");
        $this->write('bin/gframe-update', "#!/usr/bin/env php\n<?php echo 'Do not execute';\n");
        [$status, $output] = $this->runLint();
        self::assertSame(0, $status, $output);
        self::assertStringContainsString('Archivos comprobados: 2', $output);
        self::assertFileDoesNotExist($this->temporary . '/config/executed.txt');
    }

    public function testDependenciesAndUnselectedApplicationDirectoriesStayOutsideLint(): void
    {
        $this->write('src/valid.php', '<?php return true;');
        foreach (['vendor', 'packages', 'app', '.git'] as $directory) $this->write($directory . '/bad.php', '<?php if (');
        [$status, $output] = $this->runLint();
        self::assertSame(0, $status, $output);
        self::assertStringContainsString('Archivos comprobados: 1', $output);
    }

    public function testExistingSourceTestsAndModulesRemainCovered(): void
    {
        $this->write('src/valid.php', '<?php return true;');
        $this->write('tests/invalid.php', '<?php if (');
        $this->write('resources/modules/example/invalid.php', '<?php if (');
        [$status, $output] = $this->runLint();
        self::assertSame(1, $status);
        self::assertStringContainsString('tests', $output);
        self::assertStringContainsString('example', $output);
    }

    public function testEmptyMissingRootAndUnknownOptionsFail(): void
    {
        self::assertSame(1, $this->runLint()[0]);
        self::assertSame(1, $this->runLint(['--root', $this->temporary . '/missing'])[0]);
        self::assertSame(2, $this->runLint(['--unknown'])[0]);
    }
}
