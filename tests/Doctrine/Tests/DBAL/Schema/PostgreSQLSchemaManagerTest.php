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

    protected function setUp()
    {
        $driverMock = $this->createMock('Doctrine\DBAL\Driver');
        $platform = $this->createMock('Doctrine\DBAL\Platforms\PostgreSqlPlatform');

        $this->connection = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->setConstructorArgs([['platform' => $platform], $driverMock])
            ->getMock();

        $this->schemaManager = new PostgreSqlSchemaManager($this->connection, $platform);
    }

    /**
     * @group DBAL-474
     */
    public function testFiltersSequences()
    {
        $configuration = new Configuration();
        $configuration->setFilterSchemaAssetsExpression('/^schema/');

        $sequences = [
            ['relname' => 'foo', 'schemaname' => 'schema'],
            ['relname' => 'bar', 'schemaname' => 'schema'],
            ['relname' => 'baz', 'schemaname' => ''],
            ['relname' => 'bloo', 'schemaname' => 'bloo_schema'],
        ];

        $this->connection->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($configuration));

        $this->connection->expects($this->at(0))
            ->method('fetchAll')
            ->will($this->returnValue($sequences));

        $this->connection->expects($this->at(1))
            ->method('fetchAll')
            ->will($this->returnValue([['min_value' => 1, 'increment_by' => 1]]));

        $this->connection->expects($this->at(2))
            ->method('fetchAll')
            ->will($this->returnValue([['min_value' => 2, 'increment_by' => 2]]));

        $this->connection->expects($this->exactly(3))
            ->method('fetchAll');

        $this->assertEquals(
            [
                new Sequence('schema.foo', 2, 2),
                new Sequence('schema.bar', 1, 1),
            ],
            $this->schemaManager->listSequences('database')
        );
    }
}
