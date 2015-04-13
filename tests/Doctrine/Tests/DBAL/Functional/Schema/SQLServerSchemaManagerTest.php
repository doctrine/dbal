<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;

class SQLServerSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function getPlatformName()
    {
        return "mssql";
    }

    /**
     * @group DBAL-255
     */
    public function testDropColumnConstraints()
    {
        $table = new Table('sqlsrv_drop_column');
        $table->addColumn('id', 'integer');
        $table->addColumn('todrop', 'decimal', array('default' => 10.2));

        $this->_sm->createTable($table);

        $diff = new TableDiff('sqlsrv_drop_column', array(), array(), array(
            new Column('todrop', Type::getType('decimal'))
        ));
        $this->_sm->alterTable($diff);

        $columns = $this->_sm->listTableColumns('sqlsrv_drop_column');
        $this->assertEquals(1, count($columns));
    }

    public function testColumnCollation()
    {
        $table = new \Doctrine\DBAL\Schema\Table($tableName = 'test_collation');
        $column = $table->addColumn($columnName = 'test', 'string');

        $this->_sm->dropAndCreateTable($table);
        $columns = $this->_sm->listTableColumns($tableName);

        $this->assertTrue($columns[$columnName]->hasPlatformOption('collation')); // SQL Server should report a default collation on the column

        $column->setPlatformOption('collation', $collation = 'Icelandic_CS_AS');

        $this->_sm->dropAndCreateTable($table);
        $columns = $this->_sm->listTableColumns($tableName);

        $this->assertEquals($collation, $columns[$columnName]->getPlatformOption('collation'));
    }

    public function testDefaultContraints()
    {
        $table = new Table('sqlsrv_default_constraints');
        $table->addColumn('no_default', 'string');
        $table->addColumn('df_integer', 'integer', array('default' => 666));
        $table->addColumn('df_string_1', 'string', array('default' => 'foobar'));
        $table->addColumn('df_string_2', 'string', array('default' => 'Doctrine rocks!!!'));
        $table->addColumn('df_string_3', 'string', array('default' => 'another default value'));
        $table->addColumn('df_string_4', 'string', array('default' => 'column to rename'));
        $table->addColumn('df_boolean', 'boolean', array('default' => true));

        $this->_sm->createTable($table);
        $columns = $this->_sm->listTableColumns('sqlsrv_default_constraints');

        $this->assertNull($columns['no_default']->getDefault());
        $this->assertEquals(666, $columns['df_integer']->getDefault());
        $this->assertEquals('foobar', $columns['df_string_1']->getDefault());
        $this->assertEquals('Doctrine rocks!!!', $columns['df_string_2']->getDefault());
        $this->assertEquals('another default value', $columns['df_string_3']->getDefault());
        $this->assertEquals(1, $columns['df_boolean']->getDefault());

        $diff = new TableDiff(
            'sqlsrv_default_constraints',
            array(
                new Column('df_current_timestamp', Type::getType('datetime'), array('default' => 'CURRENT_TIMESTAMP'))
            ),
            array(
                'df_integer' => new ColumnDiff(
                    'df_integer',
                    new Column('df_integer', Type::getType('integer'), array('default' => 0)),
                    array('default'),
                    new Column('df_integer', Type::getType('integer'), array('default' => 666))
                ),
                'df_string_2' => new ColumnDiff(
                    'df_string_2',
                    new Column('df_string_2', Type::getType('string')),
                    array('default'),
                    new Column('df_string_2', Type::getType('string'), array('default' => 'Doctrine rocks!!!'))
                ),
                'df_string_3' => new ColumnDiff(
                    'df_string_3',
                    new Column('df_string_3', Type::getType('string'), array('length' => 50, 'default' => 'another default value')),
                    array('length'),
                    new Column('df_string_3', Type::getType('string'), array('length' => 50, 'default' => 'another default value'))
                ),
                'df_boolean' => new ColumnDiff(
                    'df_boolean',
                    new Column('df_boolean', Type::getType('boolean'), array('default' => false)),
                    array('default'),
                    new Column('df_boolean', Type::getType('boolean'), array('default' => true))
                )
            ),
            array(
                'df_string_1' => new Column('df_string_1', Type::getType('string'))
            ),
            array(),
            array(),
            array(),
            $table
        );
        $diff->newName = 'sqlsrv_default_constraints';
        $diff->renamedColumns['df_string_4'] = new Column(
            'df_string_renamed',
            Type::getType('string'),
            array('default' => 'column to rename')
        );

        $this->_sm->alterTable($diff);
        $columns = $this->_sm->listTableColumns('sqlsrv_default_constraints');

        $this->assertNull($columns['no_default']->getDefault());
        $this->assertEquals('CURRENT_TIMESTAMP', $columns['df_current_timestamp']->getDefault());
        $this->assertEquals(0, $columns['df_integer']->getDefault());
        $this->assertNull($columns['df_string_2']->getDefault());
        $this->assertEquals('another default value', $columns['df_string_3']->getDefault());
        $this->assertEquals(0, $columns['df_boolean']->getDefault());
        $this->assertEquals('column to rename', $columns['df_string_renamed']->getDefault());

        /**
         * Test that column default constraints can still be referenced after table rename
         */
        $diff = new TableDiff(
            'sqlsrv_default_constraints',
            array(),
            array(
                'df_current_timestamp' => new ColumnDiff(
                    'df_current_timestamp',
                    new Column('df_current_timestamp', Type::getType('datetime')),
                    array('default'),
                    new Column('df_current_timestamp', Type::getType('datetime'), array('default' => 'CURRENT_TIMESTAMP'))
                ),
                'df_integer' => new ColumnDiff(
                    'df_integer',
                    new Column('df_integer', Type::getType('integer'), array('default' => 666)),
                    array('default'),
                    new Column('df_integer', Type::getType('integer'), array('default' => 0))
                )
            ),
            array(),
            array(),
            array(),
            array(),
            $table
        );

        $this->_sm->alterTable($diff);
        $columns = $this->_sm->listTableColumns('sqlsrv_default_constraints');

        $this->assertNull($columns['df_current_timestamp']->getDefault());
        $this->assertEquals(666, $columns['df_integer']->getDefault());
    }

    /**
     * @group DBAL-543
     */
    public function testColumnComments()
    {
        $table = new Table('sqlsrv_column_comment');
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('comment_null', 'integer', array('comment' => null));
        $table->addColumn('comment_false', 'integer', array('comment' => false));
        $table->addColumn('comment_empty_string', 'integer', array('comment' => ''));
        $table->addColumn('comment_integer_0', 'integer', array('comment' => 0));
        $table->addColumn('comment_float_0', 'integer', array('comment' => 0.0));
        $table->addColumn('comment_string_0', 'integer', array('comment' => '0'));
        $table->addColumn('comment', 'integer', array('comment' => 'Doctrine 0wnz you!'));
        $table->addColumn('`comment_quoted`', 'integer', array('comment' => 'Doctrine 0wnz comments for explicitely quoted columns!'));
        $table->addColumn('create', 'integer', array('comment' => 'Doctrine 0wnz comments for reserved keyword columns!'));
        $table->addColumn('commented_type', 'object');
        $table->addColumn('commented_type_with_comment', 'array', array('comment' => 'Doctrine array type.'));
        $table->setPrimaryKey(array('id'));

        $this->_sm->createTable($table);

        $columns = $this->_sm->listTableColumns("sqlsrv_column_comment");
        $this->assertEquals(12, count($columns));
        $this->assertNull($columns['id']->getComment());
        $this->assertNull($columns['comment_null']->getComment());
        $this->assertNull($columns['comment_false']->getComment());
        $this->assertNull($columns['comment_empty_string']->getComment());
        $this->assertEquals('0', $columns['comment_integer_0']->getComment());
        $this->assertEquals('0', $columns['comment_float_0']->getComment());
        $this->assertEquals('0', $columns['comment_string_0']->getComment());
        $this->assertEquals('Doctrine 0wnz you!', $columns['comment']->getComment());
        $this->assertEquals('Doctrine 0wnz comments for explicitely quoted columns!', $columns['comment_quoted']->getComment());
        $this->assertEquals('Doctrine 0wnz comments for reserved keyword columns!', $columns['[create]']->getComment());
        $this->assertNull($columns['commented_type']->getComment());
        $this->assertEquals('Doctrine array type.', $columns['commented_type_with_comment']->getComment());

        $tableDiff = new TableDiff('sqlsrv_column_comment');
        $tableDiff->fromTable = $table;
        $tableDiff->addedColumns['added_comment_none'] = new Column('added_comment_none', Type::getType('integer'));
        $tableDiff->addedColumns['added_comment_null'] = new Column('added_comment_null', Type::getType('integer'), array('comment' => null));
        $tableDiff->addedColumns['added_comment_false'] = new Column('added_comment_false', Type::getType('integer'), array('comment' => false));
        $tableDiff->addedColumns['added_comment_empty_string'] = new Column('added_comment_empty_string', Type::getType('integer'), array('comment' => ''));
        $tableDiff->addedColumns['added_comment_integer_0'] = new Column('added_comment_integer_0', Type::getType('integer'), array('comment' => 0));
        $tableDiff->addedColumns['added_comment_float_0'] = new Column('added_comment_float_0', Type::getType('integer'), array('comment' => 0.0));
        $tableDiff->addedColumns['added_comment_string_0'] = new Column('added_comment_string_0', Type::getType('integer'), array('comment' => '0'));
        $tableDiff->addedColumns['added_comment'] = new Column('added_comment', Type::getType('integer'), array('comment' => 'Doctrine'));
        $tableDiff->addedColumns['`added_comment_quoted`'] = new Column('`added_comment_quoted`', Type::getType('integer'), array('comment' => 'rulez'));
        $tableDiff->addedColumns['select'] = new Column('select', Type::getType('integer'), array('comment' => '666'));
        $tableDiff->addedColumns['added_commented_type'] = new Column('added_commented_type', Type::getType('object'));
        $tableDiff->addedColumns['added_commented_type_with_comment'] = new Column('added_commented_type_with_comment', Type::getType('array'), array('comment' => '666'));

        $tableDiff->renamedColumns['comment_float_0'] = new Column('comment_double_0', Type::getType('decimal'), array('comment' => 'Double for real!'));

        // Add comment to non-commented column.
        $tableDiff->changedColumns['id'] = new ColumnDiff(
            'id',
            new Column('id', Type::getType('integer'), array('autoincrement' => true, 'comment' => 'primary')),
            array('comment'),
            new Column('id', Type::getType('integer'), array('autoincrement' => true))
        );

        // Remove comment from null-commented column.
        $tableDiff->changedColumns['comment_null'] = new ColumnDiff(
            'comment_null',
            new Column('comment_null', Type::getType('string')),
            array('type'),
            new Column('comment_null', Type::getType('integer'), array('comment' => null))
        );

        // Add comment to false-commented column.
        $tableDiff->changedColumns['comment_false'] = new ColumnDiff(
            'comment_false',
            new Column('comment_false', Type::getType('integer'), array('comment' => 'false')),
            array('comment'),
            new Column('comment_false', Type::getType('integer'), array('comment' => false))
        );

        // Change type to custom type from empty string commented column.
        $tableDiff->changedColumns['comment_empty_string'] = new ColumnDiff(
            'comment_empty_string',
            new Column('comment_empty_string', Type::getType('object')),
            array('type'),
            new Column('comment_empty_string', Type::getType('integer'), array('comment' => ''))
        );

        // Change comment to false-comment from zero-string commented column.
        $tableDiff->changedColumns['comment_string_0'] = new ColumnDiff(
            'comment_string_0',
            new Column('comment_string_0', Type::getType('integer'), array('comment' => false)),
            array('comment'),
            new Column('comment_string_0', Type::getType('integer'), array('comment' => '0'))
        );

        // Remove comment from regular commented column.
        $tableDiff->changedColumns['comment'] = new ColumnDiff(
            'comment',
            new Column('comment', Type::getType('integer')),
            array('comment'),
            new Column('comment', Type::getType('integer'), array('comment' => 'Doctrine 0wnz you!'))
        );

        // Change comment and change type to custom type from regular commented column.
        $tableDiff->changedColumns['`comment_quoted`'] = new ColumnDiff(
            '`comment_quoted`',
            new Column('`comment_quoted`', Type::getType('array'), array('comment' => 'Doctrine array.')),
            array('comment', 'type'),
            new Column('`comment_quoted`', Type::getType('integer'), array('comment' => 'Doctrine 0wnz you!'))
        );

        // Remove comment and change type to custom type from regular commented column.
        $tableDiff->changedColumns['create'] = new ColumnDiff(
            'create',
            new Column('create', Type::getType('object')),
            array('comment', 'type'),
            new Column('create', Type::getType('integer'), array('comment' => 'Doctrine 0wnz comments for reserved keyword columns!'))
        );

        // Add comment and change custom type to regular type from non-commented column.
        $tableDiff->changedColumns['commented_type'] = new ColumnDiff(
            'commented_type',
            new Column('commented_type', Type::getType('integer'), array('comment' => 'foo')),
            array('comment', 'type'),
            new Column('commented_type', Type::getType('object'))
        );

        // Remove comment from commented custom type column.
        $tableDiff->changedColumns['commented_type_with_comment'] = new ColumnDiff(
            'commented_type_with_comment',
            new Column('commented_type_with_comment', Type::getType('array')),
            array('comment'),
            new Column('commented_type_with_comment', Type::getType('array'), array('comment' => 'Doctrine array type.'))
        );

        $tableDiff->removedColumns['comment_integer_0'] = new Column('comment_integer_0', Type::getType('integer'), array('comment' => 0));

        $this->_sm->alterTable($tableDiff);

        $columns = $this->_sm->listTableColumns("sqlsrv_column_comment");
        $this->assertEquals(23, count($columns));
        $this->assertEquals('primary', $columns['id']->getComment());
        $this->assertNull($columns['comment_null']->getComment());
        $this->assertEquals('false', $columns['comment_false']->getComment());
        $this->assertNull($columns['comment_empty_string']->getComment());
        $this->assertEquals('0', $columns['comment_double_0']->getComment());
        $this->assertNull($columns['comment_string_0']->getComment());
        $this->assertNull($columns['comment']->getComment());
        $this->assertEquals('Doctrine array.', $columns['comment_quoted']->getComment());
        $this->assertNull($columns['[create]']->getComment());
        $this->assertEquals('foo', $columns['commented_type']->getComment());
        $this->assertNull($columns['commented_type_with_comment']->getComment());
        $this->assertNull($columns['added_comment_none']->getComment());
        $this->assertNull($columns['added_comment_null']->getComment());
        $this->assertNull($columns['added_comment_false']->getComment());
        $this->assertNull($columns['added_comment_empty_string']->getComment());
        $this->assertEquals('0', $columns['added_comment_integer_0']->getComment());
        $this->assertEquals('0', $columns['added_comment_float_0']->getComment());
        $this->assertEquals('0', $columns['added_comment_string_0']->getComment());
        $this->assertEquals('Doctrine', $columns['added_comment']->getComment());
        $this->assertEquals('rulez', $columns['added_comment_quoted']->getComment());
        $this->assertEquals('666', $columns['[select]']->getComment());
        $this->assertNull($columns['added_commented_type']->getComment());
        $this->assertEquals('666', $columns['added_commented_type_with_comment']->getComment());
    }

    public function testNamedPrimaryKeyConstraint()
    {
        $primaryTable = new Table("primary_related_table");
        $primaryTable->addColumn("id", "integer", array('autoincrement' => true));
        $primaryTable->setPrimaryKey(array('id'), "pk_primary_related_table");
        $this->_sm->createTable($primaryTable);

        $childTable = new Table("child_related_table");
        $childTable->addColumn("id", "integer");
        $childTable->addColumn("id_zone", "integer");
        $childTable->setPrimaryKey(array('id_zone', 'id'), "pk_child_related_table");
        $this->_sm->createTable($childTable);

        $childTable = $this->_sm->listTableDetails("child_related_table");

        $tableDiff = new TableDiff("child_related_table");
        $tableDiff->fromTable = $childTable;
        $tableDiff->changedIndexes["pk_child_related_table"] = new Index("pk_child_related_table", array('id'), true, true, array("clustered", true));
        $this->_sm->alterTable($tableDiff);
        $childTableIndexes = $this->_sm->listTableIndexes("child_related_table");
        $this->assertArrayHasKey("primary", $childTableIndexes);
        $this->assertEquals("pk_child_related_table", $childTableIndexes['primary']->getName());
        $this->assertEquals(1, count($childTableIndexes['primary']->getColumns()));
    }
}
