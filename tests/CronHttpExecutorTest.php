<?php

namespace GFrame\Tests;

use GFrame\Config\ConfigRepository;
use GFrame\Cron\CronHttpEndpoint;
use GFrame\Cron\CronHttpLauncher;
use PHPUnit\Framework\TestCase;

final class CronHttpExecutorTest extends TestCase
{
    private array $environment;
    private array $config;

    protected function setUp(): void
    {
        $this->environment = $_ENV;
        $this->config = ConfigRepository::get();
        $_ENV['GFRAME_CRON_MODE'] = 'real';
        $_ENV['GFRAME_CRON_EXECUTOR'] = 'http';
        $_ENV['GFRAME_CRON_HTTP_SECRET'] = str_repeat('a', 48);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->environment;
        ConfigRepository::replace($this->config);
    }

    public function testUnauthorizedRequestsNeverReachDatabase(): void
    {
        self::assertSame(405, CronHttpEndpoint::handle(['REQUEST_METHOD' => 'GET'])['http_code']);
        self::assertSame(403, CronHttpEndpoint::handle(['REQUEST_METHOD' => 'POST'])['http_code']);
        self::assertSame(403, CronHttpEndpoint::handle(['REQUEST_METHOD' => 'POST', 'HTTP_AUTHORIZATION' => 'Bearer bad'])['http_code']);
        $_ENV['GFRAME_CRON_EXECUTOR'] = 'cli';
        self::assertSame(404, CronHttpEndpoint::handle(['REQUEST_METHOD' => 'POST'])['http_code']);
        $_ENV['GFRAME_CRON_EXECUTOR'] = 'http';
        $_ENV['GFRAME_CRON_HTTP_SECRET'] = '';
        self::assertSame(404, CronHttpEndpoint::handle(['REQUEST_METHOD' => 'POST'])['http_code']);
    }

    public function testVirtualHttpRequiresTicketBeforeAccessingStorage(): void
    {
        $_ENV['GFRAME_CRON_MODE'] = 'virtual';
        foreach ([null, [], 'arbitrary'] as $ticket) {
            $result = CronHttpEndpoint::handle(['REQUEST_METHOD' => 'POST', 'HTTP_AUTHORIZATION' => 'Bearer ' . str_repeat('a', 48), 'HTTP_X_GFRAME_CRON_TICKET' => $ticket]);
            self::assertSame('invalid_cron_ticket', $result['code']);
        }
    }

