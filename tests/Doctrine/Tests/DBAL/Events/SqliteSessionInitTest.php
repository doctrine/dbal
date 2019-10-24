<?php

namespace Doctrine\Tests\DBAL\Events;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Event\Listeners\SqliteSessionInit;
use Doctrine\DBAL\Events;
use Doctrine\Tests\DbalTestCase;

class SqliteSessionInitTest extends DbalTestCase
{
    public function testPostConnect(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->expects($this->once())
                       ->method('exec')
                       ->with('PRAGMA foreign_keys = on');

        $listener = new SqliteSessionInit();
        $listener->postConnect(new ConnectionEventArgs($connectionMock));
    }

    public function testGetSubscribedEvents(): void
    {
        $listener = new SqliteSessionInit();
        self::assertEquals(array(Events::postConnect), $listener->getSubscribedEvents());
    }
}
