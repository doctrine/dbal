<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\IBMDB2;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\IBMDB2\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('ibm_db2')]
class DriverTest extends AbstractDriverTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('ibm_db2')) {
            return;
        }

        self::markTestSkipped('This test requires the ibm_db2 driver.');
    }

    public function testConnectsWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('IBM DB2 does not support connecting without database name.');
    }

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('IBM DB2 does not support connecting without database name.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
