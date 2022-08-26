<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\MySQL;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;
use Doctrine\DBAL\Tests\TestUtil;

/** @requires extension pdo_mysql */
class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_mysql')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_mysql driver.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
