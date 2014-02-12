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
        return array(
            array('10', 'Doctrine\DBAL\Platforms\SQLAnywherePlatform'),
            array('10.0', 'Doctrine\DBAL\Platforms\SQLAnywherePlatform'),
            array('10.0.0', 'Doctrine\DBAL\Platforms\SQLAnywherePlatform'),
            array('10.0.0.0', 'Doctrine\DBAL\Platforms\SQLAnywherePlatform'),
            array('10.1.2.3', 'Doctrine\DBAL\Platforms\SQLAnywherePlatform'),
            array('10.9.9.9', 'Doctrine\DBAL\Platforms\SQLAnywherePlatform'),
            array('11', 'Doctrine\DBAL\Platforms\SQLAnywhere11Platform'),
            array('11.0', 'Doctrine\DBAL\Platforms\SQLAnywhere11Platform'),
            array('11.0.0', 'Doctrine\DBAL\Platforms\SQLAnywhere11Platform'),
            array('11.0.0.0', 'Doctrine\DBAL\Platforms\SQLAnywhere11Platform'),
            array('11.1.2.3', 'Doctrine\DBAL\Platforms\SQLAnywhere11Platform'),
            array('11.9.9.9', 'Doctrine\DBAL\Platforms\SQLAnywhere11Platform'),
            array('12', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'),
            array('12.0', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'),
            array('12.0.0', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'),
            array('12.0.0.0', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'),
            array('12.1.2.3', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'),
            array('12.9.9.9', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'),
            array('13', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'),
            array('14', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'),
            array('15', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'),
            array('15.9.9.9', 'Doctrine\DBAL\Platforms\SQLAnywhere12Platform'),
            array('16', 'Doctrine\DBAL\Platforms\SQLAnywhere16Platform'),
            array('16.0', 'Doctrine\DBAL\Platforms\SQLAnywhere16Platform'),
            array('16.0.0', 'Doctrine\DBAL\Platforms\SQLAnywhere16Platform'),
            array('16.0.0.0', 'Doctrine\DBAL\Platforms\SQLAnywhere16Platform'),
            array('16.1.2.3', 'Doctrine\DBAL\Platforms\SQLAnywhere16Platform'),
            array('16.9.9.9', 'Doctrine\DBAL\Platforms\SQLAnywhere16Platform'),
            array('17', 'Doctrine\DBAL\Platforms\SQLAnywhere16Platform'),
        );
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
        );
    }
}
