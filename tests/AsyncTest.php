<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AsyncTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testLargePayloadRunsFromDatabaseWithoutTemporaryJobFiles(): void
    {
        $root = sys_get_temp_dir() . '/gframe-async-db-' . bin2hex(random_bytes(8));
        mkdir($root . '/config/routes', 0775, true);
        $path = $root . '/state.sqlite';
        $database = new \PDO('sqlite:' . $path);
        \GFrame\Install\MigrationRunner::frameworkDefault()->migrate($database, 'sqlite', ['runtime-storage']);
        $database->exec('CREATE TABLE effects (value TEXT NOT NULL)');
        file_put_contents($root . '/config/app.php', '<?php return ' . var_export(['database' => ['default' => 'main', 'connections' => ['main' => ['driver' => 'sqlite', 'path' => $path]]]], true) . ';');
        try {
            \GFrame\Foundation\Bootstrap::boot($root);
            $large = str_repeat('database payload ', 1000);
            (new \Async())->create(static function () use ($large): void {
                $database = \DatabaseManager::connection();
                $database->prepare('INSERT INTO effects (value) VALUES (?)')->execute([$large]);
            });
            $deadline = microtime(true) + 15;
            do {
                $result = $database->query('SELECT value FROM effects')->fetchColumn();
                if ($result !== false) break;
                usleep(100000);
            } while (microtime(true) < $deadline);
            self::assertSame($large, $result);
            self::assertSame(0, (int)$database->query('SELECT COUNT(*) FROM gframe_runtime_state')->fetchColumn());
        } finally {
            \DatabaseManager::disconnect();
            $database = null;
            foreach (glob($root . '/*.sqlite*') ?: [] as $file) unlink($file);
            unlink($root . '/config/app.php');
            rmdir($root . '/config/routes'); rmdir($root . '/config'); rmdir($root);
        }
    }

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
