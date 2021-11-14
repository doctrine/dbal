<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;
use Doctrine\Tests\DbalTestCase;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;

class StatementTest extends DbalTestCase
{
    /** @var Connection&MockObject */
    private $conn;

    /** @var Configuration&MockObject */
    private $configuration;

    /** @var PDOStatement&MockObject */
    private $pdoStatement;

    protected function setUp(): void
    {
        $this->pdoStatement = (new MockBuilderProxy($this->getMockBuilder(PDOStatement::class)))
            ->onlyMethods(['execute', 'bindParam', 'bindValue', 'fetchAll'])
            ->getMock();

        $driverConnection = $this->createMock(DriverConnection::class);
        $driverConnection->expects($this->any())
                ->method('prepare')
                ->willReturn($this->pdoStatement);

        $driver = $this->createMock(Driver::class);

        $this->conn = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([[], $driver])
            ->getMock();
        $this->conn->expects($this->atLeastOnce())
                ->method('getWrappedConnection')
                ->willReturn($driverConnection);

        $this->configuration = $this->createMock(Configuration::class);
        $this->conn->expects($this->any())
                ->method('getConfiguration')
                ->willReturn($this->configuration);

        $this->conn->expects($this->any())
            ->method('getDriver')
            ->willReturn($driver);
    }

    public function testExecuteCallsLoggerStartQueryWithParametersWhenValuesBound(): void
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
                ->willReturn($logger);

        $statement = new Statement($sql, $this->conn);
        $statement->bindValue($name, $var, $type);
        $statement->execute();
    }

    public function testExecuteCallsLoggerStartQueryWithParametersWhenParamsPassedToExecute(): void
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
                ->willReturn($logger);

        $statement = new Statement($sql, $this->conn);
        $statement->execute($values);
    }

    public function testExecuteCallsStartQueryWithTheParametersBoundViaBindParam(): void
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

    public function testExecuteCallsLoggerStopQueryOnException(): void
    {
        $logger = $this->createMock(SQLLogger::class);

        $this->configuration->expects($this->once())
            ->method('getSQLLogger')
            ->willReturn($logger);

        $this->conn->expects($this->any())
            ->method('handleExceptionDuringQuery')
            ->will($this->throwException(new Exception()));

        $logger->expects($this->once())
            ->method('startQuery');

        $logger->expects($this->once())
            ->method('stopQuery');

        $this->pdoStatement->expects($this->once())
            ->method('execute')
            ->will($this->throwException(new \Exception('Mock test exception')));

        $statement = new Statement('', $this->conn);

        $this->expectException(Exception::class);

        $statement->execute();
    }

    public function testPDOCustomClassConstructorArgs(): void
    {
        $statement = new Statement('', $this->conn);

        $this->pdoStatement->expects($this->once())
            ->method('fetchAll')
            ->with(self::equalTo(FetchMode::CUSTOM_OBJECT), self::equalTo('Example'), self::equalTo(['arg1']));

        $statement->fetchAll(FetchMode::CUSTOM_OBJECT, 'Example', ['arg1']);
    }
}
