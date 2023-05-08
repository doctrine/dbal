<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class DBAL168Test extends FunctionalTestCase
{
    public function testDomainsTable(): void
    {
        $table = new Table('domains');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('parent_id', Types::INTEGER);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint('domains', ['parent_id'], ['id']);

        $this->connection->createSchemaManager()->createTable($table);
        $table = $this->connection->createSchemaManager()->introspectTable('domains');

        self::assertEquals('domains', $table->getName());
    }
}
