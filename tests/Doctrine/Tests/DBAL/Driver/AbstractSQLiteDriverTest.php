<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\SqliteSchemaManager;

class AbstractSQLiteDriverTest extends AbstractDriverTest
{
    public function testReturnsDatabaseName()
    {
        $params = [
            'user'     => 'foo',
            'password' => 'bar',
            'dbname'   => 'baz',
            'path'     => 'bloo',
        ];

        $connection = $this->getConnectionMock();

        $connection->expects($this->once())
            ->method('getParams')
            ->will($this->returnValue($params));

        self::assertSame($params['path'], $this->driver->getDatabase($connection));
    }

    protected function createDriver()
    {
        return $this->getMockForAbstractClass(AbstractSQLiteDriver::class);
    }

    protected function createPlatform()
    {
        return new SqlitePlatform();
    }

    protected function createSchemaManager(Connection $connection)
    {
        return new SqliteSchemaManager($connection);
    }

    protected function getExceptionConversionData()
    {
        return [
            self::EXCEPTION_CONNECTION => [
                [null, null, 'unable to open database file'],
            ],
            self::EXCEPTION_INVALID_FIELD_NAME => [
                [null, null, 'has no column named'],
            ],
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => [
                [null, null, 'ambiguous column name'],
            ],
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => [
                [null, null, 'may not be NULL'],
            ],
            self::EXCEPTION_READ_ONLY => [
                [null, null, 'attempt to write a readonly database'],
            ],
            self::EXCEPTION_SYNTAX_ERROR => [
                [null, null, 'syntax error'],
            ],
            self::EXCEPTION_TABLE_EXISTS => [
                [null, null, 'already exists'],
            ],
            self::EXCEPTION_TABLE_NOT_FOUND => [
                [null, null, 'no such table:'],
            ],
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => [
                [null, null, 'must be unique'],
                [null, null, 'is not unique'],
                [null, null, 'are not unique'],
            ],
            self::EXCEPTION_LOCK_WAIT_TIMEOUT => [
                [null, null, 'database is locked'],
            ],
        ];
    }
}
