<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\SqliteSchemaManager;

class AbstractSQLiteDriverTest extends AbstractDriverTest
{
    public function testReturnsDatabaseName()
    {
        $params = array(
            'user'     => 'foo',
            'password' => 'bar',
            'dbname'   => 'baz',
            'path'     => 'bloo',
        );

        $connection = $this->getConnectionMock();

        $connection->expects($this->once())
            ->method('getParams')
            ->will($this->returnValue($params));

        $this->assertSame($params['path'], $this->driver->getDatabase($connection));
    }

    protected function createDriver()
    {
        return $this->getMockForAbstractClass('Doctrine\DBAL\Driver\AbstractSQLiteDriver');
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
        return array(
            self::EXCEPTION_CONNECTION => array(
                array(null, null, 'unable to open database file'),
            ),
            self::EXCEPTION_INVALID_FIELD_NAME => array(
                array(null, null, 'has no column named'),
            ),
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => array(
                array(null, null, 'ambiguous column name'),
            ),
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => array(
                array(null, null, 'may not be NULL'),
            ),
            self::EXCEPTION_READ_ONLY => array(
                array(null, null, 'attempt to write a readonly database'),
            ),
            self::EXCEPTION_SYNTAX_ERROR => array(
                array(null, null, 'syntax error'),
            ),
            self::EXCEPTION_TABLE_EXISTS => array(
                array(null, null, 'already exists'),
            ),
            self::EXCEPTION_TABLE_NOT_FOUND => array(
                array(null, null, 'no such table:'),
            ),
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => array(
                array(null, null, 'must be unique'),
                array(null, null, 'is not unique'),
                array(null, null, 'are not unique'),
            ),
        );
    }
}
