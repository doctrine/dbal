<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\OCI8;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\OCI8\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;
use function extension_loaded;

class DriverTest extends AbstractDriverTest
{
    protected function setUp() : void
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
    public function testConnectsWithoutDatabaseNameParameter() : void
    {
        $this->markTestSkipped('Oracle does not support connecting without database name.');
    }

    /**
     * {@inheritdoc}
     */
    public function testReturnsDatabaseNameWithoutDatabaseNameParameter() : void
    {
        $this->markTestSkipped('Oracle does not support connecting without database name.');
    }

    /**
     * {@inheritdoc}
     */
    protected function createDriver() : DriverInterface
    {
        return new Driver();
    }
}
