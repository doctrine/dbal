<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractSQLAnywhereDriver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLAnywhere16Platform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SQLAnywhereSchemaManager;

class AbstractSQLAnywhereDriverTest extends AbstractDriverTest
{
    protected function createDriver(): Driver
    {
        return $this->getMockForAbstractClass(AbstractSQLAnywhereDriver::class);
    }

    protected function createPlatform(): AbstractPlatform
    {
        return new SQLAnywhere16Platform();
    }

    protected function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new SQLAnywhereSchemaManager($connection);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDatabasePlatformsForVersions(): array
    {
        return [
            ['16', SQLAnywhere16Platform::class],
            ['16.0', SQLAnywhere16Platform::class],
            ['16.0.0', SQLAnywhere16Platform::class],
            ['16.0.0.0', SQLAnywhere16Platform::class],
            ['16.1.2.3', SQLAnywhere16Platform::class],
            ['16.9.9.9', SQLAnywhere16Platform::class],
            ['17', SQLAnywhere16Platform::class],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected static function getExceptionConversionData(): array
    {
        return [
            self::EXCEPTION_CONNECTION => [
                [-100],
                [-103],
                [-832],
            ],
            self::EXCEPTION_FOREIGN_KEY_CONSTRAINT_VIOLATION => [
                [-198],
            ],
            self::EXCEPTION_INVALID_FIELD_NAME => [
                [-143],
            ],
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => [
                [-144],
            ],
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => [
                [-184],
                [-195],
            ],
            self::EXCEPTION_SYNTAX_ERROR => [
                [-131],
            ],
            self::EXCEPTION_TABLE_EXISTS => [
                [-110],
            ],
            self::EXCEPTION_TABLE_NOT_FOUND => [
                [-141],
                [-1041],
            ],
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => [
                [-193],
                [-196],
            ],
            self::EXCEPTION_DEADLOCK => [
                [-306],
                [-307],
                [-684],
            ],
            self::EXCEPTION_LOCK_WAIT_TIMEOUT => [
                [-210],
                [-1175],
                [-1281],
            ],
        ];
    }
}
