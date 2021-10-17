<?php

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\API\MySQL;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\MySQLSchemaManager;

/**
 * @extends AbstractDriverTest<MySQLPlatform>
 */
class AbstractMySQLDriverTest extends AbstractDriverTest
{
    protected function createDriver(): Driver
    {
        return $this->getMockForAbstractClass(AbstractMySQLDriver::class);
    }

    protected function createPlatform(): AbstractPlatform
    {
        return new MySQLPlatform();
    }

    protected function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new MySQLSchemaManager(
            $connection,
            $this->createPlatform()
        );
    }

    protected function createExceptionConverter(): ExceptionConverter
    {
        return new MySQL\ExceptionConverter();
    }

    /**
     * {@inheritDoc}
     */
    protected function getDatabasePlatformsForVersions(): array
    {
        return [
            ['5.6.9', MySQLPlatform::class],
            ['5.7', MySQL57Platform::class],
            ['5.7.0', MySQLPlatform::class],
            ['5.7.8', MySQLPlatform::class],
            ['5.7.9', MySQL57Platform::class],
            ['5.7.10', MySQL57Platform::class],
            ['8', MySQL80Platform::class],
            ['8.0', MySQL80Platform::class],
            ['8.0.11', MySQL80Platform::class],
            ['6', MySQL57Platform::class],
            ['10.0.15-MariaDB-1~wheezy', MySQLPlatform::class],
            ['5.5.5-10.1.25-MariaDB', MySQLPlatform::class],
            ['10.1.2a-MariaDB-a1~lenny-log', MySQLPlatform::class],
            ['5.5.40-MariaDB-1~wheezy', MySQLPlatform::class],
            ['5.5.5-MariaDB-10.2.8+maria~xenial-log', MariaDb1027Platform::class],
            ['10.2.8-MariaDB-10.2.8+maria~xenial-log', MariaDb1027Platform::class],
            ['10.2.8-MariaDB-1~lenny-log', MariaDb1027Platform::class],
        ];
    }
}
