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
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint('domains', ['parent_id'], ['id']);

        $this->_conn->getSchemaManager()->createTable($table);
        $table = $this->_conn->getSchemaManager()->listTableDetails('domains');

        self::assertEquals('domains', $table->getName());
    }
}
