<?php

namespace Doctrine\DBAL\Tests\Driver\PDO\IBMDB2;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\IBMDB2\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractDB2DriverTest;

class DriverTest extends AbstractDB2DriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
