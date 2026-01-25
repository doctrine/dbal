<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\IBMDB2;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\IBMDB2\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Override;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('ibm_db2')]
class DriverTest extends AbstractDriverTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('ibm_db2')) {
            return;
        }

        self::markTestSkipped('This test requires the ibm_db2 driver.');
    }

    #[Override]
    public function testConnectsWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('Db2 does not support connecting without database name.');
    }

    #[Override]
    public function testReturnsDatabaseNameWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('Db2 does not support connecting without database name.');
    }

    #[Override]
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
