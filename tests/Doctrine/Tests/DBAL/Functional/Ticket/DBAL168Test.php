<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

/**
 * @group DBAL-168
 */
class DBAL168Test extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function testDomainsTable()
    {
        if ($this->_conn->getDatabasePlatform()->getName() != "postgresql") {
            $this->markTestSkipped('PostgreSQL only test');
        }

        $table = new \Doctrine\DBAL\Schema\Table("domains");
        $table->addColumn('id', 'integer');
        $table->addColumn('parent_id', 'integer');
        $table->setPrimaryKey(array('id'));
        $table->addForeignKeyConstraint('domains', array('parent_id'), array('id'));

        $this->_conn->getSchemaManager()->createTable($table);
        $table = $this->_conn->getSchemaManager()->listTableDetails('domains');

        $this->assertEquals('domains', $table->getName());
    }
}