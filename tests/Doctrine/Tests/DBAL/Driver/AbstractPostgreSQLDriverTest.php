<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Schema\PostgreSqlSchemaManager;

class AbstractPostgreSQLDriverTest extends AbstractDriverTest
{
    public function testReturnsDatabaseName()
    {
        parent::testReturnsDatabaseName();

        $database = 'bloo';
        $params   = [
            'user'     => 'foo',
            'password' => 'bar',
        ];

        $statement = $this->createMock('Doctrine\Tests\Mocks\DriverResultStatementMock');

        $statement->expects($this->once())
            ->method('fetchColumn')
            ->will($this->returnValue($database));

        $connection = $this->getConnectionMock();

        $connection->expects($this->once())
            ->method('getParams')
            ->will($this->returnValue($params));

        $connection->expects($this->once())
            ->method('query')
            ->will($this->returnValue($statement));

        self::assertSame($database, $this->driver->getDatabase($connection));
    }

    protected function createDriver()
    {
        return $this->getMockForAbstractClass('Doctrine\DBAL\Driver\AbstractPostgreSQLDriver');
    }

    protected function createPlatform()
    {
        return new PostgreSqlPlatform();
    }

    protected function createSchemaManager(Connection $connection)
    {
        return new PostgreSqlSchemaManager($connection);
    }

    protected function getDatabasePlatformsForVersions()
    {
        return [
            ['9.0.9', 'Doctrine\DBAL\Platforms\PostgreSqlPlatform'],
            ['9.1', 'Doctrine\DBAL\Platforms\PostgreSQL91Platform'],
            ['9.1.0', 'Doctrine\DBAL\Platforms\PostgreSQL91Platform'],
            ['9.1.1', 'Doctrine\DBAL\Platforms\PostgreSQL91Platform'],
            ['9.1.9', 'Doctrine\DBAL\Platforms\PostgreSQL91Platform'],
            ['9.2', 'Doctrine\DBAL\Platforms\PostgreSQL92Platform'],
            ['9.2.0', 'Doctrine\DBAL\Platforms\PostgreSQL92Platform'],
            ['9.2.1', 'Doctrine\DBAL\Platforms\PostgreSQL92Platform'],
            ['9.3.6', 'Doctrine\DBAL\Platforms\PostgreSQL92Platform'],
            ['9.4', 'Doctrine\DBAL\Platforms\PostgreSQL94Platform'],
            ['9.4.0', 'Doctrine\DBAL\Platforms\PostgreSQL94Platform'],
            ['9.4.1', 'Doctrine\DBAL\Platforms\PostgreSQL94Platform'],
            ['10', PostgreSQL100Platform::class],
        ];
    }

    protected function getExceptionConversionData()
    {
        return [
            self::EXCEPTION_CONNECTION => [
                [null, '7', 'SQLSTATE[08006]'],
            ],
            self::EXCEPTION_FOREIGN_KEY_CONSTRAINT_VIOLATION => [
                [null, '23503', null],
            ],
            self::EXCEPTION_INVALID_FIELD_NAME => [
                [null, '42703', null],
            ],
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => [
                [null, '42702', null],
            ],
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => [
                [null, '23502', null],
            ],
            self::EXCEPTION_SYNTAX_ERROR => [
                [null, '42601', null],
            ],
            self::EXCEPTION_TABLE_EXISTS => [
                [null, '42P07', null],
            ],
            self::EXCEPTION_TABLE_NOT_FOUND => [
                [null, '42P01', null],
            ],
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => [
                [null, '23505', null],
            ],
            self::EXCEPTION_DEADLOCK => [
                [null, '40001', null],
                [null, '40P01', null],
            ],
        ];
    }
}
