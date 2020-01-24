<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\SQLSrv;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\SQLSrv\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;
use function extension_loaded;

class DriverTest extends AbstractDriverTest
{
    protected function setUp() : void
    {
        if (! extension_loaded('sqlsrv')) {
            $this->markTestSkipped('sqlsrv is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('sqlsrv only test.');
    }

    /**
     * {@inheritdoc}
     */
    protected function createDriver() : DriverInterface
    {
        return new Driver();
    }

    /**
     * {@inheritdoc}
     */
    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter() : ?string
    {
        return 'master';
    }
}
