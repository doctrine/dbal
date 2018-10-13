<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\PDOOracle;

use Doctrine\DBAL\Driver\PDOOracle\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;
use function extension_loaded;

class DriverTest extends AbstractDriverTest
{
    protected function setUp()
    {
        if (! extension_loaded('PDO_OCI')) {
            $this->markTestSkipped('PDO_OCI is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('PDO_OCI only test.');
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
