<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\Mysqli;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;

/**
 * @requires extension mysqli
 */
class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        self::markTestSkipped('MySQLi only test.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
