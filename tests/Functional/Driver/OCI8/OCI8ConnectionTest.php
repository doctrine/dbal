<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\Driver;
use Doctrine\DBAL\Driver\OCI8\OCI8Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function assert;

use function extension_loaded;

class OCI8ConnectionTest extends FunctionalTestCase
{
    /** @var OCI8Connection */
    protected $driverConnection;

    protected function setUp(): void
    {
        if (! extension_loaded('oci8')) {
            self::markTestSkipped('oci8 is not installed.');
        }

        parent::setUp();

        if (! $this->connection->getDriver() instanceof Driver) {
            self::markTestSkipped('oci8 only test.');
        }

        $wrappedConnection = $this->connection->getWrappedConnection();
        assert($wrappedConnection instanceof OCI8Connection);

        $this->driverConnection = $wrappedConnection;
    }

    /**
     * @group DBAL-2595
     */
    public function testLastInsertIdAcceptsFqn(): void
    {
        $platform      = $this->connection->getDatabasePlatform();
        $schemaManager = $this->connection->getSchemaManager();

        $table = new Table('DBAL2595');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('foo', 'integer');

        $schemaManager->dropAndCreateTable($table);

        $this->connection->executeUpdate('INSERT INTO DBAL2595 (foo) VALUES (1)');

        $schema   = $this->connection->getDatabase();
        $sequence = $platform->getIdentitySequenceName($schema . '.DBAL2595', 'id');

        self::assertEquals(1, $this->driverConnection->lastInsertId($sequence));
    }
}
