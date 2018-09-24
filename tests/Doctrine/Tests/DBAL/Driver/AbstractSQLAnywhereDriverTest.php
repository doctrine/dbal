<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLAnywhere12Platform;
use Doctrine\DBAL\Schema\SQLAnywhereSchemaManager;

class AbstractSQLAnywhereDriverTest extends AbstractDriverTest
{
    protected function createDriver()
    {
        return $this->getMockForAbstractClass('Doctrine\DBAL\Driver\AbstractSQLAnywhereDriver');
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
            ['10', 'Doctrine\DBAL\Platforms\SQLAnywherePlatform'],
            ['10.0', 'Doctrine\DBAL\Platforms\SQLAnywherePlatform'],
            ['10.0.0', 'Doctrine\DBAL\Platforms\SQLAnywherePlatform'],
            ['10.0.0.0', 'Doctrine\DBAL\Platforms\SQLAnywherePlatform'],
            ['10.1.2.3', 'Doctrine\DBAL\Platforms\SQLAnywherePlatform'],
            ['10.9.9.9', 'Doctrine\DBAL\Platforms\SQLAnywherePlatform'],
            ['11', 'Doctrine\DBAL\Platforms\SQLAnywhere11Platform'],
            ['11.0', 'Doctrine\DBAL\Platforms\SQLAnywhere11Platform'],
            ['11.0.0', 'Doctrine\DBAL\Platforms\SQLAnywhere11Platform'],
            ['11.0.0.0', 'Doctrine\DBAL\Platforms\SQLAnywhere11Platform'],
            ['11.1.2.3', 'Doctrine\DBAL\Platforms\SQLAnywhere11Platform'],
            ['11.9.9.9', 'Doctrine\DBAL\Platforms\SQLAnywhere11Platform'],
            ['12', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'],
            ['12.0', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'],
            ['12.0.0', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'],
            ['12.0.0.0', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'],
            ['12.1.2.3', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'],
            ['12.9.9.9', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'],
            ['13', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'],
            ['14', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'],
            ['15', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'],
            ['15.9.9.9', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'],
            ['16', 'Doctrine\DBAL\Platforms\SQLAnywhere16Platform'],
            ['16.0', 'Doctrine\DBAL\Platforms\SQLAnywhere16Platform'],
            ['16.0.0', 'Doctrine\DBAL\Platforms\SQLAnywhere16Platform'],
            ['16.0.0.0', 'Doctrine\DBAL\Platforms\SQLAnywhere16Platform'],
            ['16.1.2.3', 'Doctrine\DBAL\Platforms\SQLAnywhere16Platform'],
            ['16.9.9.9', 'Doctrine\DBAL\Platforms\SQLAnywhere16Platform'],
            ['17', 'Doctrine\DBAL\Platforms\SQLAnywhere16Platform'],
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
