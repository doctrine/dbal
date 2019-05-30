<?php

namespace Doctrine\Tests\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractMySQLDriverTest;

class DriverTest extends AbstractMySQLDriverTest
{
    public function testReturnsName() : void
    {
        self::assertSame('mysqli', $this->driver->getName());
    }

    protected function createDriver() : DriverInterface
    {
        return new Driver();
    }
}
