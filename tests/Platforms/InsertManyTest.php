<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\OCI8\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\Oracle23Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InsertManyTest extends TestCase
{
    #[DataProvider('platforms')]
    public function testSQL(AbstractPlatform $platform, int $rowLimit): void
    {
        self::assertSame($rowLimit, $platform->getMaximumRowsPerInsert());
        self::assertSame('INSERT INTO t (a, b) VALUES (?,?)', $platform->getInsertManySQL('t', ['a', 'b'], 1));
        if ($rowLimit > 1) {
            self::assertSame(
                'INSERT INTO t (a, b) VALUES (?,?), (?,?)',
                $platform->getInsertManySQL('t', ['a', 'b'], 2),
            );
        }
    }

    /** @return iterable<string, array{AbstractPlatform, int}> */
    public static function platforms(): iterable
    {
        yield 'MySQL' => [new MySQLPlatform(), 1000];
        yield 'PostgreSQL' => [new PostgreSQLPlatform(), 1000];
        yield 'SQLite' => [new SQLitePlatform(), 1000];
        yield 'SQL Server' => [new SQLServerPlatform(), 1000];
        yield 'Db2' => [new DB2Platform(), 1000];
        yield 'Oracle 23' => [new Oracle23Platform(), 1000];
    }

    public function testSQLiteLimitsAreCachedPerConnection(): void
    {
        $connection = self::createMock(Connection::class);
        $connection->expects(self::once())->method('fetchFirstColumn')->with('PRAGMA compile_options')
            ->willReturn(['MAX_VARIABLE_NUMBER=1234', 'MAX_SQL_LENGTH=5678', 'ENABLE_FTS5']);
        $otherConnection = self::createMock(Connection::class);
        $otherConnection->expects(self::once())->method('fetchFirstColumn')->willReturn([]);
        $platform = new SQLitePlatform();
        self::assertSame(1234, $platform->getMaximumAmountOfBoundParameters($connection));
        self::assertSame(5678, $platform->getMaximumInsertSQLLength($connection));
        self::assertSame(1234, $platform->getMaximumAmountOfBoundParameters($connection));
        self::assertSame(999, $platform->getMaximumAmountOfBoundParameters($otherConnection));
        self::assertSame(1000000000, $platform->getMaximumInsertSQLLength($otherConnection));
    }

    #[DataProvider('packetLimits')]
    public function testMySQLSQLBudgetUsesSessionPacketLimit(int $packetLimit): void
    {
        $connection = self::createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->with('SELECT @@max_allowed_packet')
            ->willReturn($packetLimit);
        $otherConnection = self::createMock(Connection::class);
        $otherConnection->expects(self::once())->method('fetchOne')->with('SELECT @@max_allowed_packet')
            ->willReturn(2048);
        $platform = new MySQLPlatform();
        self::assertSame($packetLimit - 1, $platform->getMaximumInsertSQLLength($connection));
        self::assertSame($packetLimit - 1, $platform->getMaximumInsertSQLLength($connection));
        self::assertSame(2047, $platform->getMaximumInsertSQLLength($otherConnection));
        self::assertSame(2047, $platform->getMaximumInsertSQLLength($otherConnection));
    }

    /** @return iterable<string, array{int}> */
    public static function packetLimits(): iterable
    {
        yield 'below fallback' => [1024];
        yield 'above fallback' => [4194304];
    }

    public function testPostgreSQLParameterLimit(): void
    {
        self::assertSame(65535, (new PostgreSQLPlatform())->getMaximumAmountOfBoundParameters(
            self::createStub(Connection::class),
        ));
    }

    public function testOracleVersionSelectionWithoutConnecting(): void
    {
        $provider = self::createStub(ServerVersionProvider::class);
        $provider->method('getServerVersion')->willReturn('21.0.0.0.0', '23.0.0.0.0');
        $driver = new Driver();
        self::assertSame(OraclePlatform::class, $driver->getDatabasePlatform($provider)::class);
        self::assertSame(Oracle23Platform::class, $driver->getDatabasePlatform($provider)::class);
    }
}
