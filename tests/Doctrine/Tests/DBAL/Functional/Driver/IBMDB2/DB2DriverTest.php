<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\IBMDB2;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\IBMDB2\DB2Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;
use function extension_loaded;

class DB2DriverTest extends AbstractDriverTest
{
    protected function setUp() : void
    {
        if (! extension_loaded('ibm_db2')) {
            $this->markTestSkipped('ibm_db2 is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof DB2Driver) {
            return;
        }

        $this->markTestSkipped('ibm_db2 only test.');
    }

    /**
     * {@inheritdoc}
     */
    public function testConnectsWithoutDatabaseNameParameter() : void
    {
        $this->markTestSkipped('IBM DB2 does not support connecting without database name.');
    }

    /**
     * {@inheritdoc}
     */
    public function testReturnsDatabaseNameWithoutDatabaseNameParameter() : void
    {
        $this->markTestSkipped('IBM DB2 does not support connecting without database name.');
    }

    /**
     * {@inheritdoc}
     */
    protected function createDriver() : Driver
    {
        return new DB2Driver();
    }
}
