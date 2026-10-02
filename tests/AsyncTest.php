<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class AsyncTest extends TestCase
{
    public function testResolvesARealCliBinary(): void
    {
        $async = new class extends \Async { public function binary(): string { return $this->cliBinary(); } };
        self::assertSame(realpath(PHP_BINARY), $async->binary());
    }

    public function testInvalidConfiguredBinaryFailsInsteadOfReportingQueued(): void
    {
        $previous = $_ENV['GFRAME_PHP_BINARY'] ?? null;
        $_ENV['GFRAME_PHP_BINARY'] = __FILE__ . '.missing';
        try {
            $async = new class extends \Async { public function binary(): string { return $this->cliBinary(); } };
            $this->expectException(\RuntimeException::class);
            $async->binary();
        } finally {
            if ($previous === null) unset($_ENV['GFRAME_PHP_BINARY']);
            else $_ENV['GFRAME_PHP_BINARY'] = $previous;
        }
    }
}
