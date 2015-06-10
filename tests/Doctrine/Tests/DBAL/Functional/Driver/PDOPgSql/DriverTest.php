<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\PDOPgSql;

use Doctrine\DBAL\Driver\PDOPgSql\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;

class DriverTest extends AbstractDriverTest
{
    protected function setUp()
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql is not installed.');
        }

        parent::setUp();

        if (! $this->_conn->getDriver() instanceof Driver) {
            $this->markTestSkipped('pdo_pgsql only test.');
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
        return 'template1';
    }
}
