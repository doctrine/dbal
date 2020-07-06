<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\IBMDB2;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\IBMDB2\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;

/**
 * @requires extension ibm_db2
 */
class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('ibm_db2 only test.');
    }

    public function testConnectsWithoutDatabaseNameParameter(): void
    {
        $this->markTestSkipped('IBM DB2 does not support connecting without database name.');
    }

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter(): void
    {
        $this->markTestSkipped('IBM DB2 does not support connecting without database name.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
