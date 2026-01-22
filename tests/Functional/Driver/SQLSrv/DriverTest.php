<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\SQLSrv;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\SQLSrv\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Override;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('sqlsrv')]
class DriverTest extends AbstractDriverTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('sqlsrv')) {
            return;
        }

        self::markTestSkipped('This test requires the sqlsrv driver.');
    }

    #[Override]
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }

    #[Override]
    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter(): ?string
    {
        return 'master';
    }
}
