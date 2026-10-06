<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class AsyncTest extends TestCase
{
    public function testClosureRoundTripPreservesCapturedDataWithoutPhpNotices(): void
    {
        $recipient = 'contact@example.test';
        $variables = ['message' => 'Prueba de contacto'];
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $serialized = \ClosureWrapper::serialize(static fn(): array => [$recipient, $variables]);
            $closure = \ClosureWrapper::unserialize($serialized);
            self::assertSame([$recipient, $variables], $closure());
        } finally {
            restore_error_handler();
        }
    }

    public function testReadsTaskSerializedByOpis37WithoutPhpNotices(): void
    {
        $serialized = file_get_contents(__DIR__ . '/fixtures/async-opis3.serialized');
        self::assertIsString($serialized);
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $closure = \ClosureWrapper::unserialize($serialized);
            self::assertSame(['legacy@example.test', ['message' => 'Tarea creada con Opis 3.7']], $closure());
            self::assertSame($closure(), \ClosureWrapper::unserialize(\ClosureWrapper::serialize($closure))());
        } finally {
            restore_error_handler();
        }
    }

    public function testCapturedObjectAndNestedClosureSurviveRoundTrip(): void
    {
        $object = (object)['recipient' => 'contact@example.test'];
        $prefix = 'Hola';
        $nested = static fn(string $value): string => $prefix . ' ' . $value;
        $closure = \ClosureWrapper::unserialize(\ClosureWrapper::serialize(
            static fn(): string => $nested($object->recipient)
        ));
        self::assertSame('Hola contact@example.test', $closure());
    }

    public function testPayloadRunsInAnotherPhpProcess(): void
    {
        $value = 'worker';
        $payload = \ClosureWrapper::serialize(static fn(): string => $value);
        $code = 'require ' . var_export(dirname(__DIR__) . '/packages/autoload.php', true) . ';'
            . 'echo ClosureWrapper::unserialize(base64_decode($argv[1]))();';
        $output = [];
        $status = 0;
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' '
            . escapeshellarg(base64_encode($payload)), $output, $status);
        self::assertSame(0, $status);
        self::assertSame('worker', implode("\n", $output));
    }

    public function testRejectsPayloadThatDoesNotContainAClosure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \ClosureWrapper::unserialize(serialize(['message' => 'invalid job']));
    }

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
