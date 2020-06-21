<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractSQLServerDriver\PortWithoutHost;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;

abstract class AbstractSQLServerDriverTest extends AbstractDriverTest
{
    protected function createPlatform(): AbstractPlatform
    {
        return new SQLServer2012Platform();
    }

    protected function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new SQLServerSchemaManager($connection);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDatabasePlatformsForVersions(): array
    {
        return [
            ['12', SQLServer2012Platform::class],
        ];
    }

    public function testPortWithoutHost(): void
    {
        $this->expectException(PortWithoutHost::class);
        $this->driver->connect(['port' => 1433]);
    }
}
