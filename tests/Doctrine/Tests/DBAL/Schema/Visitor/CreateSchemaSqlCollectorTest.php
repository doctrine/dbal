<?php

namespace Doctrine\Tests\DBAL\Schema\Visitor;

use \Doctrine\DBAL\Schema\Visitor\CreateSchemaSqlCollector;

class CreateSchemaSqlCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testGetQueriesForPsql()
    {
        $platformMock = $this->getMock(
            'Doctrine\DBAL\Platforms\PostgreSqlPlatform',
            array('supportsSchemas', 'schemaNeedsCreation', 'getCreateTableSQL')
        );

        $platformMock->expects($this->any())
                     ->method('supportsSchemas')
                     ->will($this->returnValue(true));

        $platformMock->expects($this->any())
                     ->method('schemaNeedsCreation')
                     ->will($this->returnValue(true));

        $platformMock->expects($this->any())
                     ->method('getCreateTableSQL')
                     ->will($this->returnValue(array('foo')));

        $tableMock = $this->getMockBuilder('\Doctrine\DBAL\Schema\Table')
                          ->disableOriginalConstructor()
                          ->getMock();

        $sqlCollector = new CreateSchemaSqlCollector($platformMock);
        $sqlCollector->acceptTable($tableMock);
        $sql = $sqlCollector->getQueries();
        $this->assertEquals('CREATE SCHEMA public', $sql[0]);
    }
}
