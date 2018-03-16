<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLAnywherePlatform;
use Doctrine\DBAL\Schema\SQLAnywhereSchemaManager;

class AbstractSQLAnywhereDriverTest extends AbstractDriverTest
{
    protected function createDriver()
    {
        return $this->getMockForAbstractClass('Doctrine\DBAL\Driver\AbstractSQLAnywhereDriver');
    }

    protected function createPlatform()
    {
        return new SQLAnywherePlatform();
    }

    protected function createSchemaManager(Connection $connection)
    {
        return new SQLAnywhereSchemaManager($connection);
    }

    protected function getDatabasePlatformsForVersions()
    {
        return [
            ['16', SQLAnywherePlatform::class],
            ['16.0', SQLAnywherePlatform::class],
            ['16.0.0', SQLAnywherePlatform::class],
            ['16.0.0.0', SQLAnywherePlatform::class],
            ['16.1.2.3', SQLAnywherePlatform::class],
            ['16.9.9.9', SQLAnywherePlatform::class],
            ['17', SQLAnywherePlatform::class],
        ];
    }

    protected function getExceptionConversionData()
    {
        return array(
            self::EXCEPTION_CONNECTION => array(
                array('-100', null, null),
                array('-103', null, null),
                array('-832', null, null),
            ),
            self::EXCEPTION_FOREIGN_KEY_CONSTRAINT_VIOLATION => array(
                array('-198', null, null),
            ),
            self::EXCEPTION_INVALID_FIELD_NAME => array(
                array('-143', null, null),
            ),
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => array(
                array('-144', null, null),
            ),
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => array(
                array('-184', null, null),
                array('-195', null, null),
            ),
            self::EXCEPTION_SYNTAX_ERROR => array(
                array('-131', null, null),
            ),
            self::EXCEPTION_TABLE_EXISTS => array(
                array('-110', null, null),
            ),
            self::EXCEPTION_TABLE_NOT_FOUND => array(
                array('-141', null, null),
                array('-1041', null, null),
            ),
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => array(
                array('-193', null, null),
                array('-196', null, null),
            ),
            self::EXCEPTION_DEADLOCK => array(
                array('-306', null, null),
                array('-307', null, null),
                array('-684', null, null),
            ),
            self::EXCEPTION_LOCK_WAIT_TIMEOUT => array(
                array('-210', null, null),
                array('-1175', null, null),
                array('-1281', null, null),
            ),
        );
    }
}
