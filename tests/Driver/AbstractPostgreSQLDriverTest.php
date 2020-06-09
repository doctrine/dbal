<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractPostgreSQLDriver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\PostgreSqlSchemaManager;

class AbstractPostgreSQLDriverTest extends AbstractDriverTest
{
    protected function createDriver(): Driver
    {
        return $this->getMockForAbstractClass(AbstractPostgreSQLDriver::class);
    }

    protected function createPlatform(): AbstractPlatform
    {
        return new PostgreSQL94Platform();
    }

    protected function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new PostgreSqlSchemaManager($connection);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDatabasePlatformsForVersions(): array
    {
        return [
            ['9.4', PostgreSQL94Platform::class],
            ['9.4.0', PostgreSQL94Platform::class],
            ['9.4.1', PostgreSQL94Platform::class],
            ['10', PostgreSQL100Platform::class],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected static function getExceptionConversionData(): array
    {
        return [
            self::EXCEPTION_CONNECTION => [
                [7, null, 'SQLSTATE[08006]'],
            ],
            self::EXCEPTION_FOREIGN_KEY_CONSTRAINT_VIOLATION => [
                [0, '23503'],
            ],
            self::EXCEPTION_INVALID_FIELD_NAME => [
                [0, '42703'],
            ],
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => [
                [0, '42702'],
            ],
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => [
                [0, '23502'],
            ],
            self::EXCEPTION_SYNTAX_ERROR => [
                [0, '42601'],
            ],
            self::EXCEPTION_TABLE_EXISTS => [
                [0, '42P07'],
            ],
            self::EXCEPTION_TABLE_NOT_FOUND => [
                [0, '42P01'],
            ],
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => [
                [0, '23505'],
            ],
            self::EXCEPTION_DEADLOCK => [
                [0, '40001'],
                [0, '40P01'],
            ],
        ];
    }
}
