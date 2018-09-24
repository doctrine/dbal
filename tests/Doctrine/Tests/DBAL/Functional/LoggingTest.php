<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\Tests\DbalFunctionalTestCase;

class LoggingTest extends DbalFunctionalTestCase
{
    public function testLogExecuteQuery()
    {
        $sql = $this->_conn->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->createMock('Doctrine\DBAL\Logging\SQLLogger');
        $logMock->expects($this->at(0))
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo([]), $this->equalTo([]));
        $logMock->expects($this->at(1))
                ->method('stopQuery');
        $this->_conn->getConfiguration()->setSQLLogger($logMock);
        $this->_conn->executeQuery($sql, []);
    }

    public function testLogExecuteUpdate()
    {
        $this->markTestSkipped('Test breaks MySQL but works on all other platforms (Unbuffered Queries stuff).');

        $sql = $this->_conn->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->createMock('Doctrine\DBAL\Logging\SQLLogger');
        $logMock->expects($this->at(0))
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo([]), $this->equalTo([]));
        $logMock->expects($this->at(1))
                ->method('stopQuery');
        $this->_conn->getConfiguration()->setSQLLogger($logMock);
        $this->_conn->executeUpdate($sql, []);
    }

    public function testLogPrepareExecute()
    {
        $sql = $this->_conn->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->createMock('Doctrine\DBAL\Logging\SQLLogger');
        $logMock->expects($this->once())
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo([]));
        $logMock->expects($this->at(1))
                ->method('stopQuery');
        $this->_conn->getConfiguration()->setSQLLogger($logMock);

        $stmt = $this->_conn->prepare($sql);
        $stmt->execute();
    }
}
