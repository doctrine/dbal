<?php

namespace Doctrine\DBAL\Tests\Driver\IBMDB2;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\IBMDB2\DB2Driver;
use Doctrine\DBAL\Tests\Driver\AbstractDB2DriverTest;

class DB2DriverTest extends AbstractDB2DriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new DB2Driver();
    }
}
