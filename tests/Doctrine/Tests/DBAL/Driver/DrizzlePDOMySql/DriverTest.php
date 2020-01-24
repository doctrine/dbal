<?php

namespace Doctrine\Tests\DBAL\Driver\DrizzlePDOMySql;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\DrizzlePDOMySql\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DrizzlePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\DrizzleSchemaManager;
use Doctrine\Tests\DBAL\Driver\PDOMySql\DriverTest as PDOMySQLDriverTest;

class DriverTest extends PDOMySQLDriverTest
{
    public function testReturnsName() : void
    {
        self::assertSame('drizzle_pdo_mysql', $this->driver->getName());
    }

    public function testThrowsExceptionOnCreatingDatabasePlatformsForInvalidVersion() : void
    {
        $this->markTestSkipped('This test does not work on Drizzle as it is not version aware.');
    }

    protected function createDriver() : DriverInterface
    {
        return new Driver();
    }

    protected function createPlatform() : AbstractPlatform
    {
        return new DrizzlePlatform();
    }

    protected function createSchemaManager(Connection $connection) : AbstractSchemaManager
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
