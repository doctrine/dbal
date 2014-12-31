<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Schema\PostgreSqlSchemaManager;
use Doctrine\DBAL\Schema\Sequence;

class PostgreSQLSchemaManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Doctrine\DBAL\Schema\PostgreSQLSchemaManager
     */
    private $schemaManager;

    /**
     * @var \Doctrine\DBAL\Connection|\PHPUnit_Framework_MockObject_MockObject
     */
    private $connection;

    /**
     * @var \Doctrine\DBAL\Platforms\PostgreSqlPlatform|\PHPUnit_Framework_MockObject_MockObject
     */
    private $platform;

    protected function setUp()
    {
        $driverMock = $this->getMock('Doctrine\DBAL\Driver');
        $this->platform = $this->getMock('Doctrine\DBAL\Platforms\PostgreSqlPlatform');
        $this->connection = $this->getMock(
            'Doctrine\DBAL\Connection',
            array(),
            array(array('platform' => $this->platform), $driverMock)
        );
        $this->schemaManager = new PostgreSqlSchemaManager($this->connection, $this->platform);
    }

    /**
     * @group DBAL-474
     */
    public function testFiltersSequences()
    {
        $configuration = new Configuration();
        $configuration->setFilterSchemaAssetsExpression('/^schema/');

        $sequences = array(
            array('relname' => 'foo', 'schemaname' => 'schema'),
            array('relname' => 'bar', 'schemaname' => 'schema'),
            array('relname' => 'baz', 'schemaname' => ''),
            array('relname' => 'bloo', 'schemaname' => 'bloo_schema'),
        );

        $this->connection->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($configuration));

        $this->connection->expects($this->at(0))
            ->method('fetchAll')
            ->will($this->returnValue($sequences));

        $this->connection->expects($this->at(1))
            ->method('fetchAll')
            ->will($this->returnValue(array(array('min_value' => 1, 'increment_by' => 1))));

        $this->connection->expects($this->at(2))
            ->method('fetchAll')
            ->will($this->returnValue(array(array('min_value' => 2, 'increment_by' => 2))));

        $this->connection->expects($this->exactly(3))
            ->method('fetchAll');

        $this->assertEquals(
            array(
                new Sequence('schema.foo', 2, 2),
                new Sequence('schema.bar', 1, 1),
            ),
            $this->schemaManager->listSequences('database')
        );
    }

    /**
     * @group DBAL-1087
     */
    public function testListTableColumnsBpcharLength()
    {
        $this->connection->expects($this->atLeastOnce())
            ->method('getDatabase')
            ->will($this->returnValue('pg_db_name'));

        $fetchedColumns = array(
            array(
                'attnum' => 1,
                'field' => 'name',
                'type' => 'bpchar',
                'complete_type' => 'character(2)',
                'collation' => '',
                'domain_type' => null,
                'domain_complete_type' => null,
                'isnotnull' => true,
                'pri' => null,
                'default' => null,
                'comment' => null,
            ),
        );

        $this->connection->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->will($this->returnValue($fetchedColumns));

        $this->platform->expects($this->atLeastOnce())
            ->method('getDoctrineTypeMapping')
            ->with('bpchar')
            ->will($this->returnValue('string'));

        $columns = $this->schemaManager->listTableColumns('test');

        $this->assertSame(2, $columns['name']->getLength());
    }
}
