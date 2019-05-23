<?php

namespace Doctrine\DBAL\Tests\Driver\PDOMySql;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDOMySql\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractMySQLDriverTest;

class DriverTest extends AbstractMySQLDriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
