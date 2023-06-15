<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

class DBAL6044Test extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_pgsql', 'pgsql')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_pgsql or the pgsql driver.');
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

        $validationSchema        = $schemaManager->introspectSchema();
        $validationUnloggedTable = $validationSchema->getTable($unloggedTable->getName());
        self::assertTrue($validationUnloggedTable->getOption('unlogged'));
        $validationLoggedTable = $validationSchema->getTable($loggedTable->getName());
        self::assertFalse($validationLoggedTable->getOption('unlogged'));

        $sql  = 'SELECT relpersistence FROM pg_class WHERE relname = ?';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, $unloggedTable->getName());
        $unloggedTablePersistenceType = $stmt->executeQuery()->fetchOne();
        self::assertEquals('u', $unloggedTablePersistenceType);

        $stmt->bindValue(1, $loggedTable->getName());
        $loggedTablePersistenceType = $stmt->executeQuery()->fetchOne();
        self::assertEquals('p', $loggedTablePersistenceType);
    }
}
