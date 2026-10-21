<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception\BatchInsertsDontMatch;
use Doctrine\DBAL\Exception\MaxBoundParamsExceeded;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_fill;
use function intdiv;

class InsertManyTest extends TestCase
{
    private function connection(int $parameters = 4, int $rows = 1000, int $length = 1048576): Connection&MockObject
    {
        $platform = $this->getMockBuilder(SQLitePlatform::class)
            ->onlyMethods(['getMaximumAmountOfBoundParameters', 'getMaximumRowsPerInsert', 'getMaximumInsertSQLLength'])
            ->getMock();
        $platform->method('getMaximumAmountOfBoundParameters')->willReturn($parameters);
        $platform->method('getMaximumRowsPerInsert')->willReturn($rows);
        $platform->method('getMaximumInsertSQLLength')->willReturn($length);
        $driver = self::createStub(Driver::class);
        $driver->method('getDatabasePlatform')->willReturn($platform);

        return $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([[], $driver])
            ->onlyMethods(['executeStatement'])
            ->getMock();
    }

    public function testEmptyInputDoesNotConnect(): void
    {
        $driver = self::createMock(Driver::class);
        $driver->expects(self::never())->method('connect');
        $driver->expects(self::never())->method('getDatabasePlatform');
        self::assertSame(0, (new Connection([], $driver))->insertMany('t', []));
    }

    #[DataProvider('unsupportedOracleLimits')]
    public function testOlderOracleRejectsInsertMany(?int $maxRowsPerInsert, int $rowCount): void
    {
        $driver = self::createStub(Driver::class);
        $driver->method('getDatabasePlatform')->willReturn(new OraclePlatform());
        $connection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([[], $driver])
            ->onlyMethods(['executeStatement'])
            ->getMock();
        $connection->expects(self::never())->method('executeStatement');
        $this->expectException(NotSupported::class);
        $connection->insertMany(
            't',
            array_fill(0, $rowCount, ['a' => 1]),
            maxRowsPerInsert: $maxRowsPerInsert,
            maxInsertSQLLengthInBytes: 1000,
            maximumParameters: 1000,
        );
    }

    /** @return iterable<string, array{int|null, int}> */
    public static function unsupportedOracleLimits(): iterable
    {
        yield 'platform budget' => [null, 2];
        yield 'single-row batches' => [1, 2];
        yield 'larger override' => [2000, 2];
        yield 'single input row' => [null, 1];
    }

    /** @param array<array<string, mixed>> $rows */
    #[DataProvider('invalidRows')]
    public function testAllShapesAreValidatedBeforeWriting(array $rows): void
    {
        $connection = $this->connection(rows: 1);
        $connection->expects(self::never())->method('executeStatement');
        $this->expectException(BatchInsertsDontMatch::class);
        $connection->insertMany('t', $rows);
    }

    /** @return iterable<string, array{array<array<string, mixed>>}> */
    public static function invalidRows(): iterable
    {
        yield 'missing' => [[['a' => 1, 'b' => 2], ['a' => 3]]];
        yield 'extra' => [[['a' => 1], ['a' => 2, 'b' => 3]]];
        yield 'different names' => [[['a' => 1], ['b' => 2]]];
        yield 'empty first row' => [[[], ['a' => 1]]];
        yield 'empty later row' => [[['a' => 1], []]];
    }

