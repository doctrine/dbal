<?php

namespace Doctrine\Tests\DBAL\Driver\PDOPgSql;

use Doctrine\DBAL\Driver\PDOPgSql\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractPostgreSQLDriverTest;

class DriverTest extends AbstractPostgreSQLDriverTest
{
    public function testReturnsName()
    {
        $this->assertSame('pdo_pgsql', $this->driver->getName());
    }

    protected function createDriver()
    {
        return new Driver();
    }
}
