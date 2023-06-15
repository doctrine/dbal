<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

class DBAL6024Test extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('pdo_pgsql', 'pgsql')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_pgsql or the pgsql driver.');
    }

    public function testDropPrimaryKey(): void
    {
        $table = new Table('mytable');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $newTable = clone $table;
        $newTable->dropPrimaryKey();

        $schemaManager = $this->connection->createSchemaManager();
        $diff          = $schemaManager->createComparator()->compareTables($table, $newTable);

        $statements = $this->connection->getDatabasePlatform()->getAlterTableSQL($diff);
        foreach ($statements as $statement) {
            $this->connection->executeStatement($statement);
        }

        $validationSchema = $schemaManager->introspectSchema();
        $validationTable  = $validationSchema->getTable($table->getName());

        self::assertNull($validationTable->getPrimaryKey());
    }
}
