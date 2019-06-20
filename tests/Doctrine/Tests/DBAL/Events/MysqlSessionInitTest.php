<?php

namespace Doctrine\Tests\DBAL\Events;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Event\Listeners\MysqlSessionInit;
use Doctrine\DBAL\Events;
use Doctrine\Tests\DbalTestCase;

class MysqlSessionInitTest extends DbalTestCase
{
    public function testPostConnect() : void
    {
        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->expects($this->once())
                       ->method('executeUpdate')
                       ->with($this->equalTo('SET NAMES foo COLLATE bar'));

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
