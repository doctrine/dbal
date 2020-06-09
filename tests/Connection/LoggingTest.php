<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Connection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;

final class LoggingTest extends TestCase
{
    public function testLogExecuteQuery(): void
    {
        $driverConnection = $this->createStub(DriverConnection::class);

        $this->createConnection($driverConnection, 'SELECT * FROM table')
            ->executeQuery('SELECT * FROM table');
    }

    public function testLogExecuteUpdate(): void
    {
        $this->createConnection(
            $this->createStub(DriverConnection::class),
            'UPDATE table SET foo = ?'
        )
            ->executeUpdate('UPDATE table SET foo = ?');
    }

    public function testLogPrepareExecute(): void
    {
        $driverConnection = $this->createStub(DriverConnection::class);
        $driverConnection->method('prepare')
            ->willReturn($this->createStub(Statement::class));

        $this->createConnection($driverConnection, 'UPDATE table SET foo = ?')
            ->prepare('UPDATE table SET foo = ?')
            ->execute();
    }

    private function createConnection(DriverConnection $driverConnection, string $expectedSQL): Connection
    {
        $driver = $this->createStub(Driver::class);
        $driver->method('connect')
            ->willReturn($driverConnection);
        $driver->method('getDatabasePlatform')
            ->willReturn($this->createMock(AbstractPlatform::class));

        $logger = $this->createMock(SQLLogger::class);
        $logger->expects(self::once())
            ->method('startQuery')
            ->with(self::equalTo($expectedSQL), self::equalTo([]));
        $logger->expects(self::at(1))
            ->method('stopQuery');

        $connection = new Connection([], $driver);
        $connection->getConfiguration()->setSQLLogger($logger);

        return $connection;
    }
}
