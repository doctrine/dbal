<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class AlterTableTest extends FunctionalTestCase
{
    public function testAddPrimaryKeyOnExistingColumn(): void
    {
        $table = new Table('alter_pk', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('val')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);

        $this->testMigration($table, static function (Table $table): void {
            $table->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            );
        });
    }

    public function testAddPrimaryKeyOnNewAutoIncrementColumn(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof DB2Platform) {
            self::markTestSkipped(
                'IBM DB2 LUW does not support adding identity columns to an existing table.',
            );
        }

        $table = new Table('alter_pk', [
            Column::editor()
                ->setUnquotedName('val')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);

        $this->testMigration($table, static function (Table $table): void {
            $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
            $table->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            );
        });
    }

    public function testAlterPrimaryKeyFromAutoincrementToNonAutoincrementColumn(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            self::markTestSkipped(
                'MySQL does not support auto-increment columns that are not part of the primary key constraint',
            );
        }

        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped(
                'SQLite does not support auto-increment columns that are not part the primary key constraint',
            );
        }

        $table = new Table('alter_pk', [
            Column::editor()
                ->setUnquotedName('id1')
                ->setTypeName(Types::INTEGER)
                ->setAutoincrement(true)
                ->create(),
            Column::editor()
                ->setUnquotedName('id2')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id1')
                ->create(),
        );

        $this->testMigration($table, static function (Table $table): void {
            $table->dropPrimaryKey();
            $table->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id2')
                    ->create(),
            );
        });
    }

    public function testDropPrimaryKeyWithAutoincrementColumn(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            self::markTestSkipped(
                'MySQL does not support auto-increment columns that are not part of the primary key constraint',
            );
        }

        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped(
                'SQLite does not support auto-increment columns as part of composite primary key constraint',
            );
        }

        $table = new Table('alter_pk', [
            Column::editor()
                ->setUnquotedName('id1')
                ->setTypeName(Types::INTEGER)
                ->setAutoincrement(true)
                ->create(),
            Column::editor()
                ->setUnquotedName('id2')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id1', 'id2')
                ->create(),
        );

        $this->testMigration($table, static function (Table $table): void {
            $table->dropPrimaryKey();
        });
    }

    public function testDropNonAutoincrementColumnFromCompositePrimaryKeyWithAutoincrementColumn(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped(
                'SQLite does not support auto-increment columns as part of composite primary key constraint',
            );
        }

        $table = new Table('alter_pk', [
            Column::editor()
                ->setUnquotedName('id1')
                ->setTypeName(Types::INTEGER)
                ->setAutoincrement(true)
                ->create(),
            Column::editor()
                ->setUnquotedName('id2')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id1', 'id2')
                ->create(),
        );

        $this->testMigration($table, static function (Table $table): void {
            $table->dropPrimaryKey();
            $table->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id1')
                    ->create(),
            );
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

        $table = new Table('alter_pk', [
            Column::editor()
                ->setUnquotedName('id1')
                ->setTypeName(Types::INTEGER)
                ->setAutoincrement(true)
                ->create(),
            Column::editor()
                ->setUnquotedName('id2')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id1')
                ->create(),
        );

        $this->testMigration($table, static function (Table $table): void {
            $table->dropPrimaryKey();
            $table->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id1', 'id2')
                    ->create(),
            );
        });
    }

    public function testAddNewColumnToPrimaryKey(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof DB2Platform) {
            self::markTestIncomplete('This test fails on IBM Db2 for an unrelated reason.');
        }

        $table = new Table('alter_pk', [
            Column::editor()
                ->setUnquotedName('id1')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id1')
                ->create(),
        );

        $this->testMigration($table, static function (Table $table): void {
            $table->addColumn('id2', Types::INTEGER);
            $table->dropPrimaryKey();
            $table->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id1', 'id2')
                    ->create(),
            );
        });
    }

    public function testReplaceForeignKeyConstraint(): void
    {
        $articles = new Table('articles', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('sku')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $articles->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );
        $articles->addUniqueConstraint(['sku']);

        $orders = new Table('orders', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('article_id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('article_sku')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
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

    private function testMigration(Table $oldTable, callable $migration): void
    {
        $this->dropAndCreateTable($oldTable);

        $schemaManager = $this->connection->createSchemaManager();

        $oldTable = $schemaManager->introspectTable($oldTable->getName());
        $newTable = clone $oldTable;

        $migration($newTable);

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
