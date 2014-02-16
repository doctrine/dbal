<?php

namespace Doctrine\Tests\DBAL\Driver\PDOIbm;

use Doctrine\DBAL\Driver\PDOIbm\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractDB2DriverTest;

class DriverTest extends AbstractDB2DriverTest
{
    public function testReturnsName()
    {
        $this->assertSame('pdo_ibm', $this->driver->getName());
    }

    protected function createDriver()
    {
        return new Driver();
    }
}
