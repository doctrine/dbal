<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;
use function extension_loaded;

class DriverTest extends AbstractDriverTest
{
    protected function setUp()
    {
        if (! extension_loaded('oci8')) {
            $this->markTestSkipped('oci8 is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('oci8 only test.');
    }

    /**
     * {@inheritdoc}
     */
    public function testConnectsWithoutDatabaseNameParameter()
    {
        $this->markTestSkipped('Oracle does not support connecting without database name.');
    }

    /**
     * {@inheritdoc}
     */
    public function testReturnsDatabaseNameWithoutDatabaseNameParameter()
    {
        $this->markTestSkipped('Oracle does not support connecting without database name.');
    }

    /**
     * {@inheritdoc}
     */
    protected function createDriver()
    {
        return new Driver();
    }
}
