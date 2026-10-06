<?php

namespace GFrame\Tests;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class OrmDocumentationTest extends TestCase
{
    private function examples(): array
    {
        preg_match_all('/```php\s*\n(.*?)\n```/s', file_get_contents(dirname(__DIR__) . '/docs/orm.md'), $blocks);
        return $blocks[1];
    }

    public function testExamplesHaveValidSyntax(): void
    {
        self::assertGreaterThanOrEqual(11, count($this->examples()));
        foreach ($this->examples() as $code) {
            if (!str_starts_with(trim($code), '<?php')) $code = '<?php ' . $code;
            self::assertNotEmpty(token_get_all($code, TOKEN_PARSE));
        }
    }

    public function testExamplesRunOnSqliteWithTheDeclaredSchema(): void
    {
        define('DB_DEFAULT_CONNECTION', 'catalog');
        define('DB_CONNECTIONS', ['catalog' => ['driver' => 'sqlite', 'path' => ':memory:']]);
        \ORM::disconnect();
        $pdo = \DatabaseManager::connection('catalog');
        $pdo->exec('CREATE TABLE products (product_id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, is_active INTEGER)');
        $pdo->exec('CREATE TABLE categories (category_id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO products VALUES (25, 'Mesa de prueba', 1)");
        try {
            foreach ($this->examples() as $code) {
                eval(preg_replace('/^\s*<\?php\s*/', '', $code));
            }
            self::assertSame('Mesa de prueba', \ProductModel::find(25)->name);
            self::assertCount(3, (new \ProductModel())->get());
            self::assertSame('Mesa grande', \ProductModel::find(26)->name);
            self::assertSame([25], $processedIds);
            self::assertSame(1, $listing['meta']['page']);
            self::assertSame(1, $listing['meta']['total_items']);
            self::assertSame(['is_active' => 1, 'total' => 1], $summary[0]);
            self::assertFalse($pdo->inTransaction());
            \ProductModel::beginTransaction('catalog');
            (new \ProductModel(['name' => 'No persistir', 'is_active' => 1]))->insert();
            \ProductModel::rollBack('catalog');
            self::assertCount(3, (new \ProductModel())->get());
        } finally {
            \ORM::disconnect();
        }
    }
}
