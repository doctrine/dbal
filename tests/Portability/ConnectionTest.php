<?php

namespace Doctrine\DBAL\Tests\Portability;

use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Portability\Connection;
use Doctrine\DBAL\Portability\Converter;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    public function testGetServerVersion(): void
    {
        $driverConnection = $this->createMock(ServerInfoAwareConnection::class);
        $driverConnection->expects(self::once())
            ->method('getServerVersion')
            ->willReturn('1.2.3');

        $connection = new Connection($driverConnection, new Converter(false, false, 0));

        self::assertSame('1.2.3', $connection->getServerVersion());
    }
}
