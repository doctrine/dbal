<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\PDO\MySQL;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractMySQLDriverTestCase;

class DriverTest extends AbstractMySQLDriverTestCase
{
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
