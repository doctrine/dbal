<?php

namespace Doctrine\DBAL\Tests\Events;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Event\Listeners\MysqlSessionInit;
use Doctrine\DBAL\Events;
use PHPUnit\Framework\TestCase;

class MysqlSessionInitTest extends TestCase
{
    public function testPostConnect() : void
    {
        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->expects(self::once())
                       ->method('executeUpdate')
                       ->with(self::equalTo('SET NAMES foo COLLATE bar'));

        $eventArgs = new ConnectionEventArgs($connectionMock);

        $listener = new MysqlSessionInit('foo', 'bar');
        $listener->postConnect($eventArgs);
    }

    public function testGetSubscribedEvents() : void
    {
        $listener = new MysqlSessionInit();
        self::assertEquals([Events::postConnect], $listener->getSubscribedEvents());
    }
}
