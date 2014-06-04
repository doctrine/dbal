<?php

namespace Doctrine\Tests\DBAL\Events;

use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Event\Listeners\SQLSessionInit;
use Doctrine\DBAL\Events;
use Doctrine\Tests\DbalTestCase;

require_once __DIR__ . '/../../TestInit.php';

/**
 * @group DBAL-169
 */
class SQLSessionInitTest extends DbalTestCase
{
    public function testPostConnect()
    {
        $connectionMock = $this->getMock('Doctrine\DBAL\Connection', array(), array(), '', false);
        $connectionMock->expects($this->once())
                       ->method('exec')
                       ->with($this->equalTo("SET SEARCH_PATH TO foo, public, TIMEZONE TO 'Europe/Berlin'"));

        $eventArgs = new ConnectionEventArgs($connectionMock);

        $listener = new SQLSessionInit("SET SEARCH_PATH TO foo, public, TIMEZONE TO 'Europe/Berlin'");
        $listener->postConnect($eventArgs);
    }

    public function testGetSubscribedEvents()
    {
        $listener = new SQLSessionInit("SET SEARCH_PATH TO foo, public, TIMEZONE TO 'Europe/Berlin'");
        $this->assertEquals(array(Events::postConnect), $listener->getSubscribedEvents());
    }
}