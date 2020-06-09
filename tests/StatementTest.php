<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StatementTest extends TestCase
{
    /** @var Connection&MockObject */
    private $conn;

    /** @var Configuration&MockObject */
    private $configuration;

    /** @var DriverStatement&MockObject */
    private $driverStatement;

    protected function setUp(): void
    {
        $this->driverStatement = $this->createMock(DriverStatement::class);

        $driverConnection = $this->createConfiguredMock(DriverConnection::class, [
            'prepare' => $this->driverStatement,
        ]);

        $driver = $this->createMock(Driver::class);

        $this->conn = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([[], $driver])
            ->getMock();
        $this->conn->expects(self::atLeastOnce())
                ->method('getWrappedConnection')
                ->will(self::returnValue($driverConnection));

        $this->configuration = $this->createMock(Configuration::class);
        $this->conn->expects(self::any())
                ->method('getConfiguration')
                ->will(self::returnValue($this->configuration));

        $this->conn->expects(self::any())
            ->method('getDriver')
            ->will(self::returnValue($driver));
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
                ->will(self::returnValue($logger));

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
        $logger->expects(self::once())
                ->method('startQuery')
                ->with(self::equalTo($sql), self::equalTo($values), self::equalTo($types));

        $this->configuration->expects(self::once())
                ->method('getSQLLogger')
                ->will(self::returnValue($logger));

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

        $this->configuration->expects(self::once())
            ->method('getSQLLogger')
            ->will(self::returnValue($logger));

        // Needed to satisfy construction of DBALException
        $this->conn->expects(self::any())
            ->method('resolveParams')
            ->will(self::returnValue([]));

        $logger->expects(self::once())
            ->method('startQuery');

        $logger->expects(self::once())
            ->method('stopQuery');

        $this->driverStatement->expects(self::once())
            ->method('execute')
            ->will(self::throwException(new Exception('Mock test exception')));

        $statement = new Statement('', $this->conn);

        $this->expectException(DBALException::class);

        $statement->execute();
    }
}
