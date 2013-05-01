<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;

/**
 * @group DBAL-510
 */
class DBAL510Test extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function setUp()
    {
        parent::setUp();

        if ($this->_conn->getDatabasePlatform()->getName() !== "postgresql") {
            $this->markTestSkipped('PostgreSQL Only test');
        }
    }

    public function testSearchPathSchemaChanges()
    {
        $table = new Table("dbal510tbl");
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));

        $this->_conn->getSchemaManager()->createTable($table);

        $onlineTable = $this->_conn->getSchemaManager()->listTableDetails('dbal510tbl');

        $comparator = new Comparator();
        $diff = $comparator->diffTable($onlineTable, $table);

        $this->assertFalse($diff);
    }
}
