<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractPostgreSQLDriver;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\PostgreSQL91Platform;
use Doctrine\DBAL\Platforms\PostgreSQL92Platform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\PostgreSqlSchemaManager;

class AbstractPostgreSQLDriverTest extends AbstractDriverTest
{
    public function testReturnsDatabaseName(): void
    {
        parent::testReturnsDatabaseName();

        $database = 'bloo';
        $params   = [
            'user'     => 'foo',
            'password' => 'bar',
        ];

        $statement = $this->createMock(ResultStatement::class);

        $statement->expects($this->once())
            ->method('fetchColumn')
            ->willReturn($database);

        $connection = $this->getConnectionMock();

        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn($params);

        $connection->expects($this->once())
            ->method('query')
            ->willReturn($statement);

        self::assertSame($database, $this->driver->getDatabase($connection));
    }

    protected function createDriver(): Driver
    {
        return $this->getMockForAbstractClass(AbstractPostgreSQLDriver::class);
    }

    protected function createPlatform(): AbstractPlatform
    {
        return new PostgreSqlPlatform();
    }

    protected function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new PostgreSqlSchemaManager($connection);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDatabasePlatformsForVersions(): array
    {
        return [
            ['9.0.9', PostgreSqlPlatform::class],
            ['9.1', PostgreSQL91Platform::class],
            ['9.1.0', PostgreSQL91Platform::class],
            ['9.1.1', PostgreSQL91Platform::class],
            ['9.1.9', PostgreSQL91Platform::class],
            ['9.2', PostgreSQL92Platform::class],
            ['9.2.0', PostgreSQL92Platform::class],
            ['9.2.1', PostgreSQL92Platform::class],
            ['9.3.6', PostgreSQL92Platform::class],
            ['9.4', PostgreSQL94Platform::class],
            ['9.4.0', PostgreSQL94Platform::class],
            ['9.4.1', PostgreSQL94Platform::class],
            ['10', PostgreSQL100Platform::class],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected static function getExceptionConversionData(): array
    {
        return [
            self::EXCEPTION_CONNECTION => [
                [null, '7', 'SQLSTATE[08006]'],
            ],
            self::EXCEPTION_FOREIGN_KEY_CONSTRAINT_VIOLATION => [
                [null, '23503', ''],
            ],
            self::EXCEPTION_INVALID_FIELD_NAME => [
                [null, '42703', ''],
            ],
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => [
                [null, '42702', ''],
            ],
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => [
                [null, '23502', ''],
            ],
            self::EXCEPTION_SYNTAX_ERROR => [
                [null, '42601', ''],
            ],
            self::EXCEPTION_TABLE_EXISTS => [
                [null, '42P07', ''],
            ],
            self::EXCEPTION_TABLE_NOT_FOUND => [
                [null, '42P01', ''],
            ],
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => [
                [null, '23505', ''],
            ],
            self::EXCEPTION_DEADLOCK => [
                [null, '40001', ''],
                [null, '40P01', ''],
            ],
        ];
    }
}
