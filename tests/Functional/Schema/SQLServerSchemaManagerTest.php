<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;

use function current;

class SQLServerSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function getPlatformName(): string
    {
        return 'mssql';
    }

    /**
     * @group DBAL-255
     */
    public function testDropColumnConstraints(): void
    {
        $table = new Table('sqlsrv_drop_column');
        $table->addColumn('id', 'integer');
        $table->addColumn('todrop', 'decimal', ['default' => 10.2]);

        $this->schemaManager->createTable($table);

        $diff = new TableDiff('sqlsrv_drop_column', [], [], ['todrop' => new Column('todrop', Type::getType('decimal'))]);
        $this->schemaManager->alterTable($diff);

        $columns = $this->schemaManager->listTableColumns('sqlsrv_drop_column');
        self::assertCount(1, $columns);
    }

    public function testColumnCollation(): void
    {
        $table  = new Table($tableName = 'test_collation');
        $column = $table->addColumn('test', 'string', ['length' => 32]);

        $this->schemaManager->dropAndCreateTable($table);
        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertTrue($columns['test']->hasPlatformOption('collation')); // SQL Server should report a default collation on the column

        $column->setPlatformOption('collation', $collation = 'Icelandic_CS_AS');

        $this->schemaManager->dropAndCreateTable($table);
        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertEquals($collation, $columns['test']->getPlatformOption('collation'));
    }

    public function testDefaultConstraints(): void
    {
        $table = new Table('sqlsrv_default_constraints');
        $table->addColumn('no_default', 'string', ['length' => 32]);
        $table->addColumn('df_integer', 'integer', ['default' => 666]);
        $table->addColumn('df_string_1', 'string', [
            'length' => 32,
            'default' => 'foobar',
        ]);
        $table->addColumn('df_string_2', 'string', [
            'length' => 32,
            'default' => 'Doctrine rocks!!!',
        ]);
        $table->addColumn('df_string_3', 'string', [
            'length' => 32,
            'default' => 'another default value',
        ]);
        $table->addColumn('df_string_4', 'string', [
            'length' => 32,
            'default' => 'column to rename',
        ]);
        $table->addColumn('df_boolean', 'boolean', ['default' => true]);

        $this->schemaManager->createTable($table);
        $columns = $this->schemaManager->listTableColumns('sqlsrv_default_constraints');

        self::assertNull($columns['no_default']->getDefault());
        self::assertEquals(666, $columns['df_integer']->getDefault());
        self::assertEquals('foobar', $columns['df_string_1']->getDefault());
        self::assertEquals('Doctrine rocks!!!', $columns['df_string_2']->getDefault());
        self::assertEquals('another default value', $columns['df_string_3']->getDefault());
        self::assertEquals(1, $columns['df_boolean']->getDefault());

        $diff                                = new TableDiff(
            'sqlsrv_default_constraints',
            [],
            [
                'df_integer' => new ColumnDiff(
                    'df_integer',
                    new Column('df_integer', Type::getType('integer'), ['default' => 0]),
                    ['default'],
                    new Column('df_integer', Type::getType('integer'), ['default' => 666])
                ),
                'df_string_2' => new ColumnDiff(
                    'df_string_2',
                    new Column('df_string_2', Type::getType('string'), ['length' => 32]),
                    ['default'],
                    new Column('df_string_2', Type::getType('string'), [
                        'length' => 32,
                        'default' => 'Doctrine rocks!!!',
                    ])
                ),
                'df_string_3' => new ColumnDiff(
                    'df_string_3',
                    new Column('df_string_3', Type::getType('string'), ['length' => 50, 'default' => 'another default value']),
                    ['length'],
                    new Column('df_string_3', Type::getType('string'), ['length' => 50, 'default' => 'another default value'])
                ),
                'df_boolean' => new ColumnDiff(
                    'df_boolean',
                    new Column('df_boolean', Type::getType('boolean'), ['default' => false]),
                    ['default'],
                    new Column('df_boolean', Type::getType('boolean'), ['default' => true])
                ),
            ],
            [
                'df_string_1' => new Column('df_string_1', Type::getType('string'), ['length' => 32]),
            ],
            [],
            [],
            [],
            $table
        );
        $diff->newName                       = 'sqlsrv_default_constraints';
        $diff->renamedColumns['df_string_4'] = new Column(
            'df_string_renamed',
            Type::getType('string'),
            ['default' => 'column to rename']
        );

        $this->schemaManager->alterTable($diff);
        $columns = $this->schemaManager->listTableColumns('sqlsrv_default_constraints');

        self::assertNull($columns['no_default']->getDefault());
        self::assertEquals(0, $columns['df_integer']->getDefault());
        self::assertNull($columns['df_string_2']->getDefault());
        self::assertEquals('another default value', $columns['df_string_3']->getDefault());
        self::assertEquals(0, $columns['df_boolean']->getDefault());
        self::assertEquals('column to rename', $columns['df_string_renamed']->getDefault());

        /**
         * Test that column default constraints can still be referenced after table rename
         */
        $diff = new TableDiff(
            'sqlsrv_default_constraints',
            [],
            [
                'df_integer' => new ColumnDiff(
                    'df_integer',
                    new Column('df_integer', Type::getType('integer'), ['default' => 666]),
                    ['default'],
                    new Column('df_integer', Type::getType('integer'), ['default' => 0])
                ),
            ],
            [],
            [],
            [],
            [],
            $table
        );

        $this->schemaManager->alterTable($diff);
        $columns = $this->schemaManager->listTableColumns('sqlsrv_default_constraints');

        self::assertEquals(666, $columns['df_integer']->getDefault());
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
        $table->addColumn('colA', 'integer', ['notnull' => true]);
        $table->addColumn('colB', 'integer', ['notnull' => true]);
        $table->setPrimaryKey(['colB', 'colA']);
        $this->schemaManager->createTable($table);

        $indexes = $this->schemaManager->listTableIndexes('sqlsrv_pk_ordering');

        self::assertCount(1, $indexes);

        $firstIndex = current($indexes);
        $columns    = $firstIndex->getColumns();
        self::assertCount(2, $columns);
        self::assertEquals('colB', $columns[0]);
        self::assertEquals('colA', $columns[1]);
    }
}
