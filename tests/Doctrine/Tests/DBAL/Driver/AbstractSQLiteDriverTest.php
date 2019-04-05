<?php

declare(strict_types=1);

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
                [0, null, 'unable to open database file'],
            ],
            self::EXCEPTION_INVALID_FIELD_NAME => [
                [0, null, 'has no column named'],
            ],
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => [
                [0, null, 'ambiguous column name'],
            ],
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => [
                [0, null, 'may not be NULL'],
            ],
            self::EXCEPTION_READ_ONLY => [
                [0, null, 'attempt to write a readonly database'],
            ],
            self::EXCEPTION_SYNTAX_ERROR => [
                [0, null, 'syntax error'],
            ],
            self::EXCEPTION_TABLE_EXISTS => [
                [0, null, 'already exists'],
            ],
            self::EXCEPTION_TABLE_NOT_FOUND => [
                [0, null, 'no such table:'],
            ],
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => [
                [0, null, 'must be unique'],
                [0, null, 'is not unique'],
                [0, null, 'are not unique'],
            ],
            self::EXCEPTION_LOCK_WAIT_TIMEOUT => [
                [0, null, 'database is locked'],
            ],
        ];
    }
}
