<?php

namespace GFrame\Tests;

use PDO;
use PHPUnit\Framework\TestCase;

final class OrmBooleanBindingsTest extends TestCase
{
    public function testBooleanWritesAndFiltersOnSqlite(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE boolean_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, flag INTEGER NOT NULL CHECK (flag IN (0, 1)), payload TEXT, optional TEXT)');
        $pdo->exec('CREATE UNIQUE INDEX boolean_entries_id ON boolean_entries (id)');
        $this->exerciseBindings($pdo);
    }

    public function testBooleanBindingsOnStrictMysql(): void
    {
        $dsn = getenv('GFRAME_TEST_MYSQL_DSN');
        if (!$dsn) self::markTestSkipped('Se requiere GFRAME_TEST_MYSQL_DSN en un servidor de pruebas.');
        $pdo = new PDO($dsn, getenv('GFRAME_TEST_MYSQL_USER') ?: 'root', getenv('GFRAME_TEST_MYSQL_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
        $pdo->exec('CREATE TEMPORARY TABLE boolean_entries (id BIGINT PRIMARY KEY AUTO_INCREMENT, flag TINYINT NOT NULL, payload TEXT, optional TEXT)');
        foreach ([true, false] as $emulate) {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
            $pdo->exec('DELETE FROM boolean_entries');
            $this->exerciseBindings($pdo);
        }
    }

    private function exerciseBindings(PDO $pdo): void
    {
        BooleanBindingModel::attach($pdo);
        try {
            $model = new BooleanBindingModel(['flag' => false, 'payload' => ['enabled' => false], 'optional' => null]);
            $id = $model->insert();
            $row = $model->reset()->where('flag', '=', false)->get()[0];
            self::assertSame(0, (int)$row['flag']);
            self::assertNull($row['optional']);
            self::assertSame(['enabled' => false], json_decode($row['payload'], true));

            $model->flag = true;
            self::assertSame('updated', $model->update()['status']);
            self::assertCount(1, $model->reset()->whereIn('flag', [true])->get());
            $model->reset()->where('flag', '=', true)->update(['flag' => false]);
            self::assertCount(1, $model->reset()->where('flag', '=', false)->get());

            $model->reset()->upsert(['id' => $id], ['flag' => true]);
            self::assertCount(1, $model->reset()->where('flag', '=', true)->get());
            $model->bulkInsert([['flag' => false, 'payload' => '', 'optional' => null], ['flag' => true, 'payload' => '1', 'optional' => '']]);
            self::assertCount(3, $model->reset()->whereIn('flag', [false, true])->get());

            \ORM::raw('UPDATE boolean_entries SET flag = :flag WHERE id = :id', ['flag' => false, 'id' => $id], 'none', 'boolean_bindings');
            self::assertSame(2, (int)\ORM::raw('SELECT COUNT(*) FROM boolean_entries WHERE flag = ?', [false], 'column', 'boolean_bindings'));
            self::assertSame('', \ORM::raw('SELECT payload FROM boolean_entries WHERE payload = ?', [''], 'column', 'boolean_bindings'));
        } finally {
            \ORM::disconnect('boolean_bindings');
        }
    }
}

final class BooleanBindingModel extends \ORM
{
    protected $table = 'boolean_entries';
    protected $connection = 'boolean_bindings';
    protected $casts = ['payload' => 'json'];

    public static function attach(PDO $pdo): void
    {
        self::$pdo['boolean_bindings'] = $pdo;
    }

    protected function dialect(): \DatabaseDialectInterface
    {
        return $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? new \MySqlDialect()
            : new \SqliteDialect();
    }
}
