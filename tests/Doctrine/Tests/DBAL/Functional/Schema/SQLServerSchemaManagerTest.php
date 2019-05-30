<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use function current;

class SQLServerSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function getPlatformName() : string
    {
        return 'mssql';
    }

    /**
     * @group DBAL-255
     */
    public function testDropColumnConstraints() : void
    {
        $table = new Table('sqlsrv_drop_column');
        $table->addColumn('id', 'integer');
        $table->addColumn('todrop', 'decimal', ['default' => 10.2]);

        $this->schemaManager->createTable($table);

        $diff = new TableDiff('sqlsrv_drop_column', [], [], [new Column('todrop', Type::getType('decimal'))]);
        $this->schemaManager->alterTable($diff);

        $columns = $this->schemaManager->listTableColumns('sqlsrv_drop_column');
        self::assertCount(1, $columns);
    }

    public function testColumnCollation() : void
    {
        $table  = new Table($tableName = 'test_collation');
        $column = $table->addColumn($columnName = 'test', 'string');

        $this->schemaManager->dropAndCreateTable($table);
        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertTrue($columns[$columnName]->hasPlatformOption('collation')); // SQL Server should report a default collation on the column

        $column->setPlatformOption('collation', $collation = 'Icelandic_CS_AS');

        $this->schemaManager->dropAndCreateTable($table);
        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertEquals($collation, $columns[$columnName]->getPlatformOption('collation'));
    }

    public function testDefaultConstraints() : void
    {
        $table = new Table('sqlsrv_default_constraints');
        $table->addColumn('no_default', 'string');
        $table->addColumn('df_integer', 'integer', ['default' => 666]);
        $table->addColumn('df_string_1', 'string', ['default' => 'foobar']);
        $table->addColumn('df_string_2', 'string', ['default' => 'Doctrine rocks!!!']);
        $table->addColumn('df_string_3', 'string', ['default' => 'another default value']);
        $table->addColumn('df_string_4', 'string', ['default' => 'column to rename']);
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
                    new Column('df_string_2', Type::getType('string')),
                    ['default'],
                    new Column('df_string_2', Type::getType('string'), ['default' => 'Doctrine rocks!!!'])
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
                'df_string_1' => new Column('df_string_1', Type::getType('string')),
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

    /**
     * @group DBAL-543
     */
    public function testColumnComments() : void
    {
        $table = new Table('sqlsrv_column_comment');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('comment_null', 'integer', ['comment' => null]);
        $table->addColumn('comment_false', 'integer', ['comment' => false]);
        $table->addColumn('comment_empty_string', 'integer', ['comment' => '']);
        $table->addColumn('comment_integer_0', 'integer', ['comment' => 0]);
        $table->addColumn('comment_float_0', 'integer', ['comment' => 0.0]);
        $table->addColumn('comment_string_0', 'integer', ['comment' => '0']);
        $table->addColumn('comment', 'integer', ['comment' => 'Doctrine 0wnz you!']);
        $table->addColumn('`comment_quoted`', 'integer', ['comment' => 'Doctrine 0wnz comments for explicitly quoted columns!']);
        $table->addColumn('create', 'integer', ['comment' => 'Doctrine 0wnz comments for reserved keyword columns!']);
        $table->addColumn('commented_type', 'object');
        $table->addColumn('commented_type_with_comment', 'array', ['comment' => 'Doctrine array type.']);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns('sqlsrv_column_comment');
        self::assertCount(12, $columns);
        self::assertNull($columns['id']->getComment());
        self::assertNull($columns['comment_null']->getComment());
        self::assertNull($columns['comment_false']->getComment());
        self::assertNull($columns['comment_empty_string']->getComment());
        self::assertEquals('0', $columns['comment_integer_0']->getComment());
        self::assertEquals('0', $columns['comment_float_0']->getComment());
        self::assertEquals('0', $columns['comment_string_0']->getComment());
        self::assertEquals('Doctrine 0wnz you!', $columns['comment']->getComment());
        self::assertEquals('Doctrine 0wnz comments for explicitly quoted columns!', $columns['comment_quoted']->getComment());
        self::assertEquals('Doctrine 0wnz comments for reserved keyword columns!', $columns['[create]']->getComment());
        self::assertNull($columns['commented_type']->getComment());
        self::assertEquals('Doctrine array type.', $columns['commented_type_with_comment']->getComment());

        $tableDiff                                                    = new TableDiff('sqlsrv_column_comment');
        $tableDiff->fromTable                                         = $table;
        $tableDiff->addedColumns['added_comment_none']                = new Column('added_comment_none', Type::getType('integer'));
        $tableDiff->addedColumns['added_comment_null']                = new Column('added_comment_null', Type::getType('integer'), ['comment' => null]);
        $tableDiff->addedColumns['added_comment_false']               = new Column('added_comment_false', Type::getType('integer'), ['comment' => false]);
        $tableDiff->addedColumns['added_comment_empty_string']        = new Column('added_comment_empty_string', Type::getType('integer'), ['comment' => '']);
        $tableDiff->addedColumns['added_comment_integer_0']           = new Column('added_comment_integer_0', Type::getType('integer'), ['comment' => 0]);
        $tableDiff->addedColumns['added_comment_float_0']             = new Column('added_comment_float_0', Type::getType('integer'), ['comment' => 0.0]);
        $tableDiff->addedColumns['added_comment_string_0']            = new Column('added_comment_string_0', Type::getType('integer'), ['comment' => '0']);
        $tableDiff->addedColumns['added_comment']                     = new Column('added_comment', Type::getType('integer'), ['comment' => 'Doctrine']);
        $tableDiff->addedColumns['`added_comment_quoted`']            = new Column('`added_comment_quoted`', Type::getType('integer'), ['comment' => 'rulez']);
        $tableDiff->addedColumns['select']                            = new Column('select', Type::getType('integer'), ['comment' => '666']);
        $tableDiff->addedColumns['added_commented_type']              = new Column('added_commented_type', Type::getType('object'));
        $tableDiff->addedColumns['added_commented_type_with_comment'] = new Column('added_commented_type_with_comment', Type::getType('array'), ['comment' => '666']);

        $tableDiff->renamedColumns['comment_float_0'] = new Column('comment_double_0', Type::getType('decimal'), ['comment' => 'Double for real!']);

        // Add comment to non-commented column.
        $tableDiff->changedColumns['id'] = new ColumnDiff(
            'id',
            new Column('id', Type::getType('integer'), ['autoincrement' => true, 'comment' => 'primary']),
            ['comment'],
            new Column('id', Type::getType('integer'), ['autoincrement' => true])
        );

        // Remove comment from null-commented column.
        $tableDiff->changedColumns['comment_null'] = new ColumnDiff(
            'comment_null',
            new Column('comment_null', Type::getType('string')),
            ['type'],
            new Column('comment_null', Type::getType('integer'), ['comment' => null])
        );

        // Add comment to false-commented column.
        $tableDiff->changedColumns['comment_false'] = new ColumnDiff(
            'comment_false',
            new Column('comment_false', Type::getType('integer'), ['comment' => 'false']),
            ['comment'],
            new Column('comment_false', Type::getType('integer'), ['comment' => false])
        );

        // Change type to custom type from empty string commented column.
        $tableDiff->changedColumns['comment_empty_string'] = new ColumnDiff(
            'comment_empty_string',
            new Column('comment_empty_string', Type::getType('object')),
            ['type'],
            new Column('comment_empty_string', Type::getType('integer'), ['comment' => ''])
        );

        // Change comment to false-comment from zero-string commented column.
        $tableDiff->changedColumns['comment_string_0'] = new ColumnDiff(
            'comment_string_0',
            new Column('comment_string_0', Type::getType('integer'), ['comment' => false]),
            ['comment'],
            new Column('comment_string_0', Type::getType('integer'), ['comment' => '0'])
        );

        // Remove comment from regular commented column.
        $tableDiff->changedColumns['comment'] = new ColumnDiff(
            'comment',
            new Column('comment', Type::getType('integer')),
            ['comment'],
            new Column('comment', Type::getType('integer'), ['comment' => 'Doctrine 0wnz you!'])
        );

        // Change comment and change type to custom type from regular commented column.
        $tableDiff->changedColumns['`comment_quoted`'] = new ColumnDiff(
            '`comment_quoted`',
            new Column('`comment_quoted`', Type::getType('array'), ['comment' => 'Doctrine array.']),
            ['comment', 'type'],
            new Column('`comment_quoted`', Type::getType('integer'), ['comment' => 'Doctrine 0wnz you!'])
        );

        // Remove comment and change type to custom type from regular commented column.
        $tableDiff->changedColumns['create'] = new ColumnDiff(
            'create',
            new Column('create', Type::getType('object')),
            ['comment', 'type'],
            new Column('create', Type::getType('integer'), ['comment' => 'Doctrine 0wnz comments for reserved keyword columns!'])
        );

        // Add comment and change custom type to regular type from non-commented column.
        $tableDiff->changedColumns['commented_type'] = new ColumnDiff(
            'commented_type',
            new Column('commented_type', Type::getType('integer'), ['comment' => 'foo']),
            ['comment', 'type'],
            new Column('commented_type', Type::getType('object'))
        );

        // Remove comment from commented custom type column.
        $tableDiff->changedColumns['commented_type_with_comment'] = new ColumnDiff(
            'commented_type_with_comment',
            new Column('commented_type_with_comment', Type::getType('array')),
            ['comment'],
            new Column('commented_type_with_comment', Type::getType('array'), ['comment' => 'Doctrine array type.'])
        );

        $tableDiff->removedColumns['comment_integer_0'] = new Column('comment_integer_0', Type::getType('integer'), ['comment' => 0]);

        $this->schemaManager->alterTable($tableDiff);

        $columns = $this->schemaManager->listTableColumns('sqlsrv_column_comment');
        self::assertCount(23, $columns);
        self::assertEquals('primary', $columns['id']->getComment());
        self::assertNull($columns['comment_null']->getComment());
        self::assertEquals('false', $columns['comment_false']->getComment());
        self::assertNull($columns['comment_empty_string']->getComment());
        self::assertEquals('0', $columns['comment_double_0']->getComment());
        self::assertNull($columns['comment_string_0']->getComment());
        self::assertNull($columns['comment']->getComment());
        self::assertEquals('Doctrine array.', $columns['comment_quoted']->getComment());
        self::assertNull($columns['[create]']->getComment());
        self::assertEquals('foo', $columns['commented_type']->getComment());
        self::assertNull($columns['commented_type_with_comment']->getComment());
        self::assertNull($columns['added_comment_none']->getComment());
        self::assertNull($columns['added_comment_null']->getComment());
        self::assertNull($columns['added_comment_false']->getComment());
        self::assertNull($columns['added_comment_empty_string']->getComment());
        self::assertEquals('0', $columns['added_comment_integer_0']->getComment());
        self::assertEquals('0', $columns['added_comment_float_0']->getComment());
        self::assertEquals('0', $columns['added_comment_string_0']->getComment());
        self::assertEquals('Doctrine', $columns['added_comment']->getComment());
        self::assertEquals('rulez', $columns['added_comment_quoted']->getComment());
        self::assertEquals('666', $columns['[select]']->getComment());
        self::assertNull($columns['added_commented_type']->getComment());
        self::assertEquals('666', $columns['added_commented_type_with_comment']->getComment());
    }

    public function testPkOrdering() : void
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
