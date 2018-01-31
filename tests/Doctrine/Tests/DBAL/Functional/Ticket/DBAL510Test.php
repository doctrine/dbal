<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;

/**
 * @group DBAL-510
 */
class DBAL510Test extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ($this->conn->getDatabasePlatform()->getName() !== "postgresql") {
            $this->markTestSkipped('PostgreSQL Only test');
        }
    }

    public function testSearchPathSchemaChanges()
    {
        $table = new Table("dbal510tbl");
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));

        $this->conn->getSchemaManager()->createTable($table);

        $onlineTable = $this->conn->getSchemaManager()->listTableDetails('dbal510tbl');

        $comparator = new Comparator();
        $diff = $comparator->diffTable($onlineTable, $table);

        self::assertFalse($diff);
    }
}
