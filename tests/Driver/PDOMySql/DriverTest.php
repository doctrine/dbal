<?php

namespace Doctrine\DBAL\Tests\Driver\PDOMySql;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDOMySql\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractMySQLDriverTest;

class DriverTest extends AbstractMySQLDriverTest
{
    public function testReturnsName(): void
    {
        self::assertSame('pdo_mysql', $this->driver->getName());
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
