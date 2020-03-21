<?php

namespace Doctrine\DBAL\Tests\Driver\SQLSrv;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\SQLSrv\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractSQLServerDriverTest;

class DriverTest extends AbstractSQLServerDriverTest
{
    public function testReturnsName() : void
    {
        self::assertSame('sqlsrv', $this->driver->getName());
    }

    protected function createDriver() : DriverInterface
    {
        return new Driver();
    }
}
