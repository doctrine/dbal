<?php

namespace Doctrine\Tests\DBAL\Events;

use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Event\Listeners\OracleSessionInit;
use Doctrine\DBAL\Events;
use Doctrine\Tests\DbalTestCase;

class OracleSessionInitTest extends DbalTestCase
{
    public function testPostConnect()
    {
        $connectionMock = $this->createMock('Doctrine\DBAL\Connection');
        $connectionMock->expects($this->once())
                       ->method('executeUpdate')
                       ->with($this->isType('string'));

        $eventArgs = new ConnectionEventArgs($connectionMock);


        $listener = new OracleSessionInit();
        $listener->postConnect($eventArgs);
    }

    /**
     * @group DBAL-1824
     *
     * @dataProvider getPostConnectWithSessionParameterValuesData
     */
    public function testPostConnectQuotesSessionParameterValues($name, $value)
    {
        $connectionMock = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->disableOriginalConstructor()
            ->getMock();
        $connectionMock->expects($this->once())
            ->method('executeUpdate')
            ->with($this->stringContains(sprintf('%s = %s', $name, $value)));

        $eventArgs = new ConnectionEventArgs($connectionMock);


        $listener = new OracleSessionInit(array($name => $value));
        $listener->postConnect($eventArgs);
    }

    public function getPostConnectWithSessionParameterValuesData()
    {
        return array(
            array('CURRENT_SCHEMA', 'foo'),
        );
    }

    public function testGetSubscribedEvents()
    {
        $listener = new OracleSessionInit();
        $this->assertEquals(array(Events::postConnect), $listener->getSubscribedEvents());
    }
}
