<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;
use function extension_loaded;

class DriverTest extends AbstractDriverTest
{
    protected function setUp()
    {
        if (! extension_loaded('mysqli')) {
            $this->markTestSkipped('mysqli is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('MySQLi only test.');
    }

    /**
     * {@inheritdoc}
     */
    protected function createDriver()
    {
        return new Driver();
    }
}
