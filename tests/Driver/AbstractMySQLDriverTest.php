<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\MySqlSchemaManager;

class AbstractMySQLDriverTest extends AbstractDriverTest
{
    protected function createDriver(): Driver
    {
        return $this->getMockForAbstractClass(AbstractMySQLDriver::class);
    }

    protected function createPlatform(): AbstractPlatform
    {
        return new MySqlPlatform();
    }

    protected function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new MySqlSchemaManager($connection);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDatabasePlatformsForVersions(): array
    {
        return [
            ['5.6.9', MySqlPlatform::class],
            ['5.7', MySQL57Platform::class],
            ['5.7.0', MySqlPlatform::class],
            ['5.7.8', MySqlPlatform::class],
            ['5.7.9', MySQL57Platform::class],
            ['5.7.10', MySQL57Platform::class],
            ['8', MySQL80Platform::class],
            ['8.0', MySQL80Platform::class],
            ['8.0.11', MySQL80Platform::class],
            ['6', MySQL57Platform::class],
            ['10.0.15-MariaDB-1~wheezy', MySqlPlatform::class],
            ['5.5.5-10.1.25-MariaDB', MySqlPlatform::class],
            ['10.1.2a-MariaDB-a1~lenny-log', MySqlPlatform::class],
            ['5.5.40-MariaDB-1~wheezy', MySqlPlatform::class],
            ['5.5.5-MariaDB-10.2.8+maria~xenial-log', MariaDb1027Platform::class],
            ['10.2.8-MariaDB-10.2.8+maria~xenial-log', MariaDb1027Platform::class],
            ['10.2.8-MariaDB-1~lenny-log', MariaDb1027Platform::class],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected static function getExceptionConversionData(): array
    {
        return [
            self::EXCEPTION_CONNECTION => [
                [1044],
                [1045],
                [1046],
                [1049],
                [1095],
                [1142],
                [1143],
                [1227],
                [1370],
                [2002],
                [2005],
            ],
            self::EXCEPTION_FOREIGN_KEY_CONSTRAINT_VIOLATION => [
                [1216],
                [1217],
                [1451],
                [1452],
            ],
            self::EXCEPTION_INVALID_FIELD_NAME => [
                [1054],
                [1166],
                [1611],
            ],
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => [
                [1052],
                [1060],
                [1110],
            ],
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => [
                [1048],
                [1121],
                [1138],
                [1171],
                [1252],
                [1263],
                [1364],
                [1566],
            ],
            self::EXCEPTION_SYNTAX_ERROR => [
                [1064],
                [1149],
                [1287],
                [1341],
                [1342],
                [1343],
                [1344],
                [1382],
                [1479],
                [1541],
                [1554],
                [1626],
            ],
            self::EXCEPTION_TABLE_EXISTS => [
                [1050],
            ],
            self::EXCEPTION_TABLE_NOT_FOUND => [
                [1051],
                [1146],
            ],
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => [
                [1062],
                [1557],
                [1569],
                [1586],
            ],
            self::EXCEPTION_DEADLOCK => [
                [1213],
            ],
            self::EXCEPTION_LOCK_WAIT_TIMEOUT => [
                [1205],
            ],
        ];
    }
}
