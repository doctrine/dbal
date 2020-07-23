<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\SQLSrv;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\SQLSrv\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;

/**
 * @requires extension sqlsrv
 */
class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        self::markTestSkipped('sqlsrv only test.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter(): ?string
    {
        return 'master';
    }
}
