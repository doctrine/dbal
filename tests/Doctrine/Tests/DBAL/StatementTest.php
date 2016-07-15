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

    /**
     * @var \PDOStatement
     */
    private $pdoStatement;

    public function setUp()
    {
        $this->pdoStatement = $this->getMock('\PDOStatement', array('execute', 'bindParam', 'bindValue'));
        $platform = new \Doctrine\Tests\DBAL\Mocks\MockPlatform();
        $driverConnection = $this->getMock('\Doctrine\DBAL\Driver\Connection');
        $driverConnection->expects($this->any())
                ->method('prepare')
                ->will($this->returnValue($this->pdoStatement));

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

        $this->conn->expects($this->any())
            ->method('getDriver')
            ->will($this->returnValue($driver));

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

    public function testExecuteCallsStartQueryWithTheParametersBoundViaBindParam()
    {
        $name = 'foo';
        $var = 'bar';
        $values = array($name => $var);
        $types = array($name => \PDO::PARAM_STR);
        $sql = '';

        $logger = $this->getMock('Doctrine\DBAL\Logging\SQLLogger');
        $logger->expects($this->once())
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo($values), $this->equalTo($types));

        $this->configuration->expects(self::once())
                ->method('getSQLLogger')
                ->willReturn($logger);

        $statement = new Statement($sql, $this->conn);
        $statement->bindParam($name, $var);
        $statement->execute();
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testExecuteCallsLoggerStopQueryOnException()
    {
        $logger = $this->getMock('\Doctrine\DBAL\Logging\SQLLogger');

        $this->configuration->expects($this->once())
            ->method('getSQLLogger')
            ->will($this->returnValue($logger));

        // Needed to satisfy construction of DBALException
        $this->conn->expects($this->any())
            ->method('resolveParams')
            ->will($this->returnValue(array()));

        $logger->expects($this->once())
            ->method('startQuery');

        $logger->expects($this->once())
            ->method('stopQuery');

        $this->pdoStatement->expects($this->once())
            ->method('execute')
            ->will($this->throwException(new \Exception("Mock test exception")));

        $statement = new Statement("", $this->conn);
        $statement->execute();
    }
}
