<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\SQLAnywhere;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\SQLAnywhere\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;

class DriverTest extends AbstractDriverTest
{
    protected function setUp()
    {
        if (! extension_loaded('sqlanywhere')) {
            $this->markTestSkipped('sqlanywhere is not installed.');
        }

        parent::setUp();

        if (! $this->conn->getDriver() instanceof Driver) {
            $this->markTestSkipped('sqlanywhere only test.');
        }
    }

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter()
    {
        $params = $this->conn->getParams();
        unset($params['dbname']);

        $connection = new Connection(
            $params,
            $this->conn->getDriver(),
            $this->conn->getConfiguration(),
            $this->conn->getEventManager()
        );

        // SQL Anywhere has no "default" database. The name of the default database
        // is defined on server startup and therefore can be arbitrary.
        self::assertInternalType('string', $this->driver->getDatabase($connection));
    }

    /**
     * {@inheritdoc}
     */
    protected function createDriver()
    {
        return new Driver();
    }
}
