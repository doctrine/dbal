<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\MySqlSchemaManager;

class AbstractMySQLDriverTest extends AbstractDriverTest
{
    public function testReturnsDatabaseName()
    {
        parent::testReturnsDatabaseName();

        $database = 'bloo';
        $params   = array(
            'user'     => 'foo',
            'password' => 'bar',
        );

        $statement = $this->getMock('Doctrine\Tests\Mocks\DriverResultStatementMock');

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

        $this->assertSame($database, $this->driver->getDatabase($connection));
    }

    protected function createDriver()
    {
        return $this->getMockForAbstractClass('Doctrine\DBAL\Driver\AbstractMySQLDriver');
    }

    protected function createPlatform()
    {
        return new MySqlPlatform();
    }

    protected function createSchemaManager(Connection $connection)
    {
        return new MySqlSchemaManager($connection);
    }

    protected function getDatabasePlatformsForVersions()
    {
        return array(
            array('5.6.9', 'Doctrine\DBAL\Platforms\MySqlPlatform'),
            array('5.7', 'Doctrine\DBAL\Platforms\MySQL57Platform'),
            array('5.7.0', 'Doctrine\DBAL\Platforms\MySQL57Platform'),
            array('5.7.1', 'Doctrine\DBAL\Platforms\MySQL57Platform'),
            array('6', 'Doctrine\DBAL\Platforms\MySQL57Platform'),
            array('10.0.15-MariaDB-1~wheezy', 'Doctrine\DBAL\Platforms\MySqlPlatform'),
            array('10.1.2a-MariaDB-a1~lenny-log', 'Doctrine\DBAL\Platforms\MySqlPlatform'),
            array('5.5.40-MariaDB-1~wheezy', 'Doctrine\DBAL\Platforms\MySqlPlatform'),
        );
    }

    protected function getExceptionConversionData()
    {
        return array(
            self::EXCEPTION_CONNECTION => array(
                array('1044', null, null),
                array('1045', null, null),
                array('1046', null, null),
                array('1049', null, null),
                array('1095', null, null),
                array('1142', null, null),
                array('1143', null, null),
                array('1227', null, null),
                array('1370', null, null),
                array('2002', null, null),
                array('2005', null, null),
            ),
            self::EXCEPTION_FOREIGN_KEY_CONSTRAINT_VIOLATION => array(
                array('1216', null, null),
                array('1217', null, null),
                array('1451', null, null),
                array('1452', null, null),
            ),
            self::EXCEPTION_INVALID_FIELD_NAME => array(
                array('1054', null, null),
                array('1166', null, null),
                array('1611', null, null),
            ),
            self::EXCEPTION_NON_UNIQUE_FIELD_NAME => array(
                array('1052', null, null),
                array('1060', null, null),
                array('1110', null, null),
            ),
            self::EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION => array(
                array('1048', null, null),
                array('1121', null, null),
                array('1138', null, null),
                array('1171', null, null),
                array('1252', null, null),
                array('1263', null, null),
                array('1566', null, null),
            ),
            self::EXCEPTION_SYNTAX_ERROR => array(
                array('1064', null, null),
                array('1149', null, null),
                array('1287', null, null),
                array('1341', null, null),
                array('1342', null, null),
                array('1343', null, null),
                array('1344', null, null),
                array('1382', null, null),
                array('1479', null, null),
                array('1541', null, null),
                array('1554', null, null),
                array('1626', null, null),
            ),
            self::EXCEPTION_TABLE_EXISTS => array(
                array('1050', null, null),
            ),
            self::EXCEPTION_TABLE_NOT_FOUND => array(
                array('1051', null, null),
                array('1146', null, null),
            ),
            self::EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION => array(
                array('1062', null, null),
                array('1557', null, null),
                array('1569', null, null),
                array('1586', null, null),
            ),
        );
    }
}
