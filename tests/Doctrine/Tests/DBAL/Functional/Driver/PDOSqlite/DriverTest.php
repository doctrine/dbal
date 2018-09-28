<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\PDOSqlite;

use Doctrine\DBAL\Driver\PDOSqlite\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;
use function extension_loaded;

class DriverTest extends AbstractDriverTest
{
    protected function setUp()
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('pdo_sqlite only test.');
    }

    /**
     * {@inheritdoc}
     */
    protected function createDriver()
    {
        return new Driver();
    }
}
