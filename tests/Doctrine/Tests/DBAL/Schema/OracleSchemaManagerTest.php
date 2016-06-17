<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Schema\OracleSchemaManager;
use Doctrine\DBAL\Schema\Sequence;

class OracleSchemaManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Doctrine\DBAL\Schema\OracleSchemaManager
     */
    private $schemaManager;

    /**
     * @var \Doctrine\DBAL\Connection|\PHPUnit_Framework_MockObject_MockObject
     */
    private $connection;

    protected function setUp()
    {
        $driverMock = $this->getMock('Doctrine\DBAL\Driver');
        $platform = $this->getMock('Doctrine\DBAL\Platforms\OraclePlatform');
        $this->connection = $this->getMock(
            'Doctrine\DBAL\Connection',
            array(),
            array(array('platform' => $platform), $driverMock)
        );
        $this->schemaManager = new OracleSchemaManager($this->connection, $platform);
    }

    public function testPrimaryKeyName()
    {
        $this->connection->expects($this->once())->method('fetchAll')->will($this->returnValue($this->getPKDefinition()));
        $pkeys = $this->schemaManager->listTableIndexes('DUMMY');
        $this->assertEquals(1, count($pkeys), "Table has to have one primary key.");

        $this->assertInstanceOf('Doctrine\DBAL\Schema\Index', $pkeys["primary"]);
        $this->assertEquals(strtolower("PK_C1B1712387FE737264DE5A5511B8B3E"), strtolower($pkeys["primary"]->getName()));
    }

    public function getPKDefinition()
    {
        return array(
            array(
                "NAME" => "PK_C1B1712387FE737264DE5A5511B8B3E",
                "TYPE" => "NORMAL",
                "COLUMN_NAME" => "DUMMY",
                "IS_UNIQUE" => "1",
                "COLUMN_POS" => "1",
                "IS_PRIMARY" => "P",
            )
        );
    }
}
