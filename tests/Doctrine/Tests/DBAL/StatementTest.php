<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Statement;

class StatementTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     *
     * @var \Doctrine\DBAL\Connection 
     */
    private $conn;
    
    /**
     *
     * @var \Doctrine\DBAL\Configuration 
     */
    private $configuration;
    
    public function setUp()
    {
        $pdoStatement = $this->getMock('\PDOStatment', array('execute', 'bindParam', 'bindValue'));
        $platform = new \Doctrine\Tests\DBAL\Mocks\MockPlatform();
        $driverConnection = $this->getMock('\Doctrine\DBAL\Driver\Connection');
        $driverConnection->expects($this->any())
                ->method('prepare')
                ->will($this->returnValue($pdoStatement));
        
        $driver = $this->getMock('\Doctrine\DBAL\Driver');
        $constructorArgs = array(
            array(
                'platform' => $platform
            ),
            $driver
        );
        $this->conn = $this->getMock('\Doctrine\DBAL\Connection', array(), $constructorArgs);
        $this->conn->expects($this->atLeastOnce())
                ->method('getWrappedConnection')
                ->will($this->returnValue($driverConnection));
        
        $this->configuration = $this->getMock('\Doctrine\DBAL\Configuration');
        $this->conn->expects($this->any())
                ->method('getConfiguration')
                ->will($this->returnValue($this->configuration));
    }
    
    public function testExecuteCallsLoggerStartQueryWithParametersWhenValuesBound()
    {
        $name = 'foo';
        $var = 'bar';
        $type = \PDO::PARAM_STR;
        $values = array($name => $var);
        $types = array($name => $type);
        $sql = '';
        
        $logger = $this->getMock('\Doctrine\DBAL\Logging\SQLLogger');
        $logger->expects($this->once())
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo($values), $this->equalTo($types));
        
        $this->configuration->expects($this->once())
                ->method('getSQLLogger')
                ->will($this->returnValue($logger));
        
        $statement = new Statement($sql, $this->conn);
        $statement->bindValue($name, $var, $type);
        $statement->execute();
    }
    
    public function testExecuteCallsLoggerStartQueryWithParametersWhenParamsPassedToExecute()
    {
        $name = 'foo';
        $var = 'bar';
        $values = array($name => $var);
        $types = array();
        $sql = '';
        
        $logger = $this->getMock('\Doctrine\DBAL\Logging\SQLLogger');
        $logger->expects($this->once())
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo($values), $this->equalTo($types));
        
        $this->configuration->expects($this->once())
                ->method('getSQLLogger')
                ->will($this->returnValue($logger));
        
        $statement = new Statement($sql, $this->conn);
        $statement->execute($values);
    }
}