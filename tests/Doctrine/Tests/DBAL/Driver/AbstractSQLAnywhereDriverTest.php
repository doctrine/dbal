<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractSQLAnywhereDriver;
use Doctrine\DBAL\Platforms\SQLAnywhere11Platform;
use Doctrine\DBAL\Platforms\SQLAnywhere12Platform;
use Doctrine\DBAL\Platforms\SQLAnywhere16Platform;
use Doctrine\DBAL\Platforms\SQLAnywherePlatform;
use Doctrine\DBAL\Schema\SQLAnywhereSchemaManager;

class AbstractSQLAnywhereDriverTest extends AbstractDriverTest
{
    protected function createDriver()
    {
        return $this->getMockForAbstractClass(AbstractSQLAnywhereDriver::class);
    }

    protected function createPlatform()
    {
        return new SQLAnywhere12Platform();
    }

    protected function createSchemaManager(Connection $connection)
    {
        return new SQLAnywhereSchemaManager($connection);
    }

    protected function getDatabasePlatformsForVersions()
    {
        return [
            ['10', SQLAnywherePlatform::class],
            ['10.0', SQLAnywherePlatform::class],
            ['10.0.0', SQLAnywherePlatform::class],
            ['10.0.0.0', SQLAnywherePlatform::class],
            ['10.1.2.3', SQLAnywherePlatform::class],
            ['10.9.9.9', SQLAnywherePlatform::class],
            ['11', SQLAnywhere11Platform::class],
            ['11.0', SQLAnywhere11Platform::class],
            ['11.0.0', SQLAnywhere11Platform::class],
            ['11.0.0.0', SQLAnywhere11Platform::class],
            ['11.1.2.3', SQLAnywhere11Platform::class],
            ['11.9.9.9', SQLAnywhere11Platform::class],
            ['12', SQLAnywhere12Platform::class],
            ['12.0', SQLAnywhere12Platform::class],
            ['12.0.0', SQLAnywhere12Platform::class],
            ['12.0.0.0', SQLAnywhere12Platform::class],
            ['12.1.2.3', SQLAnywhere12Platform::class],
            ['12.9.9.9', SQLAnywhere12Platform::class],
            ['13', SQLAnywhere12Platform::class],
            ['14', SQLAnywhere12Platform::class],
            ['15', SQLAnywhere12Platform::class],
            ['15.9.9.9', SQLAnywhere12Platform::class],
            ['16', SQLAnywhere16Platform::class],
            ['16.0', SQLAnywhere16Platform::class],
            ['16.0.0', SQLAnywhere16Platform::class],
            ['16.0.0.0', SQLAnywhere16Platform::class],
            ['16.1.2.3', SQLAnywhere16Platform::class],
            ['16.9.9.9', SQLAnywhere16Platform::class],
            ['17', SQLAnywhere16Platform::class],
        ];
    }

    protected function getExceptionConversionData()
    {
        return [
            self::EXCEPTION_CONNECTION => [
                ['-100', null, null],
                ['-103', null, null],
                ['-832', null, null],
            ],
            self::EXCEPTION_FOREIGN_KEY_CONSTRAINT_VIOLATION => [
                ['-198', null, null],
            ],
            self::EXCEPTION_INVALID_FIELD_NAME => [
                ['-143', null, null],
            ],
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => [
                ['-144', null, null],
            ],
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => [
                ['-184', null, null],
                ['-195', null, null],
            ],
            self::EXCEPTION_SYNTAX_ERROR => [
                ['-131', null, null],
            ],
            self::EXCEPTION_TABLE_EXISTS => [
                ['-110', null, null],
            ],
            self::EXCEPTION_TABLE_NOT_FOUND => [
                ['-141', null, null],
                ['-1041', null, null],
            ],
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => [
                ['-193', null, null],
                ['-196', null, null],
            ],
            self::EXCEPTION_DEADLOCK => [
                ['-306', null, null],
                ['-307', null, null],
                ['-684', null, null],
            ],
            self::EXCEPTION_LOCK_WAIT_TIMEOUT => [
                ['-210', null, null],
                ['-1175', null, null],
                ['-1281', null, null],
            ],
        ];
    }
}
