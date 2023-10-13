<?php

namespace Doctrine\DBAL\Tests\Portability;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Portability\Connection;
use Doctrine\DBAL\Portability\Converter;
use LogicException;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    public function testGetServerVersion(): void
    {
        $driverConnection = $this->createMock(ServerInfoAwareConnection::class);
        $driverConnection->expects(self::once())
            ->method('getServerVersion')
            ->willReturn('1.2.3');

        $connection = new Connection($driverConnection, new Converter(false, false, Converter::CASE_LOWER));

        self::assertSame('1.2.3', $connection->getServerVersion());
    }

    public function testGetServerVersionFailsWithLegacyConnection(): void
    {
        $connection = new Connection(
            $this->createMock(DriverConnection::class),
            new Converter(false, false, Converter::CASE_LOWER),
        );

        $this->expectException(LogicException::class);
        $connection->getServerVersion();
    }

    public function testGetNativeConnection(): void
    {
        $nativeConnection = new class () {
        };

        $driverConnection = $this->createMock(NativeDriverConnection::class);
        $driverConnection->method('getNativeConnection')
            ->willReturn($nativeConnection);

        $connection = new Connection($driverConnection, new Converter(false, false, Converter::CASE_LOWER));

        self::assertSame($nativeConnection, $connection->getNativeConnection());
    }

    public function testGetNativeConnectionFailsWithLegacyConnection(): void
    {
        $connection = new Connection(
            $this->createMock(DriverConnection::class),
            new Converter(false, false, Converter::CASE_LOWER),
        );

        $this->expectException(LogicException::class);
        $connection->getNativeConnection();
    }
}

interface NativeDriverConnection extends ServerInfoAwareConnection
{
    /** @return object|resource */
    public function getNativeConnection();
}
