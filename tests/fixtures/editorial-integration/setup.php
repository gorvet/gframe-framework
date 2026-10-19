<?php
// Generates an isolated application. No existing project is used or modified.
require dirname(__DIR__, 3) . '/packages/autoload.php';
$root = $argv[1];$url = $argv[2];
if (!is_dir($root) || (scandir($root) !== ['.', '..'])) throw new RuntimeException('El destino de pruebas debe estar vacío.');
$result = GFrame\Install\ProjectInstaller::frameworkDefault()->install([
    'project_root' => $root, 'profile' => 'managed', 'app_name' => 'Prueba editorial', 'app_url' => $url,
    'database' => ['driver' => 'sqlite', 'path' => $root . '/storage/editorial.sqlite'],
    'modules' => ['rich-text-editor', 'content-autosave', 'content-editing', 'content-revisions', 'content-preview', 'cron-runner'],
    'superadministrator' => ['email' => 'integration@example.com', 'password' => 'Fixture-Only-123!'],
]);
if ($result['status'] !== 'success') throw new RuntimeException(json_encode($result));
mkdir($root . '/packages');
file_put_contents($root . '/packages/autoload.php', '<?php return require ' . var_export(dirname(__DIR__, 3) . '/packages/autoload.php', true) . ';');
// Copy application fixture sources intact, alongside the actual installed framework files.
$source = __DIR__ . '/application';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
    $to = $root . '/' . substr($file->getPathname(), strlen($source) + 1);
    if (!is_dir(dirname($to))) mkdir(dirname($to), 0775, true);
    copy($file->getPathname(), $to);
}
$pdo = new PDO('sqlite:' . $root . '/storage/editorial.sqlite');
$pdo->exec("CREATE TABLE articles(id INTEGER PRIMARY KEY,owner_id INTEGER,content TEXT,revision TEXT,status TEXT,publish_at TEXT,deleted_at TEXT);
INSERT INTO articles VALUES(1,1,'<p>Artículo inicial</p>','r1','published',NULL,NULL),(2,999,'<p>Ajeno</p>','r2','draft',NULL,NULL)");
echo json_encode(['status' => 'success', 'root' => $root]);
