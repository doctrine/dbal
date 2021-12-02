<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractPostgreSQLDriver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\API\PostgreSQL;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\PostgreSQLSchemaManager;

/**
 * @extends AbstractDriverTest<PostgreSQLPlatform>
 */
class AbstractPostgreSQLDriverTest extends AbstractDriverTest
{
    public function testThrowsExceptionOnCreatingDatabasePlatformsForInvalidVersion(): void
    {
        self::markTestSkipped('PostgreSQL drivers do not use server version to instantiate platform');
    }

    protected function createDriver(): Driver
    {
        return $this->getMockForAbstractClass(AbstractPostgreSQLDriver::class);
    }

    protected function createPlatform(): AbstractPlatform
    {
        return new PostgreSQLPlatform();
    }

    protected function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new PostgreSQLSchemaManager(
            $connection,
            $this->createPlatform()
        );
    }

    protected function createExceptionConverter(): ExceptionConverter
    {
        return new PostgreSQL\ExceptionConverter();
    }

    /**
     * {@inheritDoc}
     */
    public static function platformVersionProvider(): array
    {
        return [
            ['10.0', PostgreSQLPlatform::class],
            ['11.0', PostgreSQLPlatform::class],
            ['13.3', PostgreSQLPlatform::class],
        ];
    }
}
