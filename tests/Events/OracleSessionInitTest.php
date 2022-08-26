<?php

namespace Doctrine\DBAL\Tests\Events;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Event\Listeners\OracleSessionInit;
use Doctrine\DBAL\Events;
use PHPUnit\Framework\TestCase;

use function sprintf;

class OracleSessionInitTest extends TestCase
{
    public function testPostConnect(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->expects(self::once())
                       ->method('executeStatement')
                       ->with(self::isType('string'));

        $eventArgs = new ConnectionEventArgs($connectionMock);

        $listener = new OracleSessionInit();
        $listener->postConnect($eventArgs);
    }

    /** @dataProvider getPostConnectWithSessionParameterValuesData */
    public function testPostConnectQuotesSessionParameterValues(string $name, string $value): void
    {
        $connectionMock = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $connectionMock->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains(sprintf('%s = %s', $name, $value)));

        $eventArgs = new ConnectionEventArgs($connectionMock);

        $listener = new OracleSessionInit([$name => $value]);
        $listener->postConnect($eventArgs);
    }

    /** @return array<int, array<int, mixed>> */
    public static function getPostConnectWithSessionParameterValuesData(): iterable
    {
        return [
            ['CURRENT_SCHEMA', 'foo'],
        ];
    }

    public function testGetSubscribedEvents(): void
    {
        $listener = new OracleSessionInit();
        self::assertEquals([Events::postConnect], $listener->getSubscribedEvents());
    }
}
