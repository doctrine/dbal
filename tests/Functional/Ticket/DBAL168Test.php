<?php

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class DBAL168Test extends FunctionalTestCase
{
    public function testDomainsTable(): void
    {
        $table = new Table('domains');
        $table->addColumn('id', 'integer');
        $table->addColumn('parent_id', 'integer');
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint('domains', ['parent_id'], ['id']);

        $this->connection->getSchemaManager()->createTable($table);
        $table = $this->connection->getSchemaManager()->introspectTable('domains');

        self::assertEquals('domains', $table->getName());
    }
}
