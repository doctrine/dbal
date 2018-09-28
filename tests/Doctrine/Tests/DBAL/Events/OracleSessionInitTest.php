<?php

namespace Doctrine\Tests\DBAL\Events;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Event\Listeners\OracleSessionInit;
use Doctrine\DBAL\Events;
use Doctrine\Tests\DbalTestCase;
use function sprintf;

class OracleSessionInitTest extends DbalTestCase
{
    public function testPostConnect()
    {
        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->expects($this->once())
                       ->method('executeUpdate')
                       ->with($this->isType('string'));

        $eventArgs = new ConnectionEventArgs($connectionMock);

        $listener = new OracleSessionInit();
        $listener->postConnect($eventArgs);
    }

    /**
     * @group DBAL-1824
     * @dataProvider getPostConnectWithSessionParameterValuesData
     */
    public function testPostConnectQuotesSessionParameterValues($name, $value)
    {
        $connectionMock = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $connectionMock->expects($this->once())
            ->method('executeUpdate')
            ->with($this->stringContains(sprintf('%s = %s', $name, $value)));

        $eventArgs = new ConnectionEventArgs($connectionMock);

        $listener = new OracleSessionInit([$name => $value]);
        $listener->postConnect($eventArgs);
    }

    public function getPostConnectWithSessionParameterValuesData()
    {
        return [
            ['CURRENT_SCHEMA', 'foo'],
        ];
    }

    public function testGetSubscribedEvents()
    {
        $listener = new OracleSessionInit();
        self::assertEquals([Events::postConnect], $listener->getSubscribedEvents());
    }
}
