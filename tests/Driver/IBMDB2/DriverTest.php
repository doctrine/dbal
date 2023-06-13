<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\IBMDB2;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\IBMDB2\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractDB2DriverTestCase;

class DriverTest extends AbstractDB2DriverTestCase
{
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
