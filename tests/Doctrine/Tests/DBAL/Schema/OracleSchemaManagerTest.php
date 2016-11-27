<?php
namespace Doctrine\Tests\DBAL\Schema;

class OracleSchemaManagerTest extends AbstractSchemaManagerTest
{
    protected function setUp()
    {
        $driverMock = $this->createMock('Doctrine\DBAL\Driver');
        $this->platform = new \Doctrine\DBAL\Platforms\OraclePlatform();
        $this->connection = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->setConstructorArgs(array(array('platform' => $this->platform), $driverMock))
            ->getMock();
        $this->schemaManager = new \Doctrine\DBAL\Schema\OracleSchemaManager($this->connection, $this->platform);
        parent::setUp();
    }
}