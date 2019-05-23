<?php

namespace Doctrine\DBAL\Tests\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDOSqlsrv\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractSQLServerDriverTest;

class DriverTest extends AbstractSQLServerDriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
