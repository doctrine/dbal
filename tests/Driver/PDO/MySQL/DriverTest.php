<?php

namespace Doctrine\DBAL\Tests\Driver\PDO\MySQL;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractMySQLDriverTest;

class DriverTest extends AbstractMySQLDriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
