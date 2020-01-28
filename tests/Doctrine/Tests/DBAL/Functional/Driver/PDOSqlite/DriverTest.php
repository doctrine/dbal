<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Driver\PDOSqlite;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDOSqlite\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;
use function extension_loaded;

class DriverTest extends AbstractDriverTest
{
    protected function setUp() : void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('pdo_sqlite only test.');
    }

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter() : void
    {
        $this->markTestSkipped('SQLite does not support the concept of a database.');
    }

    protected function createDriver() : DriverInterface
    {
        return new Driver();
    }

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter() : ?string
    {
        return '';
    }
}
