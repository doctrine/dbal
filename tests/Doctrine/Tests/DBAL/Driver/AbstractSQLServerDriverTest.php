<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLServer2008Platform;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;

class AbstractSQLServerDriverTest extends AbstractDriverTest
{
    protected function createDriver()
    {
        return $this->getMockForAbstractClass('Doctrine\DBAL\Driver\AbstractSQLServerDriver');
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
            ['9', 'Doctrine\DBAL\Platforms\SQLServerPlatform'],
            ['9.00', 'Doctrine\DBAL\Platforms\SQLServerPlatform'],
            ['9.00.0', 'Doctrine\DBAL\Platforms\SQLServerPlatform'],
            ['9.00.1398', 'Doctrine\DBAL\Platforms\SQLServerPlatform'],
            ['9.00.1398.99', 'Doctrine\DBAL\Platforms\SQLServerPlatform'],
            ['9.00.1399', 'Doctrine\DBAL\Platforms\SQLServer2005Platform'],
            ['9.00.1399.0', 'Doctrine\DBAL\Platforms\SQLServer2005Platform'],
            ['9.00.1399.99', 'Doctrine\DBAL\Platforms\SQLServer2005Platform'],
            ['9.00.1400', 'Doctrine\DBAL\Platforms\SQLServer2005Platform'],
            ['9.10', 'Doctrine\DBAL\Platforms\SQLServer2005Platform'],
            ['9.10.9999', 'Doctrine\DBAL\Platforms\SQLServer2005Platform'],
            ['10.00.1599', 'Doctrine\DBAL\Platforms\SQLServer2005Platform'],
            ['10.00.1599.99', 'Doctrine\DBAL\Platforms\SQLServer2005Platform'],
            ['10.00.1600', 'Doctrine\DBAL\Platforms\SQLServer2008Platform'],
            ['10.00.1600.0', 'Doctrine\DBAL\Platforms\SQLServer2008Platform'],
            ['10.00.1600.99', 'Doctrine\DBAL\Platforms\SQLServer2008Platform'],
            ['10.00.1601', 'Doctrine\DBAL\Platforms\SQLServer2008Platform'],
            ['10.10', 'Doctrine\DBAL\Platforms\SQLServer2008Platform'],
            ['10.10.9999', 'Doctrine\DBAL\Platforms\SQLServer2008Platform'],
            ['11.00.2099', 'Doctrine\DBAL\Platforms\SQLServer2008Platform'],
            ['11.00.2099.99', 'Doctrine\DBAL\Platforms\SQLServer2008Platform'],
            ['11.00.2100', 'Doctrine\DBAL\Platforms\SQLServer2012Platform'],
            ['11.00.2100.0', 'Doctrine\DBAL\Platforms\SQLServer2012Platform'],
            ['11.00.2100.99', 'Doctrine\DBAL\Platforms\SQLServer2012Platform'],
            ['11.00.2101', 'Doctrine\DBAL\Platforms\SQLServer2012Platform'],
            ['12', 'Doctrine\DBAL\Platforms\SQLServer2012Platform'],
        ];
    }
}
