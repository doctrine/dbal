<?php

namespace Doctrine\Tests\DBAL\Functional;

require_once __DIR__ . '/../../TestInit.php';

class LoggingTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function testLogExecuteQuery()
    {
        $sql = $this->_conn->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->getMock('Doctrine\DBAL\Logging\SQLLogger');
        $logMock->expects($this->once())
                ->method('logSQL')
                ->with($this->equalTo($sql), $this->equalTo(array()), $this->isType('float'));
        $this->_conn->getConfiguration()->setSQLLogger($logMock);
        $this->_conn->executeQuery($sql, array());
    }

    public function testLogPrepareExecute()
    {
        $sql = $this->_conn->getDatabasePlatform()->getDummySelectSQL();

        $logMock = $this->getMock('Doctrine\DBAL\Logging\SQLLogger');
        $logMock->expects($this->once())
                ->method('logSQL')
                ->with($this->equalTo($sql), $this->equalTo(array()), $this->isType('float'));
        $this->_conn->getConfiguration()->setSQLLogger($logMock);

        $stmt = $this->_conn->prepare($sql);
        $stmt->execute();
    }
}