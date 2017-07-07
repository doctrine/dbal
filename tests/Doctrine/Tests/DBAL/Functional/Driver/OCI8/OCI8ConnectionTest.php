<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\Driver;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

class OCI8ConnectionTest extends DbalFunctionalTestCase
{
    /**
     * @var \Doctrine\DBAL\Driver\OCI8\OCI8Connection
     */
    protected $driverConnection;

    protected function setUp()
    {
        if (! extension_loaded('oci8')) {
            $this->markTestSkipped('oci8 is not installed.');
        }

        parent::setUp();

        if (! $this->_conn->getDriver() instanceof Driver) {
            $this->markTestSkipped('oci8 only test.');
        }

        $this->driverConnection = $this->_conn->getWrappedConnection();
    }

    /**
     * @group DBAL-2595
     */
    public function testLastInsertIdAcceptsFqn()
    {
        $platform = $this->_conn->getDatabasePlatform();
        $schemaManager = $this->_conn->getSchemaManager();

        $table = new Table('DBAL2595');
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('foo', 'integer');

        $schemaManager->dropAndCreateTable($table);

        $this->_conn->executeUpdate('INSERT INTO DBAL2595 (foo) VALUES (1)');

        $schema = $this->_conn->getDatabase();
        $sequence = $platform->getIdentitySequenceName($schema . '.DBAL2595', 'id');

        $this->assertSame(1, $this->driverConnection->lastInsertId($sequence));
    }
}
