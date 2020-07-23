<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\SQLite;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;

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

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('SQLite does not support the concept of a database.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter(): ?string
    {
        return '';
    }
}
