<?php

namespace GFrame\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OrmTransactionTest extends TestCase
{
    private function model(): TxModel
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE tx_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        TxModel::attach($pdo);
        return new TxModel();
    }

    public function testTransactionCommitsAndReturnsCallbackValue(): void
    {
        $model = $this->model();
        try {
            $value = TxModel::transaction(function () {
                (new TxModel(['name' => 'alfa']))->insert();
                return 'ok';
            });
            self::assertSame('ok', $value);
            self::assertCount(1, $model->reset()->get());
        } finally {
            \ORM::disconnect('orm_tx');
        }
    }

    public function testTransactionRollsBackAndRethrowsOnException(): void
    {
        $model = $this->model();
        try {
            try {
                TxModel::transaction(function () {
                    (new TxModel(['name' => 'alfa']))->insert();
                    throw new RuntimeException('fallo de negocio');
                });
                self::fail('Debió propagar la excepción.');
            } catch (RuntimeException $exception) {
                self::assertSame('fallo de negocio', $exception->getMessage());
            }
            self::assertCount(0, $model->reset()->get());
            self::assertFalse(TxModel::connectionForTest()->inTransaction());
        } finally {
            \ORM::disconnect('orm_tx');
        }
    }

    public function testNestedTransactionJoinsOuterAndCommitsTogether(): void
    {
        $model = $this->model();
        try {
            TxModel::transaction(function () {
                (new TxModel(['name' => 'externa']))->insert();
                TxModel::transaction(function () {
                    (new TxModel(['name' => 'interna']))->insert();
                });
            });
            self::assertCount(2, $model->reset()->get());
        } finally {
            \ORM::disconnect('orm_tx');
        }
    }

    public function testNestedFailureRollsBackEverything(): void
    {
        $model = $this->model();
        try {
            try {
                TxModel::transaction(function () {
                    (new TxModel(['name' => 'externa']))->insert();
                    TxModel::transaction(function () {
                        (new TxModel(['name' => 'interna']))->insert();
                        throw new RuntimeException('fallo interno');
                    });
                });
            } catch (RuntimeException) {
            }
            self::assertCount(0, $model->reset()->get());
        } finally {
            \ORM::disconnect('orm_tx');
        }
    }

    public function testReconnectRefusesDuringActiveTransaction(): void
    {
        $this->model();
        try {
            TxModel::connectionForTest()->beginTransaction();
            $this->expectException(\Exception::class);
            TxModel::reconnect('orm_tx');
        } finally {
            $pdo = TxModel::connectionForTest();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            \ORM::disconnect('orm_tx');
        }
    }
}

final class TxModel extends \ORM
{
    protected $table = 'tx_entries';
    protected $connection = 'orm_tx';

    public static function attach(PDO $pdo): void
    {
        self::$pdo['orm_tx'] = $pdo;
    }

    public static function connectionForTest(): PDO
    {
        return self::$pdo['orm_tx'];
    }

    protected function dialect(): \DatabaseDialectInterface
    {
        return new \SqliteDialect();
    }
}
