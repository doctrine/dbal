<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\PDO\SQLite;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractSQLiteDriverTestCase;

class DriverTest extends AbstractSQLiteDriverTestCase
{
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
