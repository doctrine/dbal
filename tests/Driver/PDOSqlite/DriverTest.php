<?php

namespace Doctrine\DBAL\Tests\Driver\PDOSqlite;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDOSqlite\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractSQLiteDriverTest;

class DriverTest extends AbstractSQLiteDriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
