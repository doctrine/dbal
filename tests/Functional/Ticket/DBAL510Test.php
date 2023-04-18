<?php

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
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

    /**
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     *
     * @dataProvider \Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils::comparatorProvider
     */
    public function testSearchPathSchemaChanges(callable $comparatorFactory): void
    {
        $table = new Table('dbal510tbl');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $schemaManager = $this->connection->createSchemaManager();
        $onlineTable   = $schemaManager->introspectTable('dbal510tbl');

        $comparator = $comparatorFactory($schemaManager);
        $diff       = $comparator->diffTable($onlineTable, $table);

        self::assertFalse($diff);
    }
}
