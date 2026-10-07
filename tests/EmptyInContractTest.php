<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class EmptyInContractTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testEmptySelectionsRespectBooleanGroupsAndCannotWriteMatchingRows(): void
    {
        define('DB_DEFAULT_CONNECTION', 'empty_in_test');
        define('DB_CONNECTIONS', ['empty_in_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        $pdo = \DatabaseManager::connection();
        $pdo->exec('CREATE TABLE entries (id INTEGER PRIMARY KEY, tenant_id INTEGER, value TEXT)');
        $pdo->exec("INSERT INTO entries VALUES (1, 7, 'first'), (2, 8, 'second')");
        $model = new class extends \ORM { protected $table = 'entries'; };
        self::assertSame([], $model->reset()->whereIn('id', [])->get());
        self::assertSame([], $model->reset()->orWhereIn('id', [])->get());
        self::assertSame([], $model->reset()->where('tenant_id', '=', 7)->whereIn('id', [])->get());
        self::assertCount(1, $model->reset()->where('id', '=', 1)->orWhereIn('id', [])->get());
        self::assertCount(1, $model->reset()->whereIn('id', [])->orWhere('id', '=', 2)->get());
        self::assertSame([], $model->reset()->where('tenant_id', '=', 7)->whereGroup(fn($q) => $q->whereIn('id', [])->orWhereIn('id', [2]))->get());
        self::assertCount(1, $model->reset()->whereIn('id', [2])->get());
        self::assertSame(0, $model->reset()->where('tenant_id', '=', 7)->whereIn('id', [])->update(['value' => 'changed'])['affected']);
        self::assertSame(0, $model->reset()->whereIn('id', [])->deleteWhere()['affected']);
        self::assertSame(['first', 'second'], $pdo->query('SELECT value FROM entries ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN));
        self::assertCount(2, $model->reset()->when(false, fn($q) => $q->whereIn('id', []))->get());
    }
}
