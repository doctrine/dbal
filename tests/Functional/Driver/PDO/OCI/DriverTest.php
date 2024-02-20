<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\OCI;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\OCI\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('pdo_oci')]
class DriverTest extends AbstractDriverTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_oci')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_oci driver.');
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
