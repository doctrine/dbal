<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

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
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->dropAndCreateTable($table);

        $onlineTable = $schemaManager->listTableDetails('dbal510tbl');

        $diff = $schemaManager->createComparator()
            ->diffTable($onlineTable, $table);

        self::assertNull($diff);
    }
}
