<?php

namespace Doctrine\Tests\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\IBMDB2\DB2Driver;
use Doctrine\Tests\DBAL\Driver\AbstractDB2DriverTest;

class DB2DriverTest extends AbstractDB2DriverTest
{
    public function testReturnsName() : void
    {
        self::assertSame('ibm_db2', $this->driver->getName());
    }

    protected function createDriver() : DriverInterface
    {
        return new DB2Driver();
    }
}
