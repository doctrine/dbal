<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

use function array_shift;

class SQLServerSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof SQLServerPlatform;
    }

    public function testColumnCollation(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_collation')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('test')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);
        [$test] = $this->schemaManager->introspectTableColumnsByUnquotedName('test_collation');

        // SQL Server should report a default collation on the column
        self::assertNotNull($test->getCollation());

        $table = $table->edit()
            ->modifyColumnByUnquotedName('test', static function (ColumnEditor $editor): void {
                $editor->setCollation('Icelandic_CS_AS');
            })
            ->create();

        $this->dropAndCreateTable($table);
        [$test] = $this->schemaManager->introspectTableColumnsByUnquotedName('test_collation');

        self::assertEquals('Icelandic_CS_AS', $test->getCollation());
    }

    public function testDefaultConstraints(): void
    {
        $oldTable = Table::editor()
            ->setUnquotedName('sqlsrv_default_constraints')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('no_default')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('df_integer')
                    ->setTypeName(Types::INTEGER)
                    ->setDefaultValue(666)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('df_string_1')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setDefaultValue('foobar')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('df_string_2')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setDefaultValue('Doctrine rocks!!!')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('df_string_3')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setDefaultValue('another default value')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('df_string_4')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setDefaultValue('column to rename')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('df_boolean')
                    ->setTypeName(Types::BOOLEAN)
                    ->setDefaultValue(true)
                    ->create(),
            )
            ->create();

        $this->schemaManager->createTable($oldTable);
        [$noDefault, $dfInteger, $dfString1, $dfString2, $dfString3, $dfString4, $dfBoolean]
            = $this->schemaManager->introspectTableColumnsByUnquotedName('sqlsrv_default_constraints');

        self::assertNull($noDefault->getDefault());
        self::assertEquals(666, $dfInteger->getDefault());
        self::assertEquals('foobar', $dfString1->getDefault());
        self::assertEquals('Doctrine rocks!!!', $dfString2->getDefault());
        self::assertEquals('another default value', $dfString3->getDefault());
        self::assertEquals('column to rename', $dfString4->getDefault());
        self::assertEquals(1, $dfBoolean->getDefault());

        $newTable = $oldTable->edit()
            ->modifyColumnByUnquotedName('df_integer', static function (ColumnEditor $editor): void {
                $editor->setDefaultValue(0);
            })
            ->modifyColumnByUnquotedName('df_string_2', static function (ColumnEditor $editor): void {
                $editor->setDefaultValue(null);
            })
            ->modifyColumnByUnquotedName('df_boolean', static function (ColumnEditor $editor): void {
                $editor->setDefaultValue(false);
            })
            ->dropColumnByUnquotedName('df_string_1')
            ->dropColumnByUnquotedName('df_string_4')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('df_string_4_renamed')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setDefaultValue('column to rename')
                    ->create(),
            )
            ->create();

        $diff = $this->schemaManager->createComparator()
            ->compareTables(
                $this->schemaManager->introspectTableByUnquotedName('sqlsrv_default_constraints'),
                $newTable,
            );

        $this->schemaManager->alterTable($diff);
        [$noDefault, $dfInteger, $dfString2, $dfString3, $dfString4Renamed, $dfBoolean]
            = $this->schemaManager->introspectTableColumnsByUnquotedName('sqlsrv_default_constraints');

        self::assertNull($noDefault->getDefault());
        self::assertEquals(0, $dfInteger->getDefault());
        self::assertNull($dfString2->getDefault());
        self::assertEquals('another default value', $dfString3->getDefault());
        self::assertEquals('column to rename', $dfString4Renamed->getDefault());
        self::assertEquals(0, $dfBoolean->getDefault());
    }

    public function testPkOrdering(): void
    {
        // SQL Server stores index column information in a system table with two
        // columns that almost always have the same value: index_column_id and key_ordinal.
        // The only situation when the two values doesn't match up is when a clustered index
        // is declared that references columns in a different order from which they are
        // declared in the table. In that case, key_ordinal != index_column_id.
        // key_ordinal holds the index ordering. index_column_id is just a unique identifier
        // for index columns within the given index.
        $table = Table::editor()
            ->setUnquotedName('sqlsrv_pk_ordering')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('colA')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('colB')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('colB', 'colA')
                    ->create(),
            )
            ->create();

        $this->schemaManager->createTable($table);

        $indexes = $this->schemaManager->listTableIndexes('sqlsrv_pk_ordering');

        self::assertCount(1, $indexes);
        $firstIndex = array_shift($indexes);
        self::assertNotNull($firstIndex);

        self::assertSame(['colB', 'colA'], $firstIndex->getColumns());
    }

    public function testNvarcharMaxIsLengthMinus1(): void
    {
        $sql = 'CREATE TABLE test_nvarchar_max (
            col_nvarchar_max NVARCHAR(MAX),
            col_nvarchar NVARCHAR(128)
        )';

        $this->connection->executeStatement($sql);

        $table = $this->schemaManager->introspectTableByUnquotedName('test_nvarchar_max');

        self::assertSame(Types::TEXT, $table->getColumn('col_nvarchar_max')->getType()->getName());
        self::assertSame(Types::STRING, $table->getColumn('col_nvarchar')->getType()->getName());
        self::assertSame(-1, $table->getColumn('col_nvarchar_max')->getLength());
        self::assertSame(128, $table->getColumn('col_nvarchar')->getLength());
    }

    /** @link https://learn.microsoft.com/en-us/sql/relational-databases/security/authentication-access/ownership-and-user-schema-separation?view=sql-server-ver16#the-dbo-schema */
    public function getExpectedDefaultSchemaName(): string
    {
        return 'dbo';
    }
}
