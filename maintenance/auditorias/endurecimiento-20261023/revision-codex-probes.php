<?php

// Revisión local: SQLite en memoria, datos ficticios y lectura de HEAD.
require dirname(__DIR__, 3) . '/packages/autoload.php';

$process = proc_open(['git', 'show', 'HEAD:src/database/ORM.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
fclose($pipes[0]);
$baseline = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
if (proc_close($process) !== 0) throw new RuntimeException($error);
eval(substr(str_replace('abstract class ORM {', 'abstract class ClaudeReviewBaselineORM {', $baseline), 5));

class ClaudeReviewOldQuery extends ClaudeReviewBaselineORM { protected $table = 'items'; }
class ClaudeReviewCurrentQuery extends ORM { protected $table = 'items'; }
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE items (id INTEGER, role_id INTEGER); CREATE TABLE roles (id INTEGER, secret TEXT); INSERT INTO items VALUES(1,1),(2,1); INSERT INTO roles VALUES(1,"allowed"),(2,"other")');
$queries = [
    'aggregate_having' => static fn($q) => $q->select('role_id', 'COUNT(*) AS total')->groupBy('role_id')->having('COUNT(*)', '>', 1),
    'aliased_join' => static fn($q) => $q->select('items.id')->join('roles AS r', 'r.id', '=', 'items.role_id'),
    'having_alias_alternative' => static fn($q) => $q->select('role_id', 'COUNT(*) AS total')->groupBy('role_id')->having('total', '>', 1),
    'join_type_not_validated' => static fn($q) => $q->select('roles.secret')->join('roles', 'roles.id', '=', 'items.role_id', 'JOIN roles ON 1=1 --'),
    'identifier_attack' => static fn($q) => $q->orderBy('id; DROP TABLE items'),
];
$results = [];
foreach ($queries as $name => $build) {
    foreach (['head' => ClaudeReviewOldQuery::class, 'current' => ClaudeReviewCurrentQuery::class] as $version => $class) {
        try {
            $query = $build((new $class())->useStrictComparison(false));
            $results[$name][$version] = ['sql' => $query->toSql(), 'bindings' => $query->getBindings()];
            // No se ejecuta el ejemplo de inyección; solo se inspecciona su SQL.
            if ($name !== 'identifier_attack') {
                $statement = $pdo->prepare($query->toSql());
                foreach ($query->getBindings() as $index => $value) $statement->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
                $statement->execute();
                $results[$name][$version]['rows'] = $statement->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $exception) {
            $results[$name][$version] = ['rejected' => $exception->getMessage()];
        }
    }
}

$redact = new ReflectionMethod(LogHelper::class, 'redact');
foreach (['array' => ['token' => 'fictional-marker'], 'object' => (object)['token' => 'fictional-marker']] as $kind => $context) {
    $encoded = json_encode($redact->invoke(null, ['nested' => $context]));
    $results['log_redaction'][$kind . '_secret_visible'] = str_contains($encoded, 'fictional-marker');
}

use GFrame\Security\CsrfToken;
use GFrame\Config\ConfigRepository;
foreach (['guest_expired' => [null, 10, time() - 100], 'guest_disabled' => [null, 0, time() - 100], 'authenticated_expired' => [['id' => 7], 10, time() - 100], 'guest_valid' => [null, 100, time() - 1]] as $name => [$identity, $maxAge, $issued]) {
    ConfigRepository::replace(['security' => ['csrf_guest_max_age' => $maxAge]]);
    $_SESSION = ['csrfToken' => 'fictional-token', 'csrfTimestamp' => $issued];
    if ($identity !== null) $_SESSION['auth'] = $identity;
    $results['csrf'][$name] = CsrfToken::validate('fictional-token', $issued);
}
define('site_url', 'https://app.test/');
$pick = new ReflectionMethod(Router::class, 'pick');
foreach (['/\\evil.test', '//evil.test', '%2F%5Cevil.test', 'admin/items', "/admin\r\nLocation: https://evil.test"] as $destination) {
    $_GET = ['rd' => $destination]; $_SESSION = [];
    $results['redirect'][$destination] = $pick->invoke(null, '/admin');
}
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
