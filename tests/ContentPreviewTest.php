<?php
namespace GFrame\Tests;

use GFrame\Content\{ContentDraftService, ContentPreviewController};
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ContentPreviewTest extends TestCase
{
    public function testHttpPreviewIsPrivateAndDoesNotExposeContentInState(): void
    {
        $root = sys_get_temp_dir() . '/gframe-preview-http-' . bin2hex(random_bytes(5)); mkdir($root);
        $router = $root . '/index.php';
        $autoload = var_export(dirname(__DIR__) . '/packages/autoload.php', true);
        $schema = var_export(dirname(__DIR__) . '/resources/modules/content-autosave/database/sqlite.sql', true);
        file_put_contents($router, '<?php require ' . $autoload . ';session_start();$_SESSION["auth"]=["id"=>7];define("DB_DEFAULT_CONNECTION","test");define("DB_CONNECTIONS",["test"=>["driver"=>"sqlite","path"=>":memory:"]]);DatabaseManager::connection()->exec(file_get_contents(' . $schema . '));header("Content-Type: application/json");echo json_encode((new GFrame\Content\ContentPreviewController())->state("authorized:1","r1","Private page"));');
        $socket = stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);
        $server = proc_open([PHP_BINARY,'-S',$address,$router],[0=>['pipe','r'],1=>['file',$root.'/out.log','a'],2=>['file',$root.'/err.log','a']],$pipes);
        self::assertIsResource($server);fclose($pipes[0]);
        try {
            $deadline=microtime(true)+5;
            do { $connection=@stream_socket_client('tcp://'.$address,$number,$message,0.1);if($connection){fclose($connection);break;}usleep(10000); } while(microtime(true)<$deadline);
            $body=file_get_contents('http://'.$address.'/');
            self::assertContains('Cache-Control: private, no-store',$http_response_header);
            self::assertContains('X-Robots-Tag: noindex, nofollow, noarchive',$http_response_header);
            self::assertContains('Vary: Cookie',$http_response_header);
            self::assertArrayNotHasKey('content',json_decode($body,true)['data']);
            self::assertStringNotContainsString('Private page',$body);
        } finally {proc_terminate($server);proc_close($server);foreach(glob($root.'/*') as $file)unlink($file);rmdir($root);}
    }

    #[RunInSeparateProcess]
    public function testPrivateCopyFallbackScopeAndVersionWithoutPublishing(): void
    {
        ini_set('session.use_cookies', '0'); session_start();
        define('DB_DEFAULT_CONNECTION', 'preview');
        define('DB_CONNECTIONS', ['preview' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        \DatabaseManager::connection()->exec(file_get_contents(dirname(__DIR__) . '/resources/modules/content-autosave/database/sqlite.sql'));
        $preview = new ContentPreviewController();
        $_SESSION = []; self::assertSame('forbidden', $preview->read('article:1', 'r1', 'Published')['code']);
        $_SESSION = ['auth' => ['id' => 7], 'tenant_id' => 3];
        $published = $preview->read('article:1', 'r1', 'Published');
        self::assertSame('Published', $published['data']['content']);
        $drafts = new ContentDraftService();
        $saved = $drafts->save(7, 3, 'article:1', '<p>Private</p>', null, 'r1', 'r1');
        $private = $preview->read('article:1', 'r1', 'Published');
        self::assertSame('<p>Private</p>', $private['data']['content']);
        self::assertNotSame($published['data']['version'], $private['data']['version']);
        self::assertSame($private['data']['version'], $preview->state('article:1', 'r1', 'Published')['data']['version']);
        self::assertArrayNotHasKey('content', $preview->state('article:1', 'r1', 'Published')['data']);
        self::assertSame('Published', $preview->read('article:2', 'r1', 'Published')['data']['content']);
        $_SESSION['auth']['id'] = 8;
        self::assertSame('Published', $preview->read('article:1', 'r1', 'Published')['data']['content']);
        $_SESSION['auth']['id'] = 7;
        self::assertSame('New public', $preview->read('article:1', 'r2', 'New public')['data']['content']);
        self::assertTrue($drafts->load(7, 3, 'article:1')['data']['available']);
        $_POST = ['tenant_id' => 4];
        self::assertSame('forbidden', $preview->read('article:1', 'r1', 'Published')['code']);
        $_POST = []; session_destroy();
    }
}
