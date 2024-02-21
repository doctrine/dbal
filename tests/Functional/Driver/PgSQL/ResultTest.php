<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PgSQL;

use Doctrine\DBAL\Driver\PgSQL\Result;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use Error;
use Generator;
use PgSql\Connection as PgSqlConnection;
use PHPUnit\Framework\Attributes\DataProvider;

use function assert;
use function chr;
use function pg_query;
use function pg_result_status;

use const PGSQL_TUPLES_OK;

class ResultTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pgsql')) {
            return;
        }

        self::markTestSkipped('This test requires the pgsql driver.');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS types_test');
        $this->connection->executeStatement('DROP TABLE IF EXISTS types_test2');

        parent::tearDown();
    }

    #[DataProvider('typedValueProvider')]
    public function testTypeConversionFetchAssociative(
        string $postgresType,
        mixed $expectedValue,
        string $dbalType,
    ): void {
        $id = $this->prepareTypesTestTable($postgresType, $expectedValue, $dbalType);

        $result = $this->connection->fetchAssociative(
            'SELECT my_value, my_null FROM types_test WHERE id = ?',
            [$id],
        );

        self::assertSame(['my_value' => $expectedValue, 'my_null' => null], $result);
    }

    #[DataProvider('typedValueProvider')]
    public function testTypeConversionFetchAllAssociative(
        string $postgresType,
        mixed $expectedValue,
        string $dbalType,
    ): void {
        $id = $this->prepareTypesTestTable($postgresType, $expectedValue, $dbalType);

        $result = $this->connection->fetchAllAssociative(
            'SELECT my_value, my_null FROM types_test WHERE id = ?',
            [$id],
        );

        self::assertSame([['my_value' => $expectedValue, 'my_null' => null]], $result);
    }

    #[DataProvider('typedValueProvider')]
    public function testTypeConversionFetchNumeric(string $postgresType, mixed $expectedValue, string $dbalType): void
    {
        $id = $this->prepareTypesTestTable($postgresType, $expectedValue, $dbalType);

        $result = $this->connection->fetchNumeric(
            'SELECT my_value, my_null FROM types_test WHERE id = ?',
            [$id],
        );

        self::assertSame([$expectedValue, null], $result);
    }

    #[DataProvider('typedValueProvider')]
    public function testTypeConversionFetchAllNumeric(
        string $postgresType,
        mixed $expectedValue,
        string $dbalType,
    ): void {
        $id = $this->prepareTypesTestTable($postgresType, $expectedValue, $dbalType);

        $result = $this->connection->fetchAllNumeric(
            'SELECT my_value, my_null FROM types_test WHERE id = ?',
            [$id],
        );

        self::assertSame([[$expectedValue, null]], $result);
    }

    #[DataProvider('typedValueProvider')]
    public function testTypeConversionFetchOne(string $postgresType, mixed $expectedValue, string $dbalType): void
    {
        $id = $this->prepareTypesTestTable($postgresType, $expectedValue, $dbalType);

        $result = $this->connection->fetchOne(
            'SELECT my_value FROM types_test WHERE id = ?',
            [$id],
        );

        self::assertSame($expectedValue, $result);
    }

    #[DataProvider('typedValueProvider')]
    public function testTypeConversionFetchFirstColumn(
        string $postgresType,
        mixed $expectedValue,
        string $dbalType,
    ): void {
        $id = $this->prepareTypesTestTable($postgresType, $expectedValue, $dbalType);

        $result = $this->connection->fetchFirstColumn(
            'SELECT my_value FROM types_test WHERE id = ?',
            [$id],
        );

        self::assertSame([$expectedValue], $result);
    }

    /** @psalm-return Generator<string, array{string, mixed, (Types::*)}> */
    public static function typedValueProvider(): Generator
    {
        yield 'integer' => ['INTEGER', 4711, Types::INTEGER];
        yield 'negative integer' => ['INTEGER', -4711, Types::INTEGER];
        yield 'bigint' => ['BIGINT', 4711, Types::BIGINT];
        yield 'smallint' => ['SMALLINT', 4711, Types::SMALLINT];
        yield 'string' => ['CHARACTER VARYING (100)', 'some value', Types::STRING];
        yield 'numeric string' => ['CHARACTER VARYING (100)', '4711', Types::STRING];
        yield 'text' => ['TEXT', 'some value', Types::STRING];
        yield 'boolean true' => ['BOOLEAN', true, Types::BOOLEAN];
        yield 'boolean false' => ['BOOLEAN', false, Types::BOOLEAN];
        yield 'float' => ['REAL', 47.11, Types::FLOAT];
        yield 'negative float with exponent' => ['REAL', -8.15e10, Types::FLOAT];
        yield 'double' => ['DOUBLE PRECISION', 47.11, Types::FLOAT];
        yield 'decimal' => ['NUMERIC (6, 2)', '47.11', Types::DECIMAL];
        yield 'binary' => ['BYTEA', chr(0x8b), Types::BINARY];
    }

    private function prepareTypesTestTable(string $postgresType, mixed $expectedValue, string $dbalType): int
    {
        $sql = <<< SQL
            CREATE TABLE types_test (
                id BIGSERIAL PRIMARY KEY,
                my_value {$postgresType} NOT NULL,
                my_null {$postgresType} DEFAULT NULL
            )
            SQL;

        $this->connection->executeStatement($sql);

        $this->connection->insert(
            'types_test',
            ['my_value' => $expectedValue],
            ['my_value' => $dbalType],
        );

        $id = $this->connection->lastInsertId();
        self::assertIsInt($id);

        return $id;
    }

    public function testTypeConversionWithDuplicateFieldNames(): void
    {
        $this->connection->executeStatement(<<< 'SQL'
            CREATE TABLE types_test (
                id INT PRIMARY KEY,
                my_value VARCHAR (20) NOT NULL
            )
            SQL);

        $this->connection->executeStatement(<<< 'SQL'
            CREATE TABLE types_test2 (
                id INT PRIMARY KEY,
                my_other_value VARCHAR (20) NOT NULL
            )
            SQL);

        $this->connection->insert('types_test', ['id' => 4711, 'my_value' => 'some value']);
        $this->connection->insert('types_test2', ['id' => 42, 'my_other_value' => 'another value']);

        self::assertSame(
            ['id' => 42, 'my_value' => 'some value', 'my_other_value' => 'another value'],
            $this->connection->fetchAssociative('SELECT a.*, b.* FROM types_test a, types_test2 b'),
        );
        self::assertSame(
            [['id' => 42, 'my_value' => 'some value', 'my_other_value' => 'another value']],
            $this->connection->fetchAllAssociative('SELECT a.*, b.* FROM types_test a, types_test2 b'),
        );
        self::assertSame(
            [4711, 'some value', 42, 'another value'],
            $this->connection->fetchNumeric('SELECT a.*, b.* FROM types_test a, types_test2 b'),
        );
        self::assertSame(
            [[4711, 'some value', 42, 'another value']],
            $this->connection->fetchAllNumeric('SELECT a.*, b.* FROM types_test a, types_test2 b'),
        );
        self::assertSame(
            4711,
            $this->connection->fetchOne('SELECT a.*, b.* FROM types_test a, types_test2 b'),
        );
        self::assertSame(
            [4711],
            $this->connection->fetchFirstColumn('SELECT a.*, b.* FROM types_test a, types_test2 b'),
        );
    }

    public function testResultIsFreedOnDestruct(): void
    {
        $pgsqlConnection = $this->connection->getNativeConnection();
        assert($pgsqlConnection instanceof PgSqlConnection);
        $pgsqlResult = pg_query($pgsqlConnection, 'SELECT 1');
        assert($pgsqlResult !== false);

        self::assertSame(PGSQL_TUPLES_OK, pg_result_status($pgsqlResult));

        new Result($pgsqlResult);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('PostgreSQL result has already been closed');

        pg_result_status($pgsqlResult);
    }
}
