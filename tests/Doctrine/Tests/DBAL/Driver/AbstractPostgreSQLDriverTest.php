<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Schema\PostgreSqlSchemaManager;

class AbstractPostgreSQLDriverTest extends AbstractDriverTest
{
    public function testReturnsDatabaseName()
    {
        parent::testReturnsDatabaseName();

        $database = 'bloo';
        $params   = array(
            'user'     => 'foo',
            'password' => 'bar',
        );

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
            ['9.3', PostgreSqlPlatform::class],
            ['9.3.0', PostgreSqlPlatform::class],
            ['9.3.6', PostgreSqlPlatform::class],
            ['9.4', PostgreSQL94Platform::class],
            ['9.4.0', PostgreSQL94Platform::class],
            ['9.4.1', PostgreSQL94Platform::class],
            ['10', PostgreSQL100Platform::class],
        ];
    }

    protected function getExceptionConversionData()
    {
        return array(
            self::EXCEPTION_CONNECTION => array(
                array(null, '7', 'SQLSTATE[08006]'),
            ),
            self::EXCEPTION_FOREIGN_KEY_CONSTRAINT_VIOLATION => array(
                array(null, '23503', null),
            ),
            self::EXCEPTION_INVALID_FIELD_NAME => array(
                array(null, '42703', null),
            ),
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => array(
                array(null, '42702', null),
            ),
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => array(
                array(null, '23502', null),
            ),
            self::EXCEPTION_SYNTAX_ERROR => array(
                array(null, '42601', null),
            ),
            self::EXCEPTION_TABLE_EXISTS => array(
                array(null, '42P07', null),
            ),
            self::EXCEPTION_TABLE_NOT_FOUND => array(
                array(null, '42P01', null),
            ),
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => array(
                array(null, '23505', null),
            ),
            self::EXCEPTION_DEADLOCK => array(
                array(null, '40001', null),
                array(null, '40P01', null),
            ),
        );
    }
}
