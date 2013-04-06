<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Column;
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

    public function testCollationCharset()
    {
        $table = new \Doctrine\DBAL\Schema\Table($tableName = 'test_collation_charset');
        $column = $table->addColumn($columnName = 'test', 'string');

        $this->_sm->dropAndCreateTable($table);
        $columns = $this->_sm->listTableColumns($tableName);

        $this->assertTrue($columns[$columnName]->hasPlatformOption('collate')); // SQL Server should report a default collation on the column

        $column->setPlatformOption('collate', $collation = 'Icelandic_CS_AS');

        $this->_sm->dropAndCreateTable($table);
        $columns = $this->_sm->listTableColumns($tableName);

        $this->assertEquals($collation, $columns[$columnName]->getPlatformOption('collate'));
    }

    public function testDefaultContraints()
    {
        $table = new Table('sqlsrv_df_constraints');
        $table->addColumn('no_default', 'string');
        $table->addColumn('df_integer', 'integer', array('default' => 666));
        $table->addColumn('df_string_1', 'string', array('default' => 'foobar'));
        $table->addColumn('df_string_2', 'string', array('default' => 'Doctrine rocks!!!'));
        $table->addColumn('df_string_3', 'string', array('default' => 'another default value'));
        $table->addColumn('df_string_4', 'string', array('default' => 'column to rename'));
        $table->addColumn('df_boolean', 'boolean', array('default' => true));

        $this->_sm->createTable($table);
        $columns = $this->_sm->listTableColumns('sqlsrv_df_constraints');

        $this->assertNull($columns['no_default']->getDefault());
        $this->assertEquals(666, $columns['df_integer']->getDefault());
        $this->assertEquals('foobar', $columns['df_string_1']->getDefault());
        $this->assertEquals('Doctrine rocks!!!', $columns['df_string_2']->getDefault());
        $this->assertEquals('another default value', $columns['df_string_3']->getDefault());
        $this->assertEquals(1, $columns['df_boolean']->getDefault());

        $diff = new TableDiff(
            'sqlsrv_df_constraints',
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
}
