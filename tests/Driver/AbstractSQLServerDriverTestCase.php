<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Driver\AbstractSQLServerDriver\Exception\PortWithoutHost;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

/** @extends AbstractDriverTestCase<SQLServerPlatform> */
abstract class AbstractSQLServerDriverTestCase extends AbstractDriverTestCase
{
    public function testPortWithoutHost(): void
    {
        $this->expectException(PortWithoutHost::class);
        $this->driver->connect(['port' => 1433]);
    }

    public function testThrowsExceptionOnCreatingDatabasePlatformsForInvalidVersion(): void
    {
        self::markTestSkipped('SQL Server drivers do not use server version to instantiate platform');
    }
}
