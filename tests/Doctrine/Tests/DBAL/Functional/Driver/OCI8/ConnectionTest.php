<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\Connection;
use Doctrine\DBAL\Driver\OCI8\Driver;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

/**
 * @requires extension oci8
 */
class ConnectionTest extends DbalFunctionalTestCase
{
    /** @var Connection */
    protected $driverConnection;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->connection->getDriver() instanceof Driver) {
            $this->markTestSkipped('oci8 only test.');
        }

        $this->driverConnection = $this->connection->getWrappedConnection();
    }

    public function testLastInsertIdAcceptsFqn(): void
    {
        $platform      = $this->connection->getDatabasePlatform();
        $schemaManager = $this->connection->getSchemaManager();

        $table = new Table('DBAL2595');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('foo', 'integer');

        $schemaManager->dropAndCreateTable($table);

        $this->connection->executeStatement('INSERT INTO DBAL2595 (foo) VALUES (1)');

        $schema   = $this->connection->getDatabase();
        $sequence = $platform->getIdentitySequenceName($schema . '.DBAL2595', 'id');

        self::assertSame(1, $this->driverConnection->lastInsertId($sequence));
    }
}
