<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;
use Exception;
use PDOStatement;

class StatementTest extends DbalTestCase
{
    /** @var Connection */
    private $conn;

    /** @var Configuration */
    private $configuration;

    /** @var PDOStatement */
    private $pdoStatement;

    protected function setUp()
    {
        $this->pdoStatement = $this->getMockBuilder(PDOStatement::class)
            ->setMethods(['execute', 'bindParam', 'bindValue'])
            ->getMock();
        $platform           = new MockPlatform();
        $driverConnection   = $this->createMock(DriverConnection::class);
        $driverConnection->expects($this->any())
                ->method('prepare')
                ->will($this->returnValue($this->pdoStatement));

        $driver          = $this->createMock(Driver::class);
        $constructorArgs = [
            ['platform' => $platform],
            $driver,
        ];
        $this->conn      = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs($constructorArgs)
            ->getMock();
        $this->conn->expects($this->atLeastOnce())
                ->method('getWrappedConnection')
                ->will($this->returnValue($driverConnection));

        $this->configuration = $this->createMock(Configuration::class);
        $this->conn->expects($this->any())
                ->method('getConfiguration')
                ->will($this->returnValue($this->configuration));

        $this->conn->expects($this->any())
            ->method('getDriver')
            ->will($this->returnValue($driver));
    }

    public function testExecuteCallsLoggerStartQueryWithParametersWhenValuesBound()
    {
        $name   = 'foo';
        $var    = 'bar';
        $type   = ParameterType::STRING;
        $values = [$name => $var];
        $types  = [$name => $type];
        $sql    = '';

        $logger = $this->createMock(SQLLogger::class);
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
        $name   = 'foo';
        $var    = 'bar';
        $values = [$name => $var];
        $types  = [];
        $sql    = '';

        $logger = $this->createMock(SQLLogger::class);
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
        $name   = 'foo';
        $var    = 'bar';
        $values = [$name => $var];
        $types  = [$name => ParameterType::STRING];
        $sql    = '';

        $logger = $this->createMock(SQLLogger::class);
        $logger->expects(self::once())
                ->method('startQuery')
                ->with(self::equalTo($sql), self::equalTo($values), self::equalTo($types));

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
        $logger = $this->createMock(SQLLogger::class);

        $this->configuration->expects($this->once())
            ->method('getSQLLogger')
            ->will($this->returnValue($logger));

        // Needed to satisfy construction of DBALException
        $this->conn->expects($this->any())
            ->method('resolveParams')
            ->will($this->returnValue([]));

        $logger->expects($this->once())
            ->method('startQuery');

        $logger->expects($this->once())
            ->method('stopQuery');

        $this->pdoStatement->expects($this->once())
            ->method('execute')
            ->will($this->throwException(new Exception('Mock test exception')));

        $statement = new Statement('', $this->conn);
        $statement->execute();
    }
}
