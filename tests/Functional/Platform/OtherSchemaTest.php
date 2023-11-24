<?php

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class OtherSchemaTest extends FunctionalTestCase
{
    public function testATableCanBeCreatedInAnotherSchema(): void
    {
        $databasePlatform = $this->connection->getDatabasePlatform();
        if (! ($databasePlatform instanceof SqlitePlatform)) {
            self::markTestSkipped('This test requires SQLite');
        }

        $this->connection->executeStatement("ATTACH DATABASE '/tmp/test_other_schema.sqlite' AS other");
        $databasePlatform->disableSchemaEmulation();

        $table = new Table('other.test_other_schema');
        $table->addColumn('id', Types::INTEGER);
        $table->addIndex(['id']);

        $this->dropAndCreateTable($table);
        $this->connection->insert('other.test_other_schema', ['id' => 1]);

        self::assertEquals(1, $this->connection->fetchOne('SELECT COUNT(*) FROM other.test_other_schema'));
        $connection  = DriverManager::getConnection(
            ['url' => 'sqlite:////tmp/test_other_schema.sqlite'],
        );
        $onlineTable = $connection->createSchemaManager()->introspectTable('test_other_schema');
        self::assertCount(1, $onlineTable->getIndexes());
    }
}
