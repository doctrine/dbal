<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\API\MySQL;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\MySQLSchemaManager;

/** @extends AbstractDriverTest<MySQLPlatform> */
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
            $this->createPlatform(),
        );
    }

    protected function createExceptionConverter(): ExceptionConverter
    {
        return new MySQL\ExceptionConverter();
    }

    /**
     * {@inheritDoc}
     */
    public static function platformVersionProvider(): array
    {
        return [
            ['5.7', MySQLPlatform::class],
            ['8', MySQL80Platform::class],
            ['8.0', MySQL80Platform::class],
            ['8.0.11', MySQL80Platform::class],
            ['5.5.40-MariaDB-1~wheezy', MariaDBPlatform::class],
            ['5.5.5-MariaDB-10.2.8+maria~xenial-log', MariaDBPlatform::class],
            ['10.2.8-MariaDB-10.2.8+maria~xenial-log', MariaDBPlatform::class],
            ['10.2.8-MariaDB-1~lenny-log', MariaDBPlatform::class],
        ];
    }
}
