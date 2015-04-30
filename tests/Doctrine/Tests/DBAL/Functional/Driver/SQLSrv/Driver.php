<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\SQLSrv;

use Doctrine\DBAL\Driver\SQLSrv\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;

class DriverTest extends AbstractDriverTest
{
    protected function setUp()
    {
        if (! extension_loaded('sqlsrv')) {
            $this->markTestSkipped('sqlsrv is not installed.');
        }

        parent::setUp();

        if (! $this->_conn->getDriver() instanceof Driver) {
            $this->markTestSkipped('sqlsrv only test.');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function createDriver()
    {
        return new Driver();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDatabaseNameForConnectionWithoutDatabaseNameParameter()
    {
        return 'master';
    }
}
