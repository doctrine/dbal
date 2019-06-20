<?php

namespace Doctrine\Tests\DBAL\Driver\PDOIbm;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDOIbm\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractDB2DriverTest;

class DriverTest extends AbstractDB2DriverTest
{
    public function testReturnsName() : void
    {
        self::assertSame('pdo_ibm', $this->driver->getName());
    }

    protected function createDriver() : DriverInterface
    {
        return new Driver();
    }
}
