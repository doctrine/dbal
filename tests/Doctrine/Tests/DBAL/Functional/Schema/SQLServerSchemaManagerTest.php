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
}
