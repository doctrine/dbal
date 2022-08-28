<?php

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StatementTest extends TestCase
{
    /** @var Connection&MockObject */
    private Connection $conn;

    /** @var Configuration&MockObject */
    private Configuration $configuration;

    /** @var DriverStatement&MockObject */
    private DriverStatement $driverStatement;

    protected function setUp(): void
    {
        $this->driverStatement = $this->createMock(DriverStatement::class);

        $driver = $this->createMock(Driver::class);

        $this->conn = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([[], $driver])
            ->getMock();

        $this->configuration = $this->createMock(Configuration::class);
        $this->conn->expects(self::any())
                ->method('getConfiguration')
                ->willReturn($this->configuration);

        $this->conn->expects(self::any())
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
        $logger->expects(self::once())
                ->method('startQuery')
                ->with(self::equalTo($sql), self::equalTo($values), self::equalTo($types));

        $this->configuration->expects(self::once())
                ->method('getSQLLogger')
                ->willReturn($logger);

        $statement = new Statement($this->conn, $this->driverStatement, $sql);
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
        $logger->expects(self::once())
                ->method('startQuery')
                ->with(self::equalTo($sql), self::equalTo($values), self::equalTo($types));

        $this->configuration->expects(self::once())
                ->method('getSQLLogger')
                ->willReturn($logger);

        $statement = new Statement($this->conn, $this->driverStatement, $sql);
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

        $statement = new Statement($this->conn, $this->driverStatement, $sql);
        $statement->bindParam($name, $var);
        $statement->execute();
    }

    public function testExecuteCallsLoggerStopQueryOnException(): void
    {
        $logger = $this->createMock(SQLLogger::class);

        $this->configuration->expects(self::once())
            ->method('getSQLLogger')
            ->willReturn($logger);

        $logger->expects(self::once())
            ->method('startQuery');

        $logger->expects(self::once())
            ->method('stopQuery');

        $this->driverStatement->expects(self::once())
            ->method('execute')
            ->will(self::throwException(
                $this->createMock(DriverException::class),
            ));

        $statement = new Statement($this->conn, $this->driverStatement, '');

        $this->expectException(Exception::class);

        $statement->execute();
    }
}
