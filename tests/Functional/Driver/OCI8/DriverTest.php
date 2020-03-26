<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\OCI8;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\OCI8\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;
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

    public function testConnectsWithoutDatabaseNameParameter() : void
    {
        $this->markTestSkipped('Oracle does not support connecting without database name.');
    }

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter() : void
    {
        $this->markTestSkipped('Oracle does not support connecting without database name.');
    }

    protected function createDriver() : DriverInterface
    {
        return new Driver();
    }
}
