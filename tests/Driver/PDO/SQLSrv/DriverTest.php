<?php

namespace Doctrine\DBAL\Tests\Driver\PDO\SQLSrv;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\SQLSrv\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractSQLServerDriverTest;

class DriverTest extends AbstractSQLServerDriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
