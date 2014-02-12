<?php

namespace Doctrine\Tests\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\SQLSrv\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractSQLServerDriverTest;

class DriverTest extends AbstractSQLServerDriverTest
{
    public function testReturnsName()
    {
        $this->assertSame('sqlsrv', $this->driver->getName());
    }

    protected function createDriver()
    {
        return new Driver();
    }
}
