<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\PgSQL;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

/**
 * How to run this test with PostgreSQL (the original target of the issue):
 * 1) Start a PostgreSQLInstance
 * 2) If needed, change ci/github/phpunit/pdo_pgsql.xml according to your PostgreSQL local settings
 * 3) Run
 * vendor/bin/phpunit -c ci/github/phpunit/pdo_pgsql.xml tests/Functional/Driver/PDO/PgSQL/DBAL6024Test.php
 */

/** @requires extension pdo_pgsql */
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

        $diff = (new Comparator())->compareTables($table, $newTable);

        $statements = $this->connection->getDatabasePlatform()->getAlterTableSQL($diff);
        foreach ($statements as $statement) {
            $this->connection->executeStatement($statement);
        }

        $schemaManager = $this->connection->createSchemaManager();

        $validationSchema = $schemaManager->introspectSchema();
        $validationTable  = $validationSchema->getTable($table->getName());

        $this->assertNull($validationTable->getPrimaryKey());
    }
}
