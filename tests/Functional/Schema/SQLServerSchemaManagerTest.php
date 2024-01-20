<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
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
        $table  = new Table($tableName = 'test_collation');
        $column = $table->addColumn('test', Types::STRING, ['length' => 32]);

        $this->dropAndCreateTable($table);
        $columns = $this->schemaManager->listTableColumns($tableName);

        // SQL Server should report a default collation on the column
        self::assertTrue($columns['test']->hasPlatformOption('collation'));

        $column->setPlatformOption('collation', $collation = 'Icelandic_CS_AS');

        $this->dropAndCreateTable($table);
        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertEquals($collation, $columns['test']->getPlatformOption('collation'));
    }

    public function testDefaultConstraints(): void
    {
        $oldTable = new Table('sqlsrv_default_constraints');
        $oldTable->addColumn('no_default', Types::STRING, ['length' => 32]);
        $oldTable->addColumn('df_integer', Types::INTEGER, ['default' => 666]);
        $oldTable->addColumn('df_string_1', Types::STRING, [
            'length' => 32,
            'default' => 'foobar',
        ]);
        $oldTable->addColumn('df_string_2', Types::STRING, [
            'length' => 32,
            'default' => 'Doctrine rocks!!!',
        ]);
        $oldTable->addColumn('df_string_3', Types::STRING, [
            'length' => 32,
            'default' => 'another default value',
        ]);
        $oldTable->addColumn('df_string_4', Types::STRING, [
            'length' => 32,
            'default' => 'column to rename',
        ]);
        $oldTable->addColumn('df_boolean', Types::BOOLEAN, ['default' => true]);

        $newTable = clone $oldTable;

        $this->schemaManager->createTable($oldTable);
        $columns = $this->schemaManager->listTableColumns('sqlsrv_default_constraints');

        self::assertNull($columns['no_default']->getDefault());
        self::assertEquals(666, $columns['df_integer']->getDefault());
        self::assertEquals('foobar', $columns['df_string_1']->getDefault());
        self::assertEquals('Doctrine rocks!!!', $columns['df_string_2']->getDefault());
        self::assertEquals('another default value', $columns['df_string_3']->getDefault());
        self::assertEquals(1, $columns['df_boolean']->getDefault());

        $newTable->getColumn('df_integer')
            ->setDefault(0);

        $newTable->dropColumn('df_string_1');

        $newTable->getColumn('df_string_2')
            ->setDefault(null);

        $newTable->getColumn('df_boolean')
            ->setDefault(false);

        $newTable->dropColumn('df_string_4');
        $newTable->addColumn('df_string_4_renamed', Types::STRING, [
            'length' => 32,
            'default' => 'column to rename',
        ]);

        $diff = $this->schemaManager->createComparator()
            ->compareTables(
                $this->schemaManager->introspectTable('sqlsrv_default_constraints'),
                $newTable,
            );

        $this->schemaManager->alterTable($diff);
        $columns = $this->schemaManager->listTableColumns('sqlsrv_default_constraints');

        self::assertNull($columns['no_default']->getDefault());
        self::assertEquals(0, $columns['df_integer']->getDefault());
        self::assertNull($columns['df_string_2']->getDefault());
        self::assertEquals('another default value', $columns['df_string_3']->getDefault());
        self::assertEquals(0, $columns['df_boolean']->getDefault());
        self::assertEquals('column to rename', $columns['df_string_4_renamed']->getDefault());
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
        $table = new Table('sqlsrv_pk_ordering');
        $table->addColumn('colA', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('colB', Types::INTEGER, ['notnull' => true]);
        $table->setPrimaryKey(['colB', 'colA']);
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

        $table = $this->schemaManager->introspectTable('test_nvarchar_max');

        self::assertSame(-1, $table->getColumn('col_nvarchar_max')->getLength());
        self::assertSame(128, $table->getColumn('col_nvarchar')->getLength());
    }
}
