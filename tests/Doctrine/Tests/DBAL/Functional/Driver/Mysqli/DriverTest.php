<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\Mysqli;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;

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

        $this->markTestSkipped('MySQLi only test.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
