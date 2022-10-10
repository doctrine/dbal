<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\SQLite;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;
use Doctrine\DBAL\Tests\TestUtil;

/** @requires extension pdo_sqlite */
class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_sqlite')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_sqlite driver.');
    }

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter(): ?string
    {
        return 'main';
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
