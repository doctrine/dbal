<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

/**
 * @group DBAL-168
 */
class DBAL168Test extends DbalFunctionalTestCase
{
    public function testDomainsTable()
    {
        if ($this->_conn->getDatabasePlatform()->getName() !== 'postgresql') {
            $this->markTestSkipped('PostgreSQL only test');
        }

        $table = new Table('domains');
        $table->addColumn('id', 'integer');
        $table->addColumn('parent_id', 'integer');
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint('domains', ['parent_id'], ['id']);

        $this->_conn->getSchemaManager()->createTable($table);
        $table = $this->_conn->getSchemaManager()->listTableDetails('domains');

        self::assertEquals('domains', $table->getName());
    }
}
