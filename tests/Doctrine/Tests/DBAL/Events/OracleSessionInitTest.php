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
    public function testPostConnect() : void
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
    public function testPostConnectQuotesSessionParameterValues(string $name, string $value) : void
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

    /**
     * @return array<int, array<int, mixed>>
     */
    public static function getPostConnectWithSessionParameterValuesData() : iterable
    {
        return [
            ['CURRENT_SCHEMA', 'foo'],
        ];
    }

    public function testGetSubscribedEvents() : void
    {
        $listener = new OracleSessionInit();
        self::assertEquals([Events::postConnect], $listener->getSubscribedEvents());
    }
}
