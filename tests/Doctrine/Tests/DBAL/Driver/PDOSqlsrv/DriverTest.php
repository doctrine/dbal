<?php

namespace Doctrine\Tests\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDOSqlsrv\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractSQLServerDriverTest;

class DriverTest extends AbstractSQLServerDriverTest
{
    public function testReturnsName()
    {
        $this->assertSame('pdo_sqlsrv', $this->driver->getName());
    }

    protected function createDriver()
    {
        return new Driver();
    }
}
