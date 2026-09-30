<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class NewProjectCommandTest extends TestCase
{
    public function testHelpDoesNotCreateAProjectDirectory(): void
    {
        $root = dirname(__DIR__);
        $unexpected = $root . DIRECTORY_SEPARATOR . '--help';
        self::assertDirectoryDoesNotExist($unexpected);

        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'new-project.php')
            . ' --help';
        exec($command . ' 2>&1', $output, $exitCode);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Uso: composer new -- <directorio>', implode("\n", $output));
        self::assertStringContainsString('-h, --help', implode("\n", $output));
        self::assertDirectoryDoesNotExist($unexpected);
    }
}
