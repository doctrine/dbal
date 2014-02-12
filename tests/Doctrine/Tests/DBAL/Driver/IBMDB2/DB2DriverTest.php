<?php

namespace Doctrine\Tests\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\IBMDB2\DB2Driver;
use Doctrine\Tests\DBAL\Driver\AbstractDB2DriverTest;

class DriverTest extends AbstractDB2DriverTest
{
    public function testReturnsName()
    {
        $this->assertSame('ibm_db2', $this->driver->getName());
    }

    protected function createDriver()
    {
        return new DB2Driver();
    }
}
