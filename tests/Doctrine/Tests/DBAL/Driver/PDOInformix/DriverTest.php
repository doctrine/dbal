<?php

namespace Doctrine\Tests\DBAL\Driver\PDOInformix;

use Doctrine\DBAL\Driver\PDOInformix\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractInformixDriverTest;

class DriverTest extends AbstractInformixDriverTest
{
    public function testReturnsName()
    {
        $this->assertSame('pdo_informix', $this->driver->getName());
    }

    protected function createDriver()
    {
        return new Driver();
    }
}
