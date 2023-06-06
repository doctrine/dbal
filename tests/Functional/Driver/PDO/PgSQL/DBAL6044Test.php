<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\PgSQL;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

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
        $this->assertEquals($unloggedTable->getName(), $validationTable->getName());

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
