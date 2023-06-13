<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\PDO\OCI;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\OCI\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractOracleDriverTestCase;

class DriverTest extends AbstractOracleDriverTestCase
{
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
