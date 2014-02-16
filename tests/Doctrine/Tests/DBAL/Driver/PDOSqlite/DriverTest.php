<?php

namespace Doctrine\Tests\DBAL\Driver\PDOSqlite;

use Doctrine\DBAL\Driver\PDOSqlite\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractSQLiteDriverTest;

class DriverTest extends AbstractSQLiteDriverTest
{
    public function testReturnsName()
    {
        $this->assertSame('pdo_sqlite', $this->driver->getName());
    }

    protected function createDriver()
    {
        return new Driver();
    }
}
