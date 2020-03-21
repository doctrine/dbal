<?php

namespace Doctrine\Tests\DBAL\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\SQLAnywhere\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractSQLAnywhereDriverTest;

class DriverTest extends AbstractSQLAnywhereDriverTest
{
    public function testReturnsName() : void
    {
        self::assertSame('sqlanywhere', $this->driver->getName());
    }

    protected function createDriver() : DriverInterface
    {
        return new Driver();
    }
}
