<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\IBMDB2;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\IBMDB2\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;
use Doctrine\DBAL\Tests\TestUtil;

/** @requires extension pdo_ibm */
class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_ibm')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_ibm driver.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
