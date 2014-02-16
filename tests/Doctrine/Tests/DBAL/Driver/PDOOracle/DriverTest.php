<?php

namespace Doctrine\Tests\DBAL\Driver\PDOOracle;

use Doctrine\DBAL\Driver\PDOOracle\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractOracleDriverTest;

class DriverTest extends AbstractOracleDriverTest
{
    public function testReturnsName()
    {
        $this->assertSame('pdo_oracle', $this->driver->getName());
    }

    protected function createDriver()
    {
        return new Driver();
    }
}
