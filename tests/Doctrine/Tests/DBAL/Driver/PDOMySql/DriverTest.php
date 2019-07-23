<?php

namespace Doctrine\Tests\DBAL\Driver\PDOMySql;

use Doctrine\DBAL\Driver\PDOMySql\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractMySQLDriverTest;

class DriverTest extends AbstractMySQLDriverTest
{
    public function testReturnsName()
    {
        self::assertSame('pdo_mysql', $this->driver->getName());
    }

    protected function createDriver()
    {
        return new Driver();
    }
}