    public function testTransportSendsHeadersWithoutWaitingForResponse(): void
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($listener, false);
        $_ENV['APP_URL'] = 'http://' . $address . '/nested';
        (new CronHttpLauncher())->launch(str_repeat('b', 48));
        $connection = stream_socket_accept($listener, 2);
        self::assertIsResource($connection);
        $request = stream_get_contents($connection);
        fclose($connection); fclose($listener);
        self::assertStringContainsString('POST /nested/gframe-cron.php HTTP/1.1', $request);
        self::assertStringContainsString('Authorization: Bearer ' . str_repeat('a', 48), $request);
        self::assertStringContainsString('X-GFrame-Cron-Ticket: ' . str_repeat('b', 48), $request);
        self::assertStringNotContainsString('?', explode("\r\n", $request)[0]);
    }

    public function testUntrustedOrMalformedConfiguredUrlsAreRejected(): void
    {
        foreach (['file:///tmp/test', 'http://user@example.test', 'http://example.test/?token=x', "http://example.test/\r\nInjected: yes"] as $url) {
            $_ENV['APP_URL'] = $url;
            try { (new CronHttpLauncher())->launch(str_repeat('b', 48)); self::fail('URL inválida aceptada'); }
            catch (\RuntimeException $exception) { self::assertStringContainsString('APP_URL', $exception->getMessage()); }
        }
    }

    public function testRealAndVirtualHttpUseTheSameExecutorWithoutCliLaunch(): void
    {
        $root = sys_get_temp_dir() . '/gframe-http-executor-' . bin2hex(random_bytes(6));
        foreach (['core', 'config/routes', 'config/cron', 'app/services', 'storage'] as $directory) mkdir($root . '/' . $directory, 0775, true);
        $autoload = var_export(dirname(__DIR__) . '/packages/autoload.php', true);
        file_put_contents($root . '/core/Load.php', '<?php require_once ' . $autoload . '; GFrame\Foundation\Bootstrap::boot(dirname(__DIR__));');
        copy(dirname(__DIR__) . '/resources/modules/cron-runner/application/gframe-cron.php', $root . '/gframe-cron.php');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false); fclose($socket);
        file_put_contents($root . '/config/app.php', '<?php return ' . var_export(['app' => ['url' => 'http://' . $address], 'database' => ['default' => 'main', 'connections' => ['main' => ['driver' => 'sqlite', 'path' => $root . '/tasks.sqlite']]]], true) . ';');
        file_put_contents($root . '/storage/gframe-installed.json', '{"modules":["cron-runner"]}');
        file_put_contents($root . '/app/services/HttpTestHandler.php', '<?php final class HttpTestHandler extends Cron {public function handle(array $task=[]):array {file_put_contents(ABSPATH."storage/effect", "x", FILE_APPEND); return ["status"=>"success"];}}');
        file_put_contents($root . '/trigger.php', '<?php require __DIR__."/core/Load.php"; GFrame\Cron\VirtualCron::register("web"); header("X-Cron-Registered: ".((new ReflectionProperty(GFrame\Cron\VirtualCron::class,"registered"))->getValue()?"yes":"no")); echo "ok";');
        $database = new \PDO('sqlite:' . $root . '/tasks.sqlite');
        $database->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/cron-runner/database/sqlite.sql'));
        $database->exec("INSERT INTO cron_tasks(task_key,handler_class,payload_json,scheduled_at) VALUES ('http','HttpTestHandler','{}','2000-01-01 00:00:00')");
        $environment = "GFRAME_CRON_EXECUTOR=http\nGFRAME_CRON_HTTP_SECRET=" . str_repeat('c', 48) . "\nAPP_URL=http://" . $address . "\n";
        file_put_contents($root . '/.env', $environment . "GFRAME_CRON_MODE=real\n");
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $processEnvironment = getenv();
        foreach (['GFRAME_CRON_EXECUTOR', 'GFRAME_CRON_MODE', 'GFRAME_CRON_HTTP_SECRET', 'APP_URL'] as $key) unset($processEnvironment[$key]);
        $server = proc_open([PHP_BINARY, '-S', $address, '-t', $root], [0 => ['pipe','r'], 1 => ['file',$null,'a'], 2 => ['file',$root.'/server.log','a']], $pipes, $root, $processEnvironment);
        fclose($pipes[0]);
        try {
            $deadline = microtime(true) + 8;
            do { $ready = @stream_socket_client('tcp://' . $address, $error, $message, .1); if ($ready) {fclose($ready); break;} usleep(20000); } while (microtime(true) < $deadline);
            self::assertSame('ok', file_get_contents('http://' . $address . '/trigger.php'));
            self::assertContains('X-Cron-Registered: no', $http_response_header);
            self::assertFileDoesNotExist($root . '/storage/effect');
            self::assertNull($database->query('SELECT last_launch_at FROM cron_runtime')->fetchColumn());
            $post = static function (string $secret, ?string $ticket = null) use ($address): array {
                $headers = "Content-Type: application/x-www-form-urlencoded\r\nAuthorization: Bearer " . $secret . ($ticket === null ? '' : "\r\nX-GFrame-Cron-Ticket: " . $ticket);
                return json_decode(file_get_contents('http://' . $address . '/gframe-cron.php?handler=MissingHandler&limit=0', false, stream_context_create(['http'=>['method'=>'POST','header'=> $headers, 'content'=>'handler=MissingHandler&limit=0', 'ignore_errors'=>true]])), true, 512, JSON_THROW_ON_ERROR);
            };
            self::assertSame('forbidden', $post('wrong')['code']);
            self::assertFileDoesNotExist($root . '/storage/effect');
            self::assertSame('cron_processed', $post(str_repeat('c',48))['code']);
            self::assertSame('x', file_get_contents($root . '/storage/effect'));
            $database->exec("UPDATE cron_tasks SET status='pending'");
            file_put_contents($root . '/.env', $environment . "GFRAME_CRON_MODE=virtual\n");
            self::assertSame('invalid_cron_ticket', $post(str_repeat('c',48))['code']);
            self::assertSame('cron_skipped', $post(str_repeat('c',48), str_repeat('d',48))['code']);
            self::assertSame('x', file_get_contents($root . '/storage/effect'));
            self::assertSame('ok', file_get_contents('http://' . $address . '/trigger.php'));
            self::assertContains('X-Cron-Registered: yes', $http_response_header);
            $deadline = microtime(true) + 8;
            while (file_get_contents($root . '/storage/effect') !== 'xx' && microtime(true) < $deadline) usleep(20000);
            self::assertSame('xx', file_get_contents($root . '/storage/effect'), file_get_contents($root . '/server.log'));
        } finally {
            proc_terminate($server); proc_close($server); unset($database);
            $entries = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($entries as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            rmdir($root);
        }
    }
}
