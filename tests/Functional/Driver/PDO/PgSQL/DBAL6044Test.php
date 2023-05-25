<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\PgSQL;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

/**
 * How to run this test with PostgreSQL:
 * 1) Start a PostgreSQLInstance
 * 2) If needed, change ci/github/phpunit/pdo_pgsql.xml according to your PostgreSQL local settings
 * 3) Run
 * vendor/bin/phpunit -c ci/github/phpunit/pdo_pgsql.xml tests/Functional/Driver/PDO/PgSQL/DBAL6044Test.php
 */

/** @requires extension pdo_pgsql */
class DBAL6044Test extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_pgsql')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_pgsql driver.');
    }

    public function testUnloggedTables(): void
    {
        $unloggedTable = new Table('my_unlogged');
        $unloggedTable->addOption('unlogged', true);
        $unloggedTable->addColumn('foo', 'string');
        $this->dropAndCreateTable($unloggedTable);

        $loggedTable = new Table('my_logged');
        $loggedTable->addColumn('foo', 'string');
        $this->dropAndCreateTable($loggedTable);

        $schemaManager = $this->connection->createSchemaManager();

        $validationSchema = $schemaManager->introspectSchema();
        $validationTable  = $validationSchema->getTable($unloggedTable->getName());
        $this->assertNotNull($validationTable);

        $sql  = 'SELECT relpersistence FROM pg_class WHERE relname = ?';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, $unloggedTable->getName());
        $unloggedTablePersistenceType = $stmt->executeQuery()->fetchOne();
        $this->assertEquals('u', $unloggedTablePersistenceType);

        $stmt->bindValue(1, $loggedTable->getName());
        $loggedTablePersistenceType = $stmt->executeQuery()->fetchOne();
        $this->assertEquals('p', $loggedTablePersistenceType);
    }
}
