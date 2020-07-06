<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\PDOMySql;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;

/**
 * @requires extension pdo_mysql
 */
class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('pdo_mysql only test.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
