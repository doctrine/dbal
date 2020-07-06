<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\PDOSqlite;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;

/**
 * @requires extension pdo_sqlite
 */
class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('pdo_sqlite only test.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
