<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class SkillsValidatorTest extends TestCase
{
    private string $temporary;

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir() . '/gframe-skills-validator-' . bin2hex(random_bytes(8));
        mkdir($this->temporary . '/skills', 0777, true);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporary, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->temporary);
    }

    private function fixture(string $name = 'gframe-fixture'): string
    {
        $directory = $this->temporary . '/skills/' . $name;
        mkdir($directory . '/agents', 0777, true);
        mkdir($directory . '/references');
        file_put_contents($directory . '/SKILL.md', "---\nname: {$name}\ndescription: Fixture for portable validation.\n---\n");
        file_put_contents($directory . '/agents/openai.yaml', "{}\n");
        return $directory;
    }

    private function runValidator(?array $arguments = null): array
    {
        $command = [PHP_BINARY, dirname(__DIR__) . '/bin/validate-skills.php', ...($arguments ?? ['--root', $this->temporary . '/skills'])];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output];
    }

    public function testCompleteCanonicalCopyWorksOutsideRepository(): void
    {
        $source = dirname(__DIR__) . '/skills';
        foreach (glob($source . '/gframe-*', GLOB_ONLYDIR) as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $destination = $this->temporary . '/skills/' . substr($file->getPathname(), strlen($source) + 1);
                if (!is_dir(dirname($destination))) mkdir(dirname($destination), 0777, true);
                copy($file->getPathname(), $destination);
                self::assertSame(hash_file('sha256', $file->getPathname()), hash_file('sha256', $destination));
            }
        }
        [$status, $output] = $this->runValidator();
        self::assertSame(0, $status, $output);
        self::assertStringContainsString('Skills válidos: 14', $output);
        [$sourceStatus, $sourceOutput] = $this->runValidator(['--root', $source]);
        self::assertSame(0, $sourceStatus, $sourceOutput);
        self::assertSame($sourceOutput, $output);
    }

    public function testBrokenLinkInsideReferenceIsDetected(): void
    {
        $directory = $this->fixture();
        file_put_contents($directory . '/references/nested.md', '[Missing](absent.md#fragment)');
        [$status, $output] = $this->runValidator();
        self::assertSame(1, $status);
        self::assertStringContainsString('nested.md', $output);
    }

    public function testImagesAndReferenceDefinitionsAreChecked(): void
    {
        $directory = $this->fixture();
        file_put_contents($directory . '/references/nested.md', "![Image](missing.png)\n[Guide][manual]\n[manual]: missing.md\n");
        [$status, $output] = $this->runValidator();
        self::assertSame(1, $status);
        self::assertStringContainsString('missing.png', $output);
        self::assertStringContainsString('missing.md', $output);
    }

    public function testExistingFileOutsideSkillsDoesNotProvePortability(): void
    {
        $directory = $this->fixture();
        file_put_contents($this->temporary . '/outside.md', 'Outside package');
        file_put_contents($directory . '/references/nested.md', '[Outside](../../../outside.md)');
        [$status] = $this->runValidator();
        self::assertSame(1, $status);
    }

    public function testExamplesRemoteUrlsAndFragmentsDoNotProduceMissingFileErrors(): void
    {
        $directory = $this->fixture();
        file_put_contents($directory . '/references/with space.md', '# Heading');
        file_put_contents($directory . '/references/guide.md', "```md\n[Example](missing.md)\n```\n`[Example](absent.md)`\n[Remote](https://example.test/page) [Local](#heading)\n[File](<with space.md>)\n[Encoded](with%20space.md#heading)\n");
        [$status, $output] = $this->runValidator();
        self::assertSame(0, $status, $output);
        self::assertStringContainsString('Enlaces locales comprobados: 2', $output);
    }

    public function testCompanionLinkRequiresCopiedDependency(): void
    {
        $directory = $this->fixture();
        file_put_contents($directory . '/references/shared.md', '[Shared](../../gframe-companion/SKILL.md)');
        self::assertSame(1, $this->runValidator()[0]);
        $this->fixture('gframe-companion');
        self::assertSame(0, $this->runValidator()[0]);
    }

    public function testInvalidUtf8InReferenceIsRejected(): void
    {
        $directory = $this->fixture();
        file_put_contents($directory . '/references/bad.md', "invalid\xFF");
        [$status, $output] = $this->runValidator();
        self::assertSame(1, $status);
        self::assertStringContainsString('UTF-8', $output);
    }

    public function testMissingRootAndUnknownArgumentsReturnFailure(): void
    {
        self::assertSame(1, $this->runValidator(['--root', $this->temporary . '/absent'])[0]);
        self::assertSame(2, $this->runValidator(['--unknown'])[0]);
    }
}
