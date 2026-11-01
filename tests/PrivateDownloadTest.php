<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Files\{PrivateFileStorage, PrivateDownloadService, DownloadGrantStore};
use GFrame\Http\FileResponse;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PrivateDownloadTest extends TestCase
{
    private string $root;
    private PrivateFileStorage $storage;
    private PrivateDownloadService $service;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/gframe-download-' . bin2hex(random_bytes(6));
        $this->storage = new PrivateFileStorage($this->root);
        file_put_contents($this->root . '/document.txt', '0123456789');
        $this->service = new PrivateDownloadService($this->storage);
        ConfigRepository::replace(['files' => ['downloads' => ['enabled' => true, 'audit' => false]]]);
    }

    protected function tearDown(): void
    {
        ConfigRepository::replace([]);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    private function denied(callable $operation): void
    {
        try { $operation(); self::fail('La operación debía rechazarse.'); }
        catch (RuntimeException $exception) { self::assertNotEmpty($exception->getMessage()); }
    }

    public function testIndependentGrantsAreHashedRevocableAndNotPredictable(): void
    {
        $a = $this->service->issue('document.txt', 'Documento.txt');
        $b = $this->service->issue('document.txt', 'Documento.txt');
        self::assertNotSame($a['token'], $b['token']);
        $stored = file_get_contents($this->root . '/.downloads/' . $a['grant_id'] . '.json');
        self::assertStringNotContainsString(explode('.', $a['token'])[1], $stored);
        self::assertSame(200, $this->service->resolve($a['token'])->status());
        $this->service->revoke($a['grant_id']);
        $this->denied(fn() => $this->service->resolve($a['token']));
        self::assertSame(200, $this->service->resolve($b['token'])->status());
        $this->denied(fn() => $this->service->resolve($b['grant_id'] . '.' . str_repeat('0', 64)));
    }

    public function testExpiryIdentityCurrentEntitlementAndDeletionAreIndependent(): void
    {
        $grant = $this->service->issue('document.txt', 'Documento.txt', ['user_id' => 8, 'ttl_seconds' => 0, 'context' => ['resource_id' => 9]]);
        $this->denied(fn() => $this->service->resolve($grant['token']));
        $this->denied(fn() => $this->service->resolve($grant['token'], 7));
        $this->denied(fn() => $this->service->resolve($grant['token'], 8, authorize: static fn() => false));
        self::assertSame(200, $this->service->resolve($grant['token'], 8, authorize: static fn(array $g) => $g['context']['resource_id'] === 9)->status());
        (new DownloadGrantStore($this->storage))->update($grant['grant_id'], static function (array &$g): void { $g['expires_at'] = time() - 1; });
        $this->denied(fn() => $this->service->resolve($grant['token'], 8));
        self::assertFileExists($this->root . '/document.txt');
        $other = $this->service->issue('document.txt', 'Documento.txt');
        unlink($this->root . '/document.txt');
        $this->denied(fn() => $this->service->resolve($other['token']));
        file_put_contents($this->root . '/document.txt', 'reemplazo');
        $this->denied(fn() => $this->service->resolve($other['token']));
    }

    public function testHeadAndInvalidRangesDoNotConsumeALimitedGrant(): void
    {
        $grant = $this->service->issue('document.txt', 'Documento.txt', ['max_downloads' => 1]);
        self::assertSame(200, $this->service->resolve($grant['token'], method: 'HEAD')->status());
        self::assertSame(416, $this->service->resolve($grant['token'], range: 'bytes=200-')->status());
        $response = $this->service->resolve($grant['token'], range: 'bytes=2-4');
        self::assertSame(206, $response->status());
        self::assertSame('bytes 2-4/10', $response->headers()['Content-Range']);
        self::assertSame('3', $response->headers()['Content-Length']);
        self::assertSame('private, no-store', $response->headers()['Cache-Control']);
        $this->denied(fn() => $this->service->resolve($grant['token']));
    }

    public function testDisabledLinksAndInvalidPoliciesFailClosed(): void
    {
        foreach ([-1, 'abc', 1.5] as $ttl) $this->denied(fn() => $this->service->issue('document.txt', 'Documento.txt', ['ttl_seconds' => $ttl]));
        $grant = $this->service->issue('document.txt', 'Documento.txt');
        ConfigRepository::replace([]);
        $this->denied(fn() => $this->service->issue('document.txt', 'Documento.txt'));
        $this->denied(fn() => $this->service->resolve($grant['token']));
    }

    public function testPathsAndResponseHeadersCannotEscapeTheirBoundaries(): void
    {
        foreach (['../outside', '/etc/passwd', 'C:/secret', 'a/../../b', 'a\\..\\b', "x\0y", '.downloads/test'] as $path) {
            $this->denied(fn() => $this->storage->file($path));
        }
        $response = new FileResponse($this->root . '/document.txt', "archivo\r\nInjected: yes.txt", "text/html\r\nX-Test: bad", true);
        self::assertSame('application/octet-stream', $response->headers()['Content-Type']);
        self::assertStringStartsWith('attachment;', $response->headers()['Content-Disposition']);
        self::assertStringNotContainsString("\r", $response->headers()['Content-Disposition']);
        self::assertSame('bytes 7-9/10', (new FileResponse($this->root . '/document.txt', 'x'))->range('bytes=-3')->headers()['Content-Range']);
    }

    public function testConcurrentWorkersCannotBothConsumeTheLastDownload(): void
    {
        $grant = $this->service->issue('document.txt', 'Documento.txt', ['max_downloads' => 1]);
        $worker = $this->root . '/worker.php';
        file_put_contents($worker, '<?php require ' . var_export(dirname(__DIR__) . '/packages/autoload.php', true) . '; '
            . '\GFrame\Config\ConfigRepository::replace(["files"=>["downloads"=>["enabled"=>true,"audit"=>false]]]); '
            . 'try {(new \GFrame\Files\PrivateDownloadService(new \GFrame\Files\PrivateFileStorage($argv[1])))->resolve($argv[2]); echo "allowed";} catch (\RuntimeException $e) {echo "denied";}');
        $processes = [];
        for ($i = 0; $i < 4; $i++) {
            $process = proc_open([PHP_BINARY, $worker, $this->root, $grant['token']], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $results[] = stream_get_contents($pipes[1]);
            fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0, proc_close($process));
        }
        self::assertSame(1, count(array_filter($results, static fn($result) => $result === 'allowed')));
        self::assertSame(3, count(array_filter($results, static fn($result) => $result === 'denied')));
    }
}
