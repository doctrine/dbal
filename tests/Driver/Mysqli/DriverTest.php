<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\Mysqli;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractMySQLDriverTestCase;

class DriverTest extends AbstractMySQLDriverTestCase
{
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
