<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\Tests\DbalFunctionalTestCase;

class LoggingTest extends DbalFunctionalTestCase
{
    public function testLogExecuteQuery() : void
    {
        $sql = $this->connection->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->createMock(SQLLogger::class);
        $logMock->expects($this->at(0))
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo([]), $this->equalTo([]));
        $logMock->expects($this->at(1))
                ->method('stopQuery');
        $this->connection->getConfiguration()->setSQLLogger($logMock);
        $this->connection->executeQuery($sql, []);
    }

    public function testLogExecuteUpdate() : void
    {
        $this->markTestSkipped('Test breaks MySQL but works on all other platforms (Unbuffered Queries stuff).');

        $sql = $this->connection->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->createMock(SQLLogger::class);
        $logMock->expects($this->at(0))
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo([]), $this->equalTo([]));
        $logMock->expects($this->at(1))
                ->method('stopQuery');
        $this->connection->getConfiguration()->setSQLLogger($logMock);
        $this->connection->executeUpdate($sql, []);
    }

    public function testLogPrepareExecute() : void
    {
        $sql = $this->connection->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->createMock(SQLLogger::class);
        $logMock->expects($this->once())
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo([]));
        $logMock->expects($this->at(1))
                ->method('stopQuery');
        $this->connection->getConfiguration()->setSQLLogger($logMock);

        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
    }
}
