<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

/**
 * @group DBAL-510
 */
class DBAL510Test extends DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ($this->connection->getDatabasePlatform()->getName() === 'postgresql') {
            return;
        }

        $this->markTestSkipped('PostgreSQL Only test');
    }

    public function testSearchPathSchemaChanges()
    {
        $table = new Table('dbal510tbl');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);

        $this->connection->getSchemaManager()->createTable($table);

        $onlineTable = $this->connection->getSchemaManager()->listTableDetails('dbal510tbl');

        $comparator = new Comparator();
        $diff       = $comparator->diffTable($onlineTable, $table);

        self::assertFalse($diff);
    }
}
