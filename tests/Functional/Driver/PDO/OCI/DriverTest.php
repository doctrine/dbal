<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\OCI;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\OCI\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;

/**
 * @requires extension pdo_oci
 */
class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        self::markTestSkipped('PDO_OCI only test.');
    }

    public function testConnectsWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('Oracle does not support connecting without database name.');
    }

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('Oracle does not support connecting without database name.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
