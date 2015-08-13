<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\InformixPlatform;
use Doctrine\DBAL\Schema\InformixSchemaManager;

class AbstractInformixDriverTest extends AbstractDriverTest
{
    protected function createDriver()
    {
        return $this->getMockForAbstractClass(
            'Doctrine\DBAL\Driver\AbstractInformixDriver'
        );
    }

    protected function createPlatform()
    {
        return new InformixPlatform();
    }

    protected function createSchemaManager(Connection $connection)
    {
        return new InformixSchemaManager($connection);
    }

    protected function getExceptionConversionData()
    {
        return array(
            self::EXCEPTION_CONNECTION => array(
                array('-908', null, null),
                array('-930', null, null),
                array('-951', null, null),
            ),
            self::EXCEPTION_FOREIGN_KEY_CONSTRAINT_VIOLATION => array(
                array('-692', null, null),
            ),
            self::EXCEPTION_INVALID_FIELD_NAME => array(
                array('-217', null, null),
            ),
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => array(
                array('-324', null, null),
            ),
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => array(
                array('-391', null, null),
            ),
            self::EXCEPTION_SYNTAX_ERROR => array(
                array('-201', null, null),
            ),
            self::EXCEPTION_TABLE_EXISTS => array(
                array('-310', null, null),
            ),
            self::EXCEPTION_TABLE_NOT_FOUND => array(
                array('-206', null, null),
            ),
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => array(
                array('-239', null, null),
                array('-268', null, null),
            ),
        );
    }

    protected function getDatabasePlatformsForVersions()
    {
        // The PDO_INFORMIX driver uses the same platform for all versions
        return array(
            array(
                'IBM Informix Dynamic Server Version 11.50.Fxx',
                'Doctrine\DBAL\Platforms\InformixPlatform'
            ),
        );
    }
}
