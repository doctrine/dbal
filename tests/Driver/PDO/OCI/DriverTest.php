<?php

namespace Doctrine\DBAL\Tests\Driver\PDO\OCI;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\OCI\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractOracleDriverTest;

class DriverTest extends AbstractOracleDriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
