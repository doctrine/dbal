<?php

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractDB2Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\IBMDB2\ExceptionConverter;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\DB2SchemaManager;

/**
 * @extends AbstractDriverTest<DB2Platform>
 */
class AbstractDB2DriverTest extends AbstractDriverTest
{
    protected function createDriver(): Driver
    {
        return $this->getMockForAbstractClass(AbstractDB2Driver::class);
    }

    protected function createPlatform(): AbstractPlatform
    {
        return new DB2Platform();
    }

    protected function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new DB2SchemaManager(
            $connection,
            $this->createPlatform()
        );
    }

    protected function createExceptionConverter(): ExceptionConverterInterface
    {
        return new ExceptionConverter();
    }
}
