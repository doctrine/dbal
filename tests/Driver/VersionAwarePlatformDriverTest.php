<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection\StaticServerVersionProvider;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDB1052Platform;
use Doctrine\DBAL\Platforms\MariaDB1060Platform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VersionAwarePlatformDriverTest extends TestCase
{
    #[DataProvider('mySQLVersionProvider')]
    public function testMySQLi(string $version, string $expectedClass): void
    {
        $this->assertDriverInstantiatesDatabasePlatform(new Driver\Mysqli\Driver(), $version, $expectedClass);
    }

    #[DataProvider('mySQLVersionProvider')]
    public function testPDOMySQL(string $version, string $expectedClass): void
    {
        $this->assertDriverInstantiatesDatabasePlatform(new Driver\PDO\MySQL\Driver(), $version, $expectedClass);
    }

    /** @return array<array{string, class-string<AbstractPlatform>}> */
    public static function mySQLVersionProvider(): array
    {
        return [
            ['5.7.0', MySQLPlatform::class],
            ['8.0.11', MySQL80Platform::class],
            ['5.5.40-MariaDB-1~wheezy', MariaDBPlatform::class],
            ['5.5.5-MariaDB-10.2.8+maria~xenial-log', MariaDBPlatform::class],
            ['10.2.8-MariaDB-10.2.8+maria~xenial-log', MariaDBPlatform::class],
            ['10.2.8-MariaDB-1~lenny-log', MariaDBPlatform::class],
            ['10.5.2-MariaDB-1~lenny-log', MariaDB1052Platform::class],
            ['10.6.0-MariaDB-1~lenny-log', MariaDB1060Platform::class],
            ['11.0.2-MariaDB-1:11.0.2+maria~ubu2204', MariaDB1060Platform::class],
        ];
    }

    #[DataProvider('postgreSQLVersionProvider')]
    public function testPgSQL(string $version, string $expectedClass): void
    {
        $this->assertDriverInstantiatesDatabasePlatform(new Driver\PgSQL\Driver(), $version, $expectedClass);
    }

    #[DataProvider('postgreSQLVersionProvider')]
    public function testPDOPgSQL(string $version, string $expectedClass): void
    {
        $this->assertDriverInstantiatesDatabasePlatform(new Driver\PDO\PgSQL\Driver(), $version, $expectedClass);
    }

    /** @return array<array{string, class-string<AbstractPlatform>}> */
    public static function postgreSQLVersionProvider(): array
    {
        return [
            ['10.0', PostgreSQLPlatform::class],
            ['11.0', PostgreSQLPlatform::class],
            ['13.3', PostgreSQLPlatform::class],
        ];
    }

    private function assertDriverInstantiatesDatabasePlatform(
        Driver $driver,
        string $version,
        string $expectedClass,
    ): void {
        $platform = $driver->getDatabasePlatform(
            new StaticServerVersionProvider($version),
        );

        self::assertSame($expectedClass, $platform::class);
    }
}
