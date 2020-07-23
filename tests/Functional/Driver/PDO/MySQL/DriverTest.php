<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\MySQL;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;

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

        self::markTestSkipped('pdo_mysql only test.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
