<?php

namespace Doctrine\DBAL\Tests\Driver\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use PHPUnit\Framework\TestCase;

final class AbstractDriverMiddlewareTest extends TestCase
{
    public function testConnect(): void
    {
        $connection = $this->createMock(Connection::class);
        $driver     = $this->createMock(Driver::class);
        $driver->expects(self::once())
            ->method('connect')
            ->with(['foo' => 'bar'])
            ->willReturn($connection);

        self::assertSame($connection, $this->createMiddleware($driver)->connect(['foo' => 'bar']));
    }

    public function testCreateDatabasePlatformForVersion(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $driver   = $this->createMock(VersionAwarePlatformDriver::class);
        $driver->expects(self::once())
            ->method('createDatabasePlatformForVersion')
            ->with('1.2.3')
            ->willReturn($platform);

        self::assertSame($platform, $this->createMiddleware($driver)->createDatabasePlatformForVersion('1.2.3'));
    }

    public function testCreateDatabasePlatformForVersionWithLegacyDriver(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $driver   = $this->createMock(Driver::class);
        $driver->expects(self::once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        self::assertSame($platform, $this->createMiddleware($driver)->createDatabasePlatformForVersion('1.2.3'));
    }

    private function createMiddleware(Driver $driver): AbstractDriverMiddleware
    {
        return new class ($driver) extends AbstractDriverMiddleware {
        };
    }
}
