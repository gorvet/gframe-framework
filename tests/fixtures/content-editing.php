<?php

require dirname(__DIR__, 2) . '/packages/autoload.php';

$root = getenv('GFRAME_CONTENT_TEST_ROOT');
if (!$root || !is_dir($root)) { http_response_code(503); exit; }
define('DB_DEFAULT_CONNECTION', 'content_fixture');
define('DB_CONNECTIONS', ['content_fixture' => ['driver' => 'sqlite', 'path' => $root . '/content.sqlite']]);
define('site_url', 'http://content-editing.test/');
$database = DatabaseManager::connection();
$database->exec('PRAGMA busy_timeout=5000');
$database->exec('CREATE TABLE IF NOT EXISTS articles (id INTEGER PRIMARY KEY, tenant_id INTEGER, content TEXT, revision TEXT)');
$database->exec("INSERT OR IGNORE INTO articles VALUES (1,7,'Original','r1')");
$database->exec(file_get_contents(dirname(__DIR__, 2) . '/resources/modules/content-editing/database/sqlite.sql'));
$_SESSION = ['auth' => ['id' => 7], 'tenant_id' => 7, 'csrfToken' => 'fixture-token', 'csrfTimestamp' => 100];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validation = (new Middleware())->handle(['middleware' => ['CSRF']]);
    if ($validation['status'] !== 'success') { echo json_encode($validation); return; }
    if ($_SERVER['REQUEST_URI'] === '/presence') {
        echo json_encode((new GFrame\Content\ContentPresenceController())->handle('article:1'));
    } elseif ($_SERVER['REQUEST_URI'] === '/save') {
        $model = new class extends GFrame\Content\VersionedContentModel {
            protected $table = 'articles';
            protected $fillable = ['content', 'revision'];
        };
        echo json_encode($model->saveRevision(1, (string)$_POST['content_revision'], ['content' => SanitizeHelper::sanitize((string)$_POST['content'])], ['tenant_id' => 7]));
    }
    return;
}
$article = $database->query('SELECT * FROM articles WHERE id=1')->fetch(PDO::FETCH_ASSOC);
$contentEditing = ['url' => 'http://content-editing.test/presence', 'revision' => $article['revision']];
?>
<!doctype html><html lang="es"><meta charset="utf-8"><title>Edición</title>
<form id="tokens"><input name="csrfToken" value="fixture-token"><input name="csrfTimestamp" value="100"></form>
<form id="article"><label for="content">Contenido</label><textarea id="content" name="content"><?= htmlspecialchars($article['content'], ENT_QUOTES, 'UTF-8') ?></textarea>
<?php include dirname(__DIR__, 2) . '/resources/modules/content-editing/application/app/views/content-editing/contentEditing.php'; ?>
</form></html>
