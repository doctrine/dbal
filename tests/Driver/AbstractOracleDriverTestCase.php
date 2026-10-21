<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Platforms\Oracle23Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\ServerVersionProvider;
use PHPUnit\Framework\Attributes\DataProvider;

/** @extends AbstractDriverTestCase<OraclePlatform> */
abstract class AbstractOracleDriverTestCase extends AbstractDriverTestCase
{
    /** @param class-string<OraclePlatform> $expectedClass */
    #[DataProvider('serverVersions')]
    public function testPlatformVersion(string $version, string $expectedClass): void
    {
        $provider = self::createStub(ServerVersionProvider::class);
        $provider->method('getServerVersion')->willReturn($version);
        self::assertSame($expectedClass, $this->driver->getDatabasePlatform($provider)::class);
    }

    /** @return iterable<array{string, class-string<OraclePlatform>}> */
    public static function serverVersions(): iterable
    {
        yield ['18.0.0.0.0', OraclePlatform::class];
        yield ['21.0.0.0.0', OraclePlatform::class];
        yield ['23.0.0.0.0', Oracle23Platform::class];
        yield ['23.9.0.0.0', Oracle23Platform::class];
    }
}
