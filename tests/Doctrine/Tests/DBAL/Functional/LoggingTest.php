<?php

namespace Doctrine\Tests\DBAL\Functional;

class LoggingTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function testLogExecuteQuery()
    {
        $sql = $this->_conn->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->getMock('Doctrine\DBAL\Logging\SQLLogger');
        $logMock->expects($this->at(0))
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo(array()), $this->equalTo(array()));
        $logMock->expects($this->at(1))
                ->method('stopQuery');
        $this->_conn->getConfiguration()->setSQLLogger($logMock);
        $this->_conn->executeQuery($sql, array());
    }

    public function testLogExecuteUpdate()
    {
        $this->markTestSkipped('Test breaks MySQL but works on all other platforms (Unbuffered Queries stuff).');

        $sql = $this->_conn->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->getMock('Doctrine\DBAL\Logging\SQLLogger');
        $logMock->expects($this->at(0))
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo(array()), $this->equalTo(array()));
        $logMock->expects($this->at(1))
                ->method('stopQuery');
        $this->_conn->getConfiguration()->setSQLLogger($logMock);
        $this->_conn->executeUpdate($sql, array());
    }

    public function testLogPrepareExecute()
    {
        $sql = $this->_conn->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->getMock('Doctrine\DBAL\Logging\SQLLogger');
        $logMock->expects($this->once())
                ->method('startQuery')
                ->with($this->equalTo($sql), $this->equalTo(array()));
        $logMock->expects($this->at(1))
                ->method('stopQuery');
        $this->_conn->getConfiguration()->setSQLLogger($logMock);

        $stmt = $this->_conn->prepare($sql);
        $stmt->execute();
    }
}