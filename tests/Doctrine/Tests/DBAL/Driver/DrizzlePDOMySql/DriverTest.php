<?php

namespace Doctrine\Tests\DBAL\Driver\DrizzlePDOMySql;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\DrizzlePDOMySql\Driver;
use Doctrine\DBAL\Platforms\DrizzlePlatform;
use Doctrine\DBAL\Schema\DrizzleSchemaManager;
use Doctrine\Tests\DBAL\Driver\PDOMySql\DriverTest as PDOMySQLDriverTest;

class DriverTest extends PDOMySQLDriverTest
{
    public function testReturnsName()
    {
        $this->assertSame('drizzle_pdo_mysql', $this->driver->getName());
    }

    public function testThrowsExceptionOnCreatingDatabasePlatformsForInvalidVersion()
    {
        $this->markTestSkipped('This test does not work on Drizzle as it is not version aware.');
    }

    protected function createDriver()
    {
        return new Driver();
    }

    protected function createPlatform()
    {
        return new DrizzlePlatform();
    }

    protected function createSchemaManager(Connection $connection)
    {
        return new DrizzleSchemaManager($connection);
    }

    protected function getDatabasePlatformsForVersions()
    {
        return array(
            array('foo', 'Doctrine\DBAL\Platforms\DrizzlePlatform'),
            array('bar', 'Doctrine\DBAL\Platforms\DrizzlePlatform'),
            array('baz', 'Doctrine\DBAL\Platforms\DrizzlePlatform'),
        );
    }
}
