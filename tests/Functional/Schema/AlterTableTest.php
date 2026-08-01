<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class AlterTableTest extends FunctionalTestCase
{
    public function testAddPrimaryKeyOnExistingColumn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('alter_pk')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('val')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->testMigration($table, static function (TableEditor $editor): void {
            $editor->addPrimaryKeyConstraint(
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

        $table = Table::editor()
            ->setUnquotedName('alter_pk')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('val')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->testMigration($table, static function (TableEditor $editor): void {
            $editor
                ->addColumn(
                    Column::editor()
                        ->setUnquotedName('id')
                        ->setTypeName(Types::INTEGER)
                        ->setAutoincrement(true)
                        ->create(),
                )
                ->setPrimaryKeyConstraint(
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

        $table = Table::editor()
            ->setUnquotedName('alter_pk')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id1')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('id2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id1')
                    ->create(),
            )
            ->create();

        $this->testMigration($table, static function (TableEditor $editor): void {
            $editor
                ->dropPrimaryKeyConstraint()
                ->addPrimaryKeyConstraint(
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

        $table = Table::editor()
            ->setUnquotedName('alter_pk')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id1')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('id2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id1', 'id2')
                    ->create(),
            )
            ->create();

        $this->testMigration($table, static function (TableEditor $editor): void {
            $editor->dropPrimaryKeyConstraint();
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

        $table = Table::editor()
            ->setUnquotedName('alter_pk')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id1')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('id2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id1', 'id2')
                    ->create(),
            )
            ->create();

        $this->testMigration($table, static function (TableEditor $editor): void {
            $editor
                ->dropPrimaryKeyConstraint()
                ->addPrimaryKeyConstraint(
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

        $table = Table::editor()
            ->setUnquotedName('alter_pk')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id1')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('id2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id1')
                    ->create(),
            )
            ->create();

        $this->testMigration($table, static function (TableEditor $editor): void {
            $editor
                ->dropPrimaryKeyConstraint()
                ->addPrimaryKeyConstraint(
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

        $table = Table::editor()
            ->setUnquotedName('alter_pk')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id1')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id1')
                    ->create(),
            )
            ->create();

        $this->testMigration($table, static function (TableEditor $editor): void {
            $editor
                ->addColumn(
                    Column::editor()
                        ->setUnquotedName('id2')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                )
                ->dropPrimaryKeyConstraint()
                ->addPrimaryKeyConstraint(
                    PrimaryKeyConstraint::editor()
                        ->setUnquotedColumnNames('id1', 'id2')
                        ->create(),
                );
        });
    }

    /**
     * A single alter that both drops a column and adds an index. Db2 leaves the table pending
     * reorganization after the drop and rejects the new index until the reorganization runs, so the
     * two must be ordered correctly.
     */
    public function testDropColumnAndAddIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('reorg_alter')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('legacy')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('lookup')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->testMigration($table, static function (TableEditor $editor): void {
            $editor
                ->dropColumnByUnquotedName('legacy')
                ->addIndex(
                    Index::editor()
                        ->setUnquotedName('reorg_alter_lookup_idx')
                        ->setUnquotedColumnNames('lookup')
                        ->create(),
                );
        });
    }

    public function testReplaceForeignKeyConstraint(): void
    {
        $prototype = ForeignKeyConstraint::editor()
            ->setUnquotedName('articles_fk')
            ->setUnquotedReferencedTableName('articles');

        $old = $prototype
            ->setUnquotedReferencingColumnNames('article_id')
            ->setUnquotedReferencedColumnNames('id')
            ->create();

        $new = $prototype
            ->setUnquotedReferencingColumnNames('article_sku')
            ->setUnquotedReferencedColumnNames('sku')
            ->create();

        $this->assertForeignKeyConstraintModification($old, static function (TableEditor $editor) use ($new): void {
            $editor
                ->dropForeignKeyConstraintByUnquotedName('articles_fk')
                ->addForeignKeyConstraint($new);
        });
    }

    public function testRenameForeignKeyConstraint(): void
    {
        $prototype = ForeignKeyConstraint::editor()
            ->setUnquotedReferencingColumnNames('article_id')
            ->setUnquotedReferencedTableName('articles')
            ->setUnquotedReferencedColumnNames('id');

        $old = $prototype
            ->setUnquotedName('articles_fk')
            ->create();

        $new = $prototype
            ->setUnquotedName('new_fk')
            ->create();

        $this->assertForeignKeyConstraintModification($old, static function (TableEditor $editor) use ($new): void {
            $editor
                ->dropForeignKeyConstraintByUnquotedName('articles_fk')
                ->addForeignKeyConstraint($new);
        });
    }

    /**
     * Creates a table with the given foreign key, applies the modification and asserts the altered
     * table introspects back to the desired state.
     *
     * @param callable(TableEditor): void $modify
     */
    private function assertForeignKeyConstraintModification(ForeignKeyConstraint $old, callable $modify): void
    {
        $articles = Table::editor()
            ->setUnquotedName('articles')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('sku')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedColumnNames('sku')
                    ->create(),
            )
            ->create();

        $orders = Table::editor()
            ->setUnquotedName('orders')
            ->setColumns(
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
            )
            ->setForeignKeyConstraints($old)
            ->create();

        $this->dropTableIfExists('orders');
        $this->dropTableIfExists('articles');

        $this->connection->createSchemaManager()
            ->createTable($articles);

        $this->testMigration($orders, $modify);
    }

    public function testDropColumnCoveredByForeignKey(): void
    {
        $referenced = Table::editor()
            ->setUnquotedName('alter_referenced')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $referencing = Table::editor()
            ->setUnquotedName('alter_referencing')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('referenced_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('alter_referencing_fk')
                    ->setUnquotedReferencingColumnNames('referenced_id')
                    ->setUnquotedReferencedTableName('alter_referenced')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropTableIfExists('alter_referencing');
        $this->dropTableIfExists('alter_referenced');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($referenced);
        $schemaManager->createTable($referencing);

        // testMigration() can't be used here: it introspects the table, which hits
        // https://github.com/doctrine/dbal/issues/7481.
        $desired = $referencing->edit()
            ->dropColumnByUnquotedName('referenced_id')
            ->dropForeignKeyConstraintByUnquotedName('alter_referencing_fk')
            ->create();

        $comparator = $schemaManager->createComparator();

        $diff = $comparator->compareTables($referencing, $desired);
        self::assertFalse($diff->isEmpty());

        $schemaManager->alterTable($diff);

        $introspected = $schemaManager->introspectTable($desired->getObjectName());

        self::assertTrue(
            $comparator->compareTables($desired, $introspected)->isEmpty(),
        );
    }

    public function testDropOneColumnCoveredByCompositeIndex(): void
    {
        $table = $this->indexedTable(
            Index::editor()
                ->setUnquotedName('alter_names_idx')
                ->setUnquotedColumnNames('first_name', 'last_name')
                ->create(),
        );

        $this->testMigration($table, static function (TableEditor $editor): void {
            $editor
                ->dropColumnByUnquotedName('last_name')
                ->dropIndexByUnquotedName('alter_names_idx')
                ->addIndex(
                    Index::editor()
                        ->setUnquotedName('alter_names_idx')
                        ->setUnquotedColumnNames('first_name')
                        ->create(),
                );
        });
    }

    public function testDropAllColumnsCoveredByIndex(): void
    {
        $table = $this->indexedTable(
            Index::editor()
                ->setUnquotedName('alter_name_idx')
                ->setUnquotedColumnNames('first_name')
                ->create(),
        );

        $this->testMigration($table, static function (TableEditor $editor): void {
            $editor
                ->dropColumnByUnquotedName('first_name')
                ->dropIndexByUnquotedName('alter_name_idx');
        });
    }

    private function indexedTable(Index $index): Table
    {
        $editor = Table::editor()
            ->setUnquotedName('alter_indexed')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            );

        foreach ($index->getIndexedColumns() as $indexedColumn) {
            $editor->addColumn(
                Column::editor()
                    ->setName($indexedColumn->getColumnName())
                    ->setTypeName(Types::STRING)
                    ->setLength(64)
                    ->create(),
            );
        }

        return $editor
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setIndexes($index)
            ->create();
    }

    /** @param callable(TableEditor): void $migration */
    private function testMigration(Table $oldTable, callable $migration): void
    {
        $this->dropAndCreateTable($oldTable);

        $schemaManager = $this->connection->createSchemaManager();

        $oldTable = $schemaManager->introspectTable($oldTable->getObjectName());

        $editor = $oldTable->edit();

        $migration($editor);

        $newTable = $editor->create();

        $diff = $schemaManager->createComparator()
            ->compareTables($oldTable, $newTable);

        self::assertFalse($diff->isEmpty());

        $schemaManager->alterTable($diff);

        $introspectedTable = $schemaManager->introspectTable($newTable->getObjectName());

        $diff = $schemaManager->createComparator()
            ->compareTables($newTable, $introspectedTable);

        self::assertTrue($diff->isEmpty());
    }
}
