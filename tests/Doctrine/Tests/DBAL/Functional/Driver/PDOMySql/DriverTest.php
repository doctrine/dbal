<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\PDOMySql;

use Doctrine\DBAL\Driver\PDOMySql\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;

class DriverTest extends AbstractDriverTest
{
    protected function setUp()
    {
        if (! extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('pdo_mysql is not installed.');
        }

        parent::setUp();

        if (! $this->_conn->getDriver() instanceof Driver) {
            $this->markTestSkipped('pdo_mysql only test.');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function createDriver()
    {
        return new Driver();
    }
}
