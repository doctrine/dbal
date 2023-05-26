<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\PgSQL;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class DBAL6024Test extends FunctionalTestCase
{
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

        $this->assertNull($validationTable->getPrimaryKey());
    }
}
