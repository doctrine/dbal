<?php

namespace Doctrine\Tests\DBAL\Driver\SQLite3;

use Doctrine\DBAL\Driver\SQLite3\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractSQLiteDriverTest;

class DriverTest extends AbstractSQLiteDriverTest
{
    public function testReturnsName()
    {
        $this->assertSame('sqlite3', $this->driver->getName());
    }

    protected function createDriver()
    {
        return new Driver();
    }
}
