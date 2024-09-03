<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\PDO\SQLSrv;

use Doctrine\DBAL\Driver\PDO\Exception\InvalidConfiguration;
use Doctrine\DBAL\Driver\PDO\SQLSrv\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractSQLServerDriverTestCase;

class DriverTest extends AbstractSQLServerDriverTestCase
{
    public function testUserIsFalse(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage(
            'The user configuration parameter is expected to be either a string or null, got bool.',
        );
        $this->driver->connect(['user' => false]);
    }

    public function testPasswordIsFalse(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage(
            'The password configuration parameter is expected to be either a string or null, got bool.',
        );
        $this->driver->connect(['password' => false]);
    }

    protected function createDriver(): Driver
    {
        return new Driver();
    }
}
