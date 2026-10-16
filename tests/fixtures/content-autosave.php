<?php
// Aplicación temporal de pruebas; nunca se publica ni usa credenciales reales.
$root = getenv('GFRAME_AUTOSAVE_TEST_ROOT');
if (!$root || !is_dir($root)) { http_response_code(503); exit; }
$framework = dirname(__DIR__, 2);
require $framework . '/packages/autoload.php';
define('DB_DEFAULT_CONNECTION', 'autosave_fixture');
define('DB_CONNECTIONS', ['autosave_fixture' => ['driver' => 'sqlite', 'path' => $root . '/content.sqlite']]);
$pdo = DatabaseManager::connection();
$pdo->exec('PRAGMA busy_timeout=5000');
$pdo->exec(file_get_contents($framework . '/resources/modules/content-autosave/database/sqlite.sql'));
$pdo->exec("CREATE TABLE IF NOT EXISTS articles (id INTEGER PRIMARY KEY, content TEXT, revision TEXT); INSERT OR IGNORE INTO articles VALUES (42, '<p>Publicado</p>', 'r1')");
$_SESSION = ['auth' => ['id' => 7]];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$article = $pdo->query('SELECT * FROM articles WHERE id=42')->fetch(PDO::FETCH_ASSOC);
$drafts = new \GFrame\Content\ContentDraftService();
if ($path === '/inspect') { echo json_encode(['article' => $article, 'draft' => $drafts->load(7, null, 'article:42:content')]); return; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['csrfToken'] ?? '') !== 'fixture-token') { echo json_encode(['status'=>'error','code'=>'fail_csrf']); return; }
    // El registro se resuelve y autoriza en la aplicación, antes del controlador común.
    if ($path === '/autosave') echo json_encode((new \GFrame\Content\ContentDraftController())->handle('article:42:content', $article['revision']));
    elseif ($path === '/publish') {
        $content = \GFrame\Security\HtmlSanitizer::sanitize((string)$_POST['content']);
        $revision = bin2hex(random_bytes(8));
        $pdo->prepare('UPDATE articles SET content=?, revision=? WHERE id=42')->execute([$content, $revision]);
        echo json_encode(['status' => 'success', 'data' => ['revision' => $revision]]);
    }
    return;
}
$richTextEditor = ['id'=>'article-content', 'name'=>'content', 'value'=>$article['content'], 'draft_key'=>'user:7:article:42',
    'autosave'=>['url'=>'/autosave', 'base_version'=>$article['revision'], 'interval'=>5000]];
?><!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/bootstrap.css"><link rel="stylesheet" href="/sweetalert.css"><link rel="stylesheet" href="/sweet-theme.css"><link rel="stylesheet" href="/public/vendors/external/tinymce/gframe-tinymce.css">
<body><main class="container-fluid p-3"><h1 class="h4">Artículo</h1>
<form id="tokens"><input name="csrfToken" value="fixture-token" type="hidden"></form>
<form id="article-form"><?php include $framework . '/resources/modules/rich-text-editor/application/app/views/rich-text-editor/richTextEditor.php'; ?>
<button type="button" class="btn btn-primary" id="publish">Publicar</button></form></main>
<script>var site_url='http://content-autosave.test/';</script><script src="/jquery.js"></script><script src="/sweetalert.js"></script><script src="/alerts.js"></script><script src="/public/vendors/external/tinymce/tinymce.min.js"></script><script src="/editor.js"></script>
<script>
document.getElementById('publish').addEventListener('click', function() {
    const snapshot = AdminRichTextEditor.getSaveSnapshot('article-content');
    $.post('/publish', {content:snapshot.content, csrfToken:'fixture-token'}, null, 'json').done(function(response) {
        if (response.status === 'success') { AdminRichTextEditor.markSaved(snapshot, response.data.revision); window.published=true; }
    });
});
</script></body></html>
