<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\SQLAnywhere;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\SQLAnywhere\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;

use function extension_loaded;

class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        if (! extension_loaded('sqlanywhere')) {
            self::markTestSkipped('sqlanywhere is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        self::markTestSkipped('sqlanywhere only test.');
    }

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter(): void
    {
        $params = $this->connection->getParams();
        unset($params['dbname']);

        $connection = new Connection(
            $params,
            $this->connection->getDriver(),
            $this->connection->getConfiguration(),
            $this->connection->getEventManager()
        );

        // SQL Anywhere has no "default" database. The name of the default database
        // is defined on server startup and therefore can be arbitrary.
        self::assertIsString($connection->getDatabase());
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
