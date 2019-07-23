<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\IBMDB2;

use Doctrine\DBAL\Driver\IBMDB2\DB2Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;

class DB2DriverTest extends AbstractDriverTest
{
    protected function setUp()
    {
        if (! extension_loaded('ibm_db2')) {
            $this->markTestSkipped('ibm_db2 is not installed.');
        }

        parent::setUp();

        if (! $this->_conn->getDriver() instanceof DB2Driver) {
            $this->markTestSkipped('ibm_db2 only test.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function testConnectsWithoutDatabaseNameParameter()
    {
        $this->markTestSkipped('IBM DB2 does not support connecting without database name.');
    }

    /**
     * {@inheritdoc}
     */
    public function testReturnsDatabaseNameWithoutDatabaseNameParameter()
    {
        $this->markTestSkipped('IBM DB2 does not support connecting without database name.');
    }

    /**
     * {@inheritdoc}
     */
    protected function createDriver()
    {
        return new DB2Driver();
    }
}
