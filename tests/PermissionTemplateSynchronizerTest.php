<?php

namespace GFrame\Tests;

use GFrame\Install\PermissionTemplateSynchronizer;
use PDO;
use PHPUnit\Framework\TestCase;

final class PermissionTemplateSynchronizerTest extends TestCase
{
    private string $project;
    private PDO $database;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gframe-permissions-' . bin2hex(random_bytes(6));
        mkdir($this->project . DIRECTORY_SEPARATOR . 'config', 0775, true);
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec("CREATE TABLE roles (role_id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, is_system INTEGER NOT NULL DEFAULT 0, security_version INTEGER NOT NULL DEFAULT 1, permissions_json TEXT NOT NULL DEFAULT '{}')");
    }

    protected function tearDown(): void
    {
        $path = $this->project . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'Permissions.php';
        if (is_file($path)) unlink($path);
        if (is_dir(dirname($path))) rmdir(dirname($path));
        if (is_dir($this->project)) rmdir($this->project);
    }

    public function testSynchronizesNewAndChangedRoleTemplates(): void
    {
        $this->template("<?php return ['editor' => ['content' => ['view' => true, 'delete' => false]]];");
        $sync = new PermissionTemplateSynchronizer();
        $first = $sync->sync($this->database, $this->project);

        self::assertSame(1, $first['roles']);
        self::assertSame(1, $first['granted']);
        self::assertSame(['content.view'], $this->grants('editor'));

        $this->template("<?php return ['editor' => ['content' => ['view' => false, 'delete' => true, 'publish' => true]]];");
        $second = $sync->sync($this->database, $this->project);

        self::assertSame(2, $second['granted']);
        self::assertSame(['content.delete', 'content.publish'], $this->grants('editor'));
        self::assertSame(2, (int)$this->database->query("SELECT security_version FROM roles WHERE slug = 'editor'")->fetchColumn());
    }

    private function template(string $php): void
    {
        file_put_contents($this->project . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'Permissions.php', $php);
        clearstatcache();
    }

    private function grants(string $role): array
    {
        $statement = $this->database->prepare('SELECT permissions_json FROM roles WHERE slug = ?');
        $statement->execute([$role]);
        $map = json_decode((string)$statement->fetchColumn(), true);
        $grants = array_keys(array_filter(is_array($map) ? $map : []));
        sort($grants);
        return $grants;
    }
}
