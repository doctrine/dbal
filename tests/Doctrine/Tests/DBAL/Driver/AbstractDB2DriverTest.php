<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Schema\DB2SchemaManager;

class AbstractDB2DriverTest extends AbstractDriverTest
{
    protected function createDriver()
    {
        return $this->getMockForAbstractClass('Doctrine\DBAL\Driver\AbstractDB2Driver');
    }

    protected function createPlatform()
    {
        return new DB2Platform();
    }

    protected function createSchemaManager(Connection $connection)
    {
        return new DB2SchemaManager($connection);
    }
}
