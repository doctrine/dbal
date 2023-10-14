<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2111Platform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MariaDb1052Platform;
use Doctrine\DBAL\Platforms\MariaDb1060Platform;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\TestCase;

use function get_class;

class VersionAwarePlatformDriverTest extends TestCase
{
    use VerifyDeprecations;

    /** @dataProvider mySQLVersionProvider */
    public function testMySQLi(string $version, string $expectedClass): void
    {
        $this->assertDriverInstantiatesDatabasePlatform(new Driver\Mysqli\Driver(), $version, $expectedClass);
    }

    /** @dataProvider mySQLVersionProvider */
    public function testPDOMySQL(string $version, string $expectedClass): void
    {
        $this->assertDriverInstantiatesDatabasePlatform(new Driver\PDO\MySQL\Driver(), $version, $expectedClass);
    }

    /** @return array<array{string, class-string<AbstractPlatform>}> */
    public static function mySQLVersionProvider(): array
    {
        return [
            ['5.6.9', MySQLPlatform::class],
            ['5.7', MySQL57Platform::class],
            ['5.7.0', MySQLPlatform::class],
            ['5.7.8', MySQLPlatform::class],
            ['5.7.9', MySQL57Platform::class],
            ['5.7.10', MySQL57Platform::class],
            ['8', MySQL80Platform::class],
            ['8.0', MySQL80Platform::class],
            ['8.0.11', MySQL80Platform::class],
            ['6', MySQL57Platform::class],
            ['10.0.15-MariaDB-1~wheezy', MySQLPlatform::class],
            ['5.5.5-10.1.25-MariaDB', MySQLPlatform::class],
            ['10.1.2a-MariaDB-a1~lenny-log', MySQLPlatform::class],
            ['5.5.40-MariaDB-1~wheezy', MySQLPlatform::class],
            ['5.5.5-MariaDB-10.2.8+maria~xenial-log', MariaDb1027Platform::class],
            ['10.2.8-MariaDB-10.2.8+maria~xenial-log', MariaDb1027Platform::class],
            ['10.2.8-MariaDB-1~lenny-log', MariaDb1027Platform::class],
            ['10.5.2-MariaDB-1~lenny-log', MariaDB1052Platform::class],
            ['mariadb-10.6.0', MariaDb1060Platform::class],
            ['mariadb-10.9.3', MariaDb1060Platform::class],
            ['11.0.2-MariaDB-1:11.0.2+maria~ubu2204', MariaDb1060Platform::class],
        ];
    }

    /** @dataProvider postgreSQLVersionProvider */
    public function testPgSQL(string $version, string $expectedClass): void
    {
        $this->assertDriverInstantiatesDatabasePlatform(new Driver\PgSQL\Driver(), $version, $expectedClass);
    }

    /** @dataProvider postgreSQLVersionProvider */
    public function testPDOPgSQL(string $version, string $expectedClass): void
    {
        $this->assertDriverInstantiatesDatabasePlatform(new Driver\PDO\PgSQL\Driver(), $version, $expectedClass);
    }

    /** @return array<array{string, class-string<AbstractPlatform>}> */
    public static function postgreSQLVersionProvider(): array
    {
        return [
            ['9.4', PostgreSQL94Platform::class],
            ['9.4.0', PostgreSQL94Platform::class],
            ['9.4.1', PostgreSQL94Platform::class],
            ['10', PostgreSQL100Platform::class],
        ];
    }

    /** @dataProvider db2VersionProvider */
    public function testIBMDB2(string $version, string $expectedClass): void
    {
        $this->assertDriverInstantiatesDatabasePlatform(new Driver\IBMDB2\Driver(), $version, $expectedClass);
    }

    /** @return array<array{string, class-string<AbstractPlatform>}> */
    public static function db2VersionProvider(): array
    {
        return [
            ['10.1.0', DB2Platform::class],
            ['10.1.0.0', DB2Platform::class],
            ['DB2/LINUXX8664 10.1.0.0', DB2Platform::class],
            ['11.1.0', DB2111Platform::class],
            ['11.1.0.0', DB2111Platform::class],
            ['DB2/LINUXX8664 11.1.0.0', DB2111Platform::class],
            ['11.5.8', DB2111Platform::class],
            ['11.5.8.0', DB2111Platform::class],
            ['DB2/LINUXX8664 11.5.8.0', DB2111Platform::class],
        ];
    }

    private function assertDriverInstantiatesDatabasePlatform(
        VersionAwarePlatformDriver $driver,
        string $version,
        string $expectedClass,
        ?string $deprecation = null,
        ?bool $expectDeprecation = null
    ): void {
        if ($deprecation !== null) {
            if ($expectDeprecation ?? true) {
                $this->expectDeprecationWithIdentifier($deprecation);
            } else {
                $this->expectNoDeprecationWithIdentifier($deprecation);
            }
        }

        $platform = $driver->createDatabasePlatformForVersion($version);

        self::assertSame($expectedClass, get_class($platform));
    }
}
