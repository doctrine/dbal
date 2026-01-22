<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\OCI;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\OCI\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Override;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('pdo_oci')]
class DriverTest extends AbstractDriverTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_oci')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_oci driver.');
    }

    #[Override]
    public function testConnectsWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('Oracle does not support connecting without database name.');
    }

    #[Override]
    public function testReturnsDatabaseNameWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('Oracle does not support connecting without database name.');
    }

    #[Override]
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
