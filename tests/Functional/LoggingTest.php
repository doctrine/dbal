<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class LoggingTest extends FunctionalTestCase
{
    public function testLogExecuteQuery() : void
    {
        $sql = $this->connection->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->createMock(SQLLogger::class);
        $logMock->expects(self::at(0))
                ->method('startQuery')
                ->with(self::equalTo($sql), self::equalTo([]), self::equalTo([]));
        $logMock->expects(self::at(1))
                ->method('stopQuery');
        $this->connection->getConfiguration()->setSQLLogger($logMock);
        $this->connection->executeQuery($sql, []);
    }

    public function testLogPrepareExecute() : void
    {
        $sql = $this->connection->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->createMock(SQLLogger::class);
        $logMock->expects(self::once())
                ->method('startQuery')
                ->with(self::equalTo($sql), self::equalTo([]));
        $logMock->expects(self::at(1))
                ->method('stopQuery');
        $this->connection->getConfiguration()->setSQLLogger($logMock);

        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
    }
}