    public function testBatchesReorderedNullableRowsAndTypes(): void
    {
        $connection = $this->connection();
        $call       = 0;
        $connection->expects(self::exactly(2))->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $values, array $types) use (&$call): int|string {
                if ($call++ === 0) {
                    self::assertSame('INSERT INTO t (a, b) VALUES (?,?), (?,?)', $sql);
                    self::assertSame([1, null, 2, 'two'], $values);
                    self::assertSame([
                        ParameterType::INTEGER, ParameterType::STRING,
                        ParameterType::INTEGER, ParameterType::STRING,
                    ], $types);

                    return '2';
                }

                self::assertSame('INSERT INTO t (a, b) VALUES (?,?)', $sql);
                self::assertSame([3, 'three'], $values);
                self::assertSame([ParameterType::INTEGER, ParameterType::STRING], $types);

                return 1;
            },
        );
        self::assertSame(3, $connection->insertMany('t', [
            10 => ['a' => 1, 'b' => null],
            20 => ['b' => 'two', 'a' => 2],
            30 => ['a' => 3, 'b' => 'three'],
        ], ['a' => ParameterType::INTEGER]));
    }

    #[DataProvider('batchLimits')]
    public function testRespectsRowAndSQLLengthLimits(int $rows, int $length): void
    {
        $connection = $this->connection(100, $rows, $length);
        $connection->expects(self::exactly(3))->method('executeStatement')
            ->with('INSERT INTO t (a) VALUES (?)', [1], [ParameterType::STRING])->willReturn(1);
        self::assertSame(3, $connection->insertMany('t', [['a' => 1], ['a' => 1], ['a' => 1]]));
    }

    /** @return iterable<string, array{int, int}> */
    public static function batchLimits(): iterable
    {
        yield 'rows' => [1, 1048576];
        yield 'SQL bytes' => [1000, 28];
    }

    #[DataProvider('customLimits')]
    public function testCustomLimits(
        int $platformRows,
        int $platformLength,
        ?int $maxRowsPerInsert,
        ?int $maxInsertSQLLengthInBytes,
        int $batchSize,
    ): void {
        $connection = $this->connection(4, $platformRows, $platformLength);
        $connection->expects(self::exactly(intdiv(2, $batchSize)))->method('executeStatement')
            ->with(
                $batchSize === 1 ? 'INSERT INTO t (a) VALUES (?)' : 'INSERT INTO t (a) VALUES (?), (?)',
                $batchSize === 1 ? [1] : [1, 1],
                $batchSize === 1 ? [ParameterType::STRING] : [ParameterType::STRING, ParameterType::STRING],
            )->willReturn($batchSize);
        self::assertSame(2, $connection->insertMany(
            't',
            [['a' => 1], ['a' => 1]],
            maxRowsPerInsert: $maxRowsPerInsert,
            maxInsertSQLLengthInBytes: $maxInsertSQLLengthInBytes,
        ));
    }

    /** @return iterable<string, array{int, int, int|null, int|null, int}> */
    public static function customLimits(): iterable
    {
        yield 'null uses platform rows' => [1, 100, null, null, 1];
        yield 'null uses platform SQL length' => [2, 28, null, null, 1];
        yield 'lower row budget' => [2, 100, 1, null, 1];
        yield 'higher row budget' => [1, 100, 2, null, 2];
        yield 'lower SQL budget' => [2, 100, null, 28, 1];
        yield 'higher SQL budget' => [2, 28, null, 100, 2];
        yield 'override both budgets' => [1, 1, 2, 100, 2];
    }

    #[DataProvider('invalidLimits')]
    public function testInvalidLimitsDoNotConnect(
        ?int $maxRowsPerInsert,
        ?int $maxInsertSQLLengthInBytes,
        ?int $maximumParameters,
    ): void {
        $driver = self::createMock(Driver::class);
        $driver->expects(self::never())->method('connect');
        $driver->expects(self::never())->method('getDatabasePlatform');
        $this->expectException(InvalidArgumentException::class);
        (new Connection([], $driver))->insertMany(
            't',
            [['a' => 1]],
            maxRowsPerInsert: $maxRowsPerInsert,
            maxInsertSQLLengthInBytes: $maxInsertSQLLengthInBytes,
            maximumParameters: $maximumParameters,
        );
    }

    /** @return iterable<string, array{int|null, int|null, int|null}> */
    public static function invalidLimits(): iterable
    {
        yield 'zero rows' => [0, null, null];
        yield 'negative rows' => [-1, null, null];
        yield 'zero SQL bytes' => [null, 0, null];
        yield 'negative SQL bytes' => [null, -1, null];
        yield 'zero parameters' => [null, null, 0];
        yield 'negative parameters' => [null, null, -1];
    }

    #[DataProvider('parameterLimits')]
    public function testCustomParameterLimit(int $platformLimit, ?int $maximumParameters, int $batchSize): void
    {
        $connection = $this->connection($platformLimit);
        $connection->expects(self::exactly(intdiv(2, $batchSize)))->method('executeStatement')
            ->with(
                $batchSize === 1 ? 'INSERT INTO t (a, b) VALUES (?,?)'
                    : 'INSERT INTO t (a, b) VALUES (?,?), (?,?)',
                array_fill(0, 2 * $batchSize, 1),
                array_fill(0, 2 * $batchSize, ParameterType::STRING),
            )->willReturn($batchSize);
        self::assertSame(2, $connection->insertMany(
            't',
            [['a' => 1, 'b' => 1], ['a' => 1, 'b' => 1]],
            maximumParameters: $maximumParameters,
        ));
    }

    /** @return iterable<string, array{int, int|null, int}> */
    public static function parameterLimits(): iterable
    {
        yield 'platform fallback' => [2, null, 1];
        yield 'lower override' => [4, 2, 1];
        yield 'round down incomplete row' => [4, 3, 1];
        yield 'higher override' => [1, 4, 2];
    }

    public function testSingleRowExceedsCustomParameterLimit(): void
    {
        $connection = $this->connection(100);
        $connection->expects(self::never())->method('executeStatement');
        $this->expectException(MaxBoundParamsExceeded::class);
        $this->expectExceptionMessage('The bound parameter limit is 1, this query uses 2.');
        $connection->insertMany('t', [['a' => 1, 'b' => 2]], maximumParameters: 1);
    }

    /** @param array<array<string, mixed>> $rows */
    #[DataProvider('defaultRows')]
    public function testDefaultRowsAreRejectedWithoutConnecting(array $rows): void
    {
        $driver = self::createMock(Driver::class);
        $driver->expects(self::never())->method('connect');
        $driver->expects(self::never())->method('getDatabasePlatform');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Inserting rows without columns is not supported.');
        (new Connection([], $driver))->insertMany('t', $rows);
    }

    /** @return iterable<string, array{array<array<string, mixed>>}> */
    public static function defaultRows(): iterable
    {
        yield 'one default row' => [[[]]];
        yield 'multiple default rows' => [[[], []]];
    }

    public function testSingleRowExceedsParameterLimit(): void
    {
        $connection = $this->connection(1);
        $connection->expects(self::never())->method('executeStatement');
        $this->expectException(MaxBoundParamsExceeded::class);
        $this->expectExceptionMessage('The bound parameter limit is 1, this query uses 2.');
        $connection->insertMany('t', [['a' => 1, 'b' => 2]], maxRowsPerInsert: 100, maxInsertSQLLengthInBytes: 1000);
    }

    public function testSingleRowExceedsSQLLengthLimit(): void
    {
        $connection = $this->connection(length: 1);
        $connection->expects(self::never())->method('executeStatement');
        $this->expectException(InvalidArgumentException::class);
        $connection->insertMany('t', [['a' => 1]]);
    }

    public function testStopsWhenABatchFails(): void
    {
        $connection = $this->connection(rows: 1);
        $calls      = 0;
        $connection->expects(self::exactly(2))->method('executeStatement')->willReturnCallback(
            static function () use (&$calls): int {
                if (++$calls === 2) {
                    throw new RuntimeException('Insert failed');
                }

                return 1;
            },
        );
        $this->expectExceptionMessage('Insert failed');
        $connection->insertMany('t', [['a' => 1], ['a' => 2], ['a' => 3]]);
    }
}
