<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class DBAL510Test extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        self::markTestSkipped('PostgreSQL only test');
    }

    public function testSearchPathSchemaChanges(): void
    {
        $table = new Table('dbal510tbl');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $schemaManager = $this->connection->createSchemaManager();
        $onlineTable   = $schemaManager->introspectTable('dbal510tbl');

        self::assertTrue(
            $schemaManager->createComparator()
                ->compareTables($onlineTable, $table)
                ->isEmpty(),
        );
    }
}
