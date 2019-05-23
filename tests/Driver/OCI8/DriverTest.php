<?php

namespace Doctrine\DBAL\Tests\Driver\OCI8;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\OCI8\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractOracleDriverTest;

class DriverTest extends AbstractOracleDriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
