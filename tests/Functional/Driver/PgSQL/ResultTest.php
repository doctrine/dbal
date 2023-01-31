<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PgSQL;

use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use Generator;

use function chr;

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

    /**
     * @param mixed $expectedValue
     *
     * @dataProvider provideTypedValues
     */
    public function testTypeConversionFetchAssociative(string $postgresType, $expectedValue, string $dbalType): void
    {
        $id = $this->prepareTypesTestTable($postgresType, $expectedValue, $dbalType);

        $result = $this->connection->fetchAssociative(
            'SELECT my_value, my_null FROM types_test WHERE id = ?',
            [$id],
        );

        self::assertSame(['my_value' => $expectedValue, 'my_null' => null], $result);
    }

    /**
     * @param mixed $expectedValue
     *
     * @dataProvider provideTypedValues
     */
    public function testTypeConversionFetchAllAssociative(string $postgresType, $expectedValue, string $dbalType): void
    {
        $id = $this->prepareTypesTestTable($postgresType, $expectedValue, $dbalType);

        $result = $this->connection->fetchAllAssociative(
            'SELECT my_value, my_null FROM types_test WHERE id = ?',
            [$id],
        );

        self::assertSame([['my_value' => $expectedValue, 'my_null' => null]], $result);
    }

    /**
     * @param mixed $expectedValue
     *
     * @dataProvider provideTypedValues
     */
    public function testTypeConversionFetchNumeric(string $postgresType, $expectedValue, string $dbalType): void
    {
        $id = $this->prepareTypesTestTable($postgresType, $expectedValue, $dbalType);

        $result = $this->connection->fetchNumeric(
            'SELECT my_value, my_null FROM types_test WHERE id = ?',
            [$id],
        );

        self::assertSame([$expectedValue, null], $result);
    }

    /**
     * @param mixed $expectedValue
     *
     * @dataProvider provideTypedValues
     */
    public function testTypeConversionFetchAllNumeric(string $postgresType, $expectedValue, string $dbalType): void
    {
        $id = $this->prepareTypesTestTable($postgresType, $expectedValue, $dbalType);

        $result = $this->connection->fetchAllNumeric(
            'SELECT my_value, my_null FROM types_test WHERE id = ?',
            [$id],
        );

        self::assertSame([[$expectedValue, null]], $result);
    }

    /**
     * @param mixed $expectedValue
     *
     * @dataProvider provideTypedValues
     */
    public function testTypeConversionFetchOne(string $postgresType, $expectedValue, string $dbalType): void
    {
        $id = $this->prepareTypesTestTable($postgresType, $expectedValue, $dbalType);

        $result = $this->connection->fetchOne(
            'SELECT my_value FROM types_test WHERE id = ?',
            [$id],
        );

        self::assertSame($expectedValue, $result);
    }

    /**
     * @param mixed $expectedValue
     *
     * @dataProvider provideTypedValues
     */
    public function testTypeConversionFetchFirstColumn(string $postgresType, $expectedValue, string $dbalType): void
    {
        $id = $this->prepareTypesTestTable($postgresType, $expectedValue, $dbalType);

        $result = $this->connection->fetchFirstColumn(
            'SELECT my_value FROM types_test WHERE id = ?',
            [$id],
        );

        self::assertSame([$expectedValue], $result);
    }

    /** @psalm-return Generator<string, array{string, mixed, (Types::*)}> */
    public function provideTypedValues(): Generator
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

    /** @param mixed $expectedValue */
    private function prepareTypesTestTable(string $postgresType, $expectedValue, string $dbalType): int
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
}
