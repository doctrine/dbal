<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractSQLServerDriver;
use Doctrine\DBAL\Platforms\SQLServer2005Platform;
use Doctrine\DBAL\Platforms\SQLServer2008Platform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;

class AbstractSQLServerDriverTest extends AbstractDriverTest
{
    protected function createDriver()
    {
        return $this->getMockForAbstractClass(AbstractSQLServerDriver::class);
    }

    protected function createPlatform()
    {
        return new SQLServer2008Platform();
    }

    protected function createSchemaManager(Connection $connection)
    {
        return new SQLServerSchemaManager($connection);
    }

    protected function getDatabasePlatformsForVersions()
    {
        return [
            ['9', SQLServerPlatform::class],
            ['9.00', SQLServerPlatform::class],
            ['9.00.0', SQLServerPlatform::class],
            ['9.00.1398', SQLServerPlatform::class],
            ['9.00.1398.99', SQLServerPlatform::class],
            ['9.00.1399', SQLServer2005Platform::class],
            ['9.00.1399.0', SQLServer2005Platform::class],
            ['9.00.1399.99', SQLServer2005Platform::class],
            ['9.00.1400', SQLServer2005Platform::class],
            ['9.10', SQLServer2005Platform::class],
            ['9.10.9999', SQLServer2005Platform::class],
            ['10.00.1599', SQLServer2005Platform::class],
            ['10.00.1599.99', SQLServer2005Platform::class],
            ['10.00.1600', SQLServer2008Platform::class],
            ['10.00.1600.0', SQLServer2008Platform::class],
            ['10.00.1600.99', SQLServer2008Platform::class],
            ['10.00.1601', SQLServer2008Platform::class],
            ['10.10', SQLServer2008Platform::class],
            ['10.10.9999', SQLServer2008Platform::class],
            ['11.00.2099', SQLServer2008Platform::class],
            ['11.00.2099.99', SQLServer2008Platform::class],
            ['11.00.2100', SQLServer2012Platform::class],
            ['11.00.2100.0', SQLServer2012Platform::class],
            ['11.00.2100.99', SQLServer2012Platform::class],
            ['11.00.2101', SQLServer2012Platform::class],
            ['12', SQLServer2012Platform::class],
        ];
    }
}
