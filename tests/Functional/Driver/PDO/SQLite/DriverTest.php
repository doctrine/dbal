<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\SQLite;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;

use function array_merge;

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

        self::markTestSkipped('pdo_sqlite only test.');
    }

    public function testConnectReadOnly(): void
    {
        $conn = $this->driver->connect(array_merge(
            $this->connection->getParams(),
            ['driverOptions' => ['readOnly' => true]]
        ));

        $this->expectException(Exception::class);
        $this->expectDeprecationMessage('SQLSTATE[HY000]: General error: 8 attempt to write a readonly database');
        $conn->exec('CREATE TABLE foo (ID INT NOT NULL PRIMARY KEY);');
    }

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('SQLite does not support the concept of a database.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
