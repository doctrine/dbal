<?php

namespace Doctrine\DBAL\Tests\Driver\Middleware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement;
use LogicException;
use PHPUnit\Framework\TestCase;

final class AbstractConnectionMiddlewareTest extends TestCase
{
    public function testPrepare(): void
    {
        $statement  = $this->createMock(Statement::class);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('prepare')
            ->with('SELECT 1')
            ->willReturn($statement);

        self::assertSame($statement, $this->createMiddleware($connection)->prepare('SELECT 1'));
    }

    public function testQuery(): void
    {
        $result     = $this->createMock(Result::class);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('query')
            ->with('SELECT 1')
            ->willReturn($result);

        self::assertSame($result, $this->createMiddleware($connection)->query('SELECT 1'));
    }

    public function testExec(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('exec')
            ->with('UPDATE foo SET bar=\'baz\' WHERE some_field > 0')
            ->willReturn(42);

        self::assertSame(
            42,
            $this->createMiddleware($connection)->exec('UPDATE foo SET bar=\'baz\' WHERE some_field > 0'),
        );
    }

    public function testGetServerVersion(): void
    {
        $connection = $this->createMock(ServerInfoAwareConnection::class);
        $connection->expects(self::once())
            ->method('getServerVersion')
            ->willReturn('1.2.3');

        self::assertSame('1.2.3', $this->createMiddleware($connection)->getServerVersion());
    }

    public function testGetServerVersionFailsOnLegacyConnections(): void
    {
        $connection = $this->createMock(Connection::class);
        $middleware = $this->createMiddleware($connection);

        $this->expectException(LogicException::class);
        $middleware->getServerVersion();
    }

    public function testGetNativeConnection(): void
    {
        $nativeConnection = new class () {
        };

        $connection = $this->createMock(NativeDriverConnection::class);
        $connection->method('getNativeConnection')
            ->willReturn($nativeConnection);

        self::assertSame($nativeConnection, $this->createMiddleware($connection)->getNativeConnection());
    }

    public function testGetNativeConnectionFailsWithLegacyConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $middleware = $this->createMiddleware($connection);

        $this->expectException(LogicException::class);
        $middleware->getNativeConnection();
    }

    private function createMiddleware(Connection $connection): AbstractConnectionMiddleware
    {
        return new class ($connection) extends AbstractConnectionMiddleware {
        };
    }
}

interface NativeDriverConnection extends ServerInfoAwareConnection
{
    /** @return object|resource */
    public function getNativeConnection();
}
