<?php

namespace Doctrine\Tests\DBAL\Functional;

class LoggingTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function testLogExecuteQuery()
    {
        $sql = $this->conn->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->createMock('Doctrine\DBAL\Logging\SQLLogger');
        $logMock->expects($this->at(0))
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo(array()), $this->equalTo(array()));
        $logMock->expects($this->at(1))
                ->method('stopQuery');
        $this->conn->getConfiguration()->setSQLLogger($logMock);
        $this->conn->executeQuery($sql, array());
    }

    public function testLogExecuteUpdate()
    {
        $this->markTestSkipped('Test breaks MySQL but works on all other platforms (Unbuffered Queries stuff).');

        $sql = $this->conn->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->createMock('Doctrine\DBAL\Logging\SQLLogger');
        $logMock->expects($this->at(0))
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo(array()), $this->equalTo(array()));
        $logMock->expects($this->at(1))
                ->method('stopQuery');
        $this->conn->getConfiguration()->setSQLLogger($logMock);
        $this->conn->executeUpdate($sql, array());
    }

    public function testLogPrepareExecute()
    {
        $sql = $this->conn->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->createMock('Doctrine\DBAL\Logging\SQLLogger');
        $logMock->expects($this->once())
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo(array()));
        $logMock->expects($this->at(1))
                ->method('stopQuery');
        $this->conn->getConfiguration()->setSQLLogger($logMock);

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
    }
}
