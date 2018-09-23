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
        self::assertSame('drizzle_pdo_mysql', $this->driver->getName());
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

    /**
     * @return mixed[][]
     */
    protected function getDatabasePlatformsForVersions() : array
    {
        return [
            ['foo', DrizzlePlatform::class],
            ['bar', DrizzlePlatform::class],
            ['baz', DrizzlePlatform::class],
        ];
    }
}
