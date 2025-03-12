<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class AlterTableTest extends FunctionalTestCase
{
    public function testAddPrimaryKeyOnExistingColumn(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::markTestSkipped(
                'SQLite will automatically set up auto-increment behavior on the primary key column, which this test'
                    . ' does not expect.',
            );
        }

        $table = new Table('alter_pk');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('val', Types::INTEGER);

        $this->testMigration($table, static function (Table $table): void {
            $table->setPrimaryKey(['id']);
        });
    }

    public function testAddPrimaryKeyOnNewAutoIncrementColumn(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof DB2Platform) {
            self::markTestSkipped(
                'IBM DB2 LUW does not support adding identity columns to an existing table.',
            );
        }

        $table = new Table('alter_pk');
        $table->addColumn('val', Types::INTEGER);

        $this->testMigration($table, static function (Table $table): void {
            $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
            $table->setPrimaryKey(['id']);
        });
    }

    public function testAlterPrimaryKeyFromAutoincrementToNonAutoincrementColumn(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            self::markTestIncomplete(
                'DBAL should not allow this migration on MySQL because an auto-increment column must be part of the'
                    . ' primary key constraint.',
            );
        }

        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped(
                'SQLite does not support auto-increment columns that are not part the primary key constraint',
            );
        }

        $this->ensureDroppingPrimaryKeyConstraintIsSupported();

        $table = new Table('alter_pk');
        $table->addColumn('id1', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('id2', Types::INTEGER);
        $table->setPrimaryKey(['id1']);

        $this->testMigration($table, static function (Table $table): void {
            $table->dropPrimaryKey();
            $table->setPrimaryKey(['id2']);
        });
    }

    public function testDropPrimaryKeyWithAutoincrementColumn(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            self::markTestIncomplete(
                'DBAL should not allow this migration on MySQL because an auto-increment column must be part of the'
                    . ' primary key constraint.',
            );
        }

        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped(
                'SQLite does not support auto-increment columns as part of composite primary key constraint',
            );
        }

        $this->ensureDroppingPrimaryKeyConstraintIsSupported();

        $table = new Table('alter_pk');
        $table->addColumn('id1', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('id2', Types::INTEGER);
        $table->setPrimaryKey(['id1', 'id2']);

        $this->testMigration($table, static function (Table $table): void {
            $table->dropPrimaryKey();
        });
    }

    public function testDropNonAutoincrementColumnFromCompositePrimaryKeyWithAutoincrementColumn(): void
    {
        $this->ensureDroppingPrimaryKeyConstraintIsSupported();

        $table = new Table('alter_pk');
        $table->addColumn('id1', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('id2', Types::INTEGER);
        $table->setPrimaryKey(['id1', 'id2']);

        $this->testMigration($table, static function (Table $table): void {
            $table->dropPrimaryKey();
            $table->setPrimaryKey(['id1']);
        });
    }

    public function testAddNonAutoincrementColumnToPrimaryKeyWithAutoincrementColumn(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped(
                'SQLite does not support auto-increment columns as part of composite primary key constraint',
            );
        }

        $this->ensureDroppingPrimaryKeyConstraintIsSupported();

        $table = new Table('alter_pk');
        $table->addColumn('id1', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('id2', Types::INTEGER);
        $table->setPrimaryKey(['id1']);

        $this->testMigration($table, static function (Table $table): void {
            $table->dropPrimaryKey();
            $table->setPrimaryKey(['id1', 'id2']);
        });
    }

    public function testAddNewColumnToPrimaryKey(): void
    {
        $this->ensureDroppingPrimaryKeyConstraintIsSupported();

        $table = new Table('alter_pk');
        $table->addColumn('id1', Types::INTEGER);
        $table->setPrimaryKey(['id1']);

        $this->testMigration($table, static function (Table $table): void {
            $table->addColumn('id2', Types::INTEGER);
            $table->dropPrimaryKey();
            $table->setPrimaryKey(['id1', 'id2']);
        });
    }

    public function testReplaceForeignKeyConstraint(): void
    {
        $articles = new Table('articles');
        $articles->addColumn('id', Types::INTEGER);
        $articles->addColumn('sku', Types::INTEGER);
        $articles->setPrimaryKey(['id']);
        $articles->addUniqueConstraint(['sku']);

        $orders = new Table('orders');
        $orders->addColumn('id', Types::INTEGER);
        $orders->addColumn('article_id', Types::INTEGER);
        $orders->addColumn('article_sku', Types::INTEGER);
        $orders->addForeignKeyConstraint(
            'articles',
            ['article_id'],
            ['id'],
            [],
            'articles_fk',
        );

        $this->dropTableIfExists('orders');
        $this->dropTableIfExists('articles');

        $this->connection->createSchemaManager()
            ->createTable($articles);

        $this->testMigration($orders, static function (Table $table): void {
            $table->dropForeignKey('articles_fk');
            $table->addForeignKeyConstraint(
                'articles',
                ['article_sku'],
                ['sku'],
                [],
                'articles_fk',
            );
        });
    }

    private function ensureDroppingPrimaryKeyConstraintIsSupported(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (
            ! ($platform instanceof DB2Platform)
            && ! ($platform instanceof OraclePlatform)
            && ! ($platform instanceof SQLServerPlatform)
        ) {
            return;
        }

        self::markTestIncomplete(
            'Dropping primary key constraint on the currently used database platform is not implemented.',
        );
    }

    private function testMigration(Table $oldTable, callable $migration): void
    {
        $this->dropAndCreateTable($oldTable);

        $newTable = clone $oldTable;

        $migration($newTable);

        $schemaManager = $this->connection->createSchemaManager();

        $diff = $schemaManager->createComparator()
            ->compareTables($oldTable, $newTable);

        self::assertFalse($diff->isEmpty());

        $schemaManager->alterTable($diff);

        $introspectedTable = $schemaManager->introspectTable($newTable->getName());

        $diff = $schemaManager->createComparator()
            ->compareTables($newTable, $introspectedTable);

        self::assertTrue($diff->isEmpty());
    }
}
