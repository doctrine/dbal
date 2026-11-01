<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraintEditor;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

use function array_values;

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

    public function testAlterTableKeepsUniqueConstraint(): void
    {
        $this->dropTableIfExists('users');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable(
            Table::editor()
                ->setUnquotedName('users')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('id')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('email')
                        ->setTypeName(Types::STRING)
                        ->setLength(64)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('name')
                        ->setTypeName(Types::STRING)
                        ->setLength(32)
                        ->create(),
                )
                ->setUniqueConstraints(
                    UniqueConstraint::editor()
                        ->setUnquotedName('users_email_uq')
                        ->setUnquotedColumnNames('email')
                        ->create(),
                )
                ->create(),
        );

        $oldTable = $schemaManager->introspectTableByUnquotedName('users');

        $newTable = $oldTable->edit()
            ->modifyColumnByUnquotedName('name', static function (ColumnEditor $editor): void {
                $editor->setLength(64);
            })
            ->create();

        $schemaManager->alterTable(
            $schemaManager->createComparator()
                ->compareTables($oldTable, $newTable),
        );

        $this->assertUniqueConstraintListEquals(
            array_values($oldTable->getUniqueConstraints()),
            $schemaManager->introspectTableUniqueConstraintsByUnquotedName('users'),
        );
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

        $this->assertForeignKeyConstraintModification($old, $new);
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

        $this->assertForeignKeyConstraintModification($old, $new);
    }

    /**
     * Creates a table with the old foreign key and asserts that migrating it to the table with the new one reaches the
     * new table.
     */
    private function assertForeignKeyConstraintModification(ForeignKeyConstraint $old, ForeignKeyConstraint $new): void
    {
        $this->assertMigrationReachesDesiredTable(
            $this->createOrdersEditor()
                ->setForeignKeyConstraints($old)
                ->create(),
            $this->createOrdersEditor()
                ->setForeignKeyConstraints($new)
                ->create(),
        );
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

    public function testWithdrawUniqueIndexSupportingForeignKey(): void
    {
        $orders = $this->createOrdersEditor()
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('orders_article_uq')
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('article_id')
                    ->create(),
            );

        $this->assertMigrationReachesDesiredTable(
            $orders->create(),
            $this->createOrdersEditor()->create(),
        );
    }

    public function testWithdrawUniqueIndexSupportingUnnamedForeignKey(): void
    {
        $oldOrders = $this->createOrdersEditor()
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('orders_article_uq')
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('article_id')
                    ->create(),
            )
            ->create();

        $newOrders = $this->createOrdersEditor()
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('article_id')
                    ->setUnquotedReferencedTableName('articles')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->assertMigrationReachesDesiredTable($oldOrders, $newOrders);
    }

    public function testWithdrawUniqueIndexSupportingEqualForeignKeys(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('Only MySQL and MariaDB accept two equal foreign keys on one table.');
        }

        $oldOrders = $this->createOrdersEditor()
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('orders_article_uq')
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('article_id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                $this->createArticlesForeignKeyEditor()
                    ->setUnquotedName('articles_fk')
                    ->create(),
                $this->createArticlesForeignKeyEditor()
                    ->setUnquotedName('articles_fk2')
                    ->create(),
            )
            ->create();

        $newOrders = $this->createOrdersEditor()
            ->setForeignKeyConstraints(
                $this->createArticlesForeignKeyEditor()
                    ->setUnquotedName('articles_fk')
                    ->create(),
                $this->createArticlesForeignKeyEditor()
                    ->create(),
            )
            ->create();

        $this->assertMigrationReachesDesiredTable($oldOrders, $newOrders);
    }

    public function testDropPrimaryKeySupportingForeignKey(): void
    {
        $orders = $this->createOrdersEditor()
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('article_id')
                    ->create(),
            );

        $this->assertMigrationReachesDesiredTable(
            $orders->create(),
            $this->createOrdersEditor()->create(),
        );
    }

    public function testMoveIndexSupportingForeignKeyToAnotherColumn(): void
    {
        $prototype = Index::editor()
            ->setUnquotedName('orders_idx');

        $oldOrders = $this->createOrdersEditor()
            ->setIndexes(
                $prototype
                    ->setUnquotedColumnNames('article_id')
                    ->create(),
            )
            ->create();

        $newOrders = $this->createOrdersEditor()
            ->setIndexes(
                $prototype
                    ->setUnquotedColumnNames('quantity')
                    ->create(),
            )
            ->create();

        $this->assertMigrationReachesDesiredTable($oldOrders, $newOrders);
    }

    public function testWithdrawIndexSupportingForeignKeyAndAddWiderForeignKey(): void
    {
        $narrowForeignKey = ForeignKeyConstraint::editor()
            ->setUnquotedName('articles_fk')
            ->setUnquotedReferencingColumnNames('article_id')
            ->setUnquotedReferencedTableName('articles')
            ->setUnquotedReferencedColumnNames('id')
            ->create();

        $oldOrders = $this->createOrdersEditor()
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('orders_idx')
                    ->setUnquotedColumnNames('article_id')
                    ->create(),
            )
            ->create();

        $newOrders = $this->createOrdersEditor()
            ->setForeignKeyConstraints(
                $narrowForeignKey,
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('articles_sku_fk')
                    ->setUnquotedReferencingColumnNames('article_id', 'article_sku')
                    ->setUnquotedReferencedTableName('articles')
                    ->setUnquotedReferencedColumnNames('id', 'sku')
                    ->create(),
            )
            ->create();

        $this->assertMigrationReachesDesiredTable($oldOrders, $newOrders);
    }

    private function createArticlesForeignKeyEditor(): ForeignKeyConstraintEditor
    {
        return ForeignKeyConstraint::editor()
            ->setUnquotedReferencingColumnNames('article_id')
            ->setUnquotedReferencedTableName('articles')
            ->setUnquotedReferencedColumnNames('id');
    }

    private function createOrdersEditor(): TableEditor
    {
        return Table::editor()
            ->setUnquotedName('orders')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('article_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('article_sku')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('quantity')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('articles_fk')
                    ->setUnquotedReferencingColumnNames('article_id')
                    ->setUnquotedReferencedTableName('articles')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            );
    }

    private function assertMigrationReachesDesiredTable(Table $oldOrders, Table $newOrders): void
    {
        $this->dropTableIfExists('orders');
        $this->dropTableIfExists('articles');

        $schemaManager = $this->connection->createSchemaManager();

        $schemaManager->createTable(
            Table::editor()
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
                    UniqueConstraint::editor()
                        ->setUnquotedColumnNames('id', 'sku')
                        ->create(),
                )
                ->create(),
        );

        $schemaManager->createTable($oldOrders);

        $comparator = $schemaManager->createComparator();

        $schemaManager->alterTable(
            $comparator->compareTables($schemaManager->introspectTableByUnquotedName('orders'), $newOrders),
        );

        self::assertTrue(
            $comparator->compareTables($schemaManager->introspectTableByUnquotedName('orders'), $newOrders)
                ->isEmpty(),
        );
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
