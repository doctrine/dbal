<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
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
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::markTestSkipped(
                'SQLite will automatically set up auto-increment behavior on the primary key column, which this test'
                    . ' does not expect.',
            );
        }

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

        $this->ensureDroppingPrimaryKeyConstraintIsSupported();

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
        }, (new ComparatorConfig())->withReportModifiedIndexes(false));
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
        }, (new ComparatorConfig())->withReportModifiedIndexes(false));
    }

    public function testAddNewColumnToPrimaryKey(): void
    {
        $this->ensureDroppingPrimaryKeyConstraintIsSupported();

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

    public function testReplaceForeignKeyConstraint(): void
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
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('articles_fk')
                    ->setUnquotedReferencingColumnNames('article_id')
                    ->setUnquotedReferencedTableName('articles')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropTableIfExists('orders');
        $this->dropTableIfExists('articles');

        $this->connection->createSchemaManager()
            ->createTable($articles);

        $this->testMigration($orders, static function (TableEditor $editor): void {
            $editor
                ->dropForeignKeyConstraintByUnquotedName('articles_fk')
                ->addForeignKeyConstraint(
                    ForeignKeyConstraint::editor()
                        ->setUnquotedName('articles_fk')
                        ->setUnquotedReferencingColumnNames('article_sku')
                        ->setUnquotedReferencedTableName('articles')
                        ->setUnquotedReferencedColumnNames('sku')
                        ->create(),
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

    /** @param callable(TableEditor): void $migration */
    private function testMigration(Table $oldTable, callable $migration, ?ComparatorConfig $config = null): void
    {
        $this->dropAndCreateTable($oldTable);

        $editor = $oldTable->edit();

        $migration($editor);

        $newTable = $editor->create();

        $schemaManager = $this->connection->createSchemaManager();

        $diff = $schemaManager->createComparator($config ?? new ComparatorConfig())
            ->compareTables($oldTable, $newTable);

        self::assertFalse($diff->isEmpty());

        $schemaManager->alterTable($diff);

        $introspectedTable = $schemaManager->introspectTable(
            $newTable->getObjectName()
                ->toString(),
        );

        $diff = $schemaManager->createComparator()
            ->compareTables($newTable, $introspectedTable);

        self::assertTrue($diff->isEmpty());
    }
}
