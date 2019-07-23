<?php

namespace Doctrine\Tests\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractOracleDriverTest;

class DriverTest extends AbstractOracleDriverTest
{
    public function testReturnsName()
    {
        self::assertSame('oci8', $this->driver->getName());
    }

    protected function createDriver()
    {
        return new Driver();
    }
}
