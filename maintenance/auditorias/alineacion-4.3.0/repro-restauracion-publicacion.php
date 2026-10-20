<?php
// Reproductor de auditoría: SQLite en memoria, sin aplicaciones ni datos persistentes.
require dirname(__DIR__, 3) . '/packages/autoload.php';

use GFrame\Content\RevisionedContentModel;
use GFrame\Content\ScheduledContent;

define('DB_DEFAULT_CONNECTION', 'audit_publication');
define('DB_CONNECTIONS', ['audit_publication' => ['driver' => 'sqlite', 'path' => ':memory:']]);

final class AuditPublicationArticle extends RevisionedContentModel
{
    use ScheduledContent;
    protected $table = 'audit_articles';
    protected $fillable = ['content', 'revision', 'status', 'publish_at'];
    protected function historyFields(): array { return ['content', 'status', 'publish_at']; }
}

$pdo = DatabaseManager::connection('audit_publication');
$pdo->exec('CREATE TABLE audit_articles (id INTEGER PRIMARY KEY, content TEXT, revision VARCHAR(128), status VARCHAR(32), publish_at DATETIME NULL)');
$pdo->exec("INSERT INTO audit_articles VALUES (1, 'Publicado', 'r1', 'published', NULL)");
$pdo->exec(file_get_contents(dirname(__DIR__, 3) . '/resources/modules/content-revisions/database/sqlite.sql'));
$model = new AuditPublicationArticle();
$scheduled = $model->schedulePublication(1, 'r1', '2099-01-01 00:00:00', 1);
if ($scheduled['status'] !== 'success') throw new RuntimeException('No se pudo preparar el contenido.');
$entryID = (int)$pdo->query("SELECT id FROM content_revisions WHERE revision = 'r1'")->fetchColumn();
if ($entryID < 1) throw new RuntimeException('No se capturó el estado anterior.');
$before = $pdo->query('SELECT status FROM audit_articles WHERE id=1')->fetchColumn();
$restored = $model->restoreRevision(1, $entryID, $scheduled['data']['revision'], 1);
$after = $pdo->query('SELECT status FROM audit_articles WHERE id=1')->fetchColumn();
echo json_encode(['before' => $before, 'restore_code' => $restored['code'], 'after' => $after], JSON_PRETTY_PRINT), PHP_EOL;
if ($before !== 'scheduled' || $after !== 'published') throw new RuntimeException('El comportamiento observado cambió: revisar el hallazgo.');
