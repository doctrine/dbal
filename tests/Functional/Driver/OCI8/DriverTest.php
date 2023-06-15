<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\OCI8;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\OCI8\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;

/** @requires extension oci8 */
class DriverTest extends AbstractDriverTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('oci8')) {
            return;
        }

        self::markTestSkipped('This test requires the oci8 driver.');
    }

    public function testConnectsWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('Oracle does not support connecting without database name.');
    }

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('Oracle does not support connecting without database name.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
