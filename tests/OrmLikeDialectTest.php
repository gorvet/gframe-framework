<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class OrmLikeDialectTest extends TestCase
{
    public function testDialectsCompileTheirOwnEscapeLiteral(): void
    {
        self::assertSame("email LIKE ? ESCAPE '\\\\'", (new \MySqlDialect())->likeExpression('email'));
        self::assertSame("email LIKE ? ESCAPE '\\'", (new \SqliteDialect())->likeExpression('email'));
    }

    #[RunInSeparateProcess]
    public function testSqliteSearchPreservesLiteralWildcardsAndEmptyResults(): void
    {
        define('DB_DEFAULT_CONNECTION', 'like_test');
        define('DB_CONNECTIONS', ['like_test' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        \ORM::disconnect();
        $pdo = \DatabaseManager::connection();
        $pdo->exec('CREATE TABLE entries (id INTEGER PRIMARY KEY, email TEXT, title TEXT)');
        $insert = $pdo->prepare('INSERT INTO entries (email, title) VALUES (?, ?)');
        foreach (['normal', 'literal%value', 'literal_value', 'path\\value'] as $value) {
            $insert->execute([$value, $value]);
        }
        $model = new class extends \ORM { protected $table = 'entries'; };
        foreach (['normal', '%', '_', '\\'] as $term) {
            self::assertCount(1, $model->reset()->whereLike('email', $term)->get());
            self::assertCount(1, $model->reset()->whereAnyLike(['email', 'title'], $term)->get());
        }
        self::assertCount(0, $model->reset()->whereLike('email', 'not-found')->get());
        self::assertCount(4, $model->reset()->whereLike('email', '')->get());
        self::assertCount(1, $model->reset()->whereLikePattern('email', 'literal\\%value')->get());
        self::assertCount(2, $model->reset()->whereLike('email', 'normal')->orWhereLikePattern('title', 'path\\\\value')->get());
        \ORM::disconnect();
    }
}
