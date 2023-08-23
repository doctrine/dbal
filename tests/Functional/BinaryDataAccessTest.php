<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;

use function array_change_key_case;
use function array_keys;
use function array_map;
use function hex2bin;
use function is_resource;
use function stream_get_contents;

use const CASE_LOWER;

class BinaryDataAccessTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('pdo_oci')) {
            self::markTestSkipped("PDO_OCI doesn't support binding binary values");
        }

        $table = new Table('binary_fetch_table');
        $table->addColumn('test_int', 'integer');
        $table->addColumn('test_binary', 'binary', ['notnull' => false, 'length' => 4]);
        $table->setPrimaryKey(['test_int']);

        $this->dropAndCreateTable($table);

        $this->connection->insert('binary_fetch_table', [
            'test_int' => 1,
            'test_binary' => hex2bin('C0DEF00D'),
        ], [
            'test_binary' => ParameterType::BINARY,
        ]);
    }

    public function testPrepareWithBindValue(): void
    {
        $sql  = 'SELECT test_int, test_binary FROM binary_fetch_table WHERE test_int = ? AND test_binary = ?';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1);
        $stmt->bindValue(2, hex2bin('C0DEF00D'), ParameterType::BINARY);

        $row = $stmt->executeQuery()->fetchAssociative();

        self::assertIsArray($row);
        $row = array_change_key_case($row, CASE_LOWER);
        self::assertEquals(['test_int', 'test_binary'], array_keys($row));
        self::assertEquals(1, $row['test_int']);

        $binaryResult = $row['test_binary'];
        if (is_resource($binaryResult)) {
            $binaryResult = stream_get_contents($binaryResult);
        }

        self::assertEquals(hex2bin('C0DEF00D'), $binaryResult);
    }

    public function testPrepareWithFetchAllAssociative(): void
    {
        $paramInt = 1;
        $paramBin = hex2bin('C0DEF00D');

        $sql  = 'SELECT test_int, test_binary FROM binary_fetch_table WHERE test_int = ? AND test_binary = ?';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, $paramInt);
        $stmt->bindValue(2, $paramBin, ParameterType::BINARY);

        $rows    = $stmt->executeQuery()->fetchAllAssociative();
        $rows[0] = array_change_key_case($rows[0], CASE_LOWER);

        self::assertEquals(['test_int', 'test_binary'], array_keys($rows[0]));
        self::assertEquals(1, $rows[0]['test_int']);

        $binaryResult = $rows[0]['test_binary'];
        if (is_resource($binaryResult)) {
            $binaryResult = stream_get_contents($binaryResult);
        }

        self::assertEquals(hex2bin('C0DEF00D'), $binaryResult);
    }

    public function testPrepareWithFetchOne(): void
    {
        $paramInt = 1;
        $paramBin = hex2bin('C0DEF00D');

        $sql  = 'SELECT test_int FROM binary_fetch_table WHERE test_int = ? AND test_binary = ?';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, $paramInt);
        $stmt->bindValue(2, $paramBin, ParameterType::BINARY);

        $column = $stmt->executeQuery()->fetchOne();
        self::assertEquals(1, $column);
    }

    public function testFetchAllAssociative(): void
    {
        $sql  = 'SELECT test_int, test_binary FROM binary_fetch_table WHERE test_int = ? AND test_binary = ?';
        $data = $this->connection->fetchAllAssociative($sql, [1, hex2bin('C0DEF00D')], [1 => ParameterType::BINARY]);

        self::assertCount(1, $data);

        $row = $data[0];
        self::assertCount(2, $row);

        $row = array_change_key_case($row, CASE_LOWER);
        self::assertEquals(1, $row['test_int']);

        $binaryResult = $row['test_binary'];
        if (is_resource($binaryResult)) {
            $binaryResult = stream_get_contents($binaryResult);
        }

        self::assertEquals(hex2bin('C0DEF00D'), $binaryResult);
    }

    public function testFetchAllWithTypes(): void
    {
        $sql  = 'SELECT test_int, test_binary FROM binary_fetch_table WHERE test_int = ? AND test_binary = ?';
        $data = $this->connection->fetchAllAssociative(
            $sql,
            [1, hex2bin('C0DEF00D')],
            [ParameterType::STRING, Types::BINARY],
        );

        self::assertCount(1, $data);

        $row = $data[0];
        self::assertCount(2, $row);

        $row = array_change_key_case($row, CASE_LOWER);
        self::assertEquals(1, $row['test_int']);

        $binaryResult = $row['test_binary'];
        if (is_resource($binaryResult)) {
            $binaryResult = stream_get_contents($binaryResult);
        }

        self::assertEquals(hex2bin('C0DEF00D'), $binaryResult);
    }

    public function testFetchAssociative(): void
    {
        $sql = 'SELECT test_int, test_binary FROM binary_fetch_table WHERE test_int = ? AND test_binary = ?';
        $row = $this->connection->fetchAssociative($sql, [1, hex2bin('C0DEF00D')], [1 => ParameterType::BINARY]);

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row['test_int']);

        $binaryResult = $row['test_binary'];
        if (is_resource($binaryResult)) {
            $binaryResult = stream_get_contents($binaryResult);
        }

        self::assertEquals(hex2bin('C0DEF00D'), $binaryResult);
    }

    public function testFetchAssocWithTypes(): void
    {
        $sql = 'SELECT test_int, test_binary FROM binary_fetch_table WHERE test_int = ? AND test_binary = ?';
        $row = $this->connection->fetchAssociative(
            $sql,
            [1, hex2bin('C0DEF00D')],
            [ParameterType::STRING, Types::BINARY],
        );

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row['test_int']);

        $binaryResult = $row['test_binary'];
        if (is_resource($binaryResult)) {
            $binaryResult = stream_get_contents($binaryResult);
        }

        self::assertEquals(hex2bin('C0DEF00D'), $binaryResult);
    }

    public function testFetchArray(): void
    {
        $sql = 'SELECT test_int, test_binary FROM binary_fetch_table WHERE test_int = ? AND test_binary = ?';
        $row = $this->connection->fetchNumeric($sql, [1, hex2bin('C0DEF00D')], [1 => ParameterType::BINARY]);
        self::assertNotFalse($row);

        self::assertEquals(1, $row[0]);

        $binaryResult = $row[1];
        if (is_resource($binaryResult)) {
            $binaryResult = stream_get_contents($binaryResult);
        }

        self::assertEquals(hex2bin('C0DEF00D'), $binaryResult);
    }

    public function testFetchArrayWithTypes(): void
    {
        $sql = 'SELECT test_int, test_binary FROM binary_fetch_table WHERE test_int = ? AND test_binary = ?';
        $row = $this->connection->fetchNumeric(
            $sql,
            [1, hex2bin('C0DEF00D')],
            [ParameterType::STRING, Types::BINARY],
        );

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row[0]);

        $binaryResult = $row[1];
        if (is_resource($binaryResult)) {
            $binaryResult = stream_get_contents($binaryResult);
        }

        self::assertEquals(hex2bin('C0DEF00D'), $binaryResult);
    }

    public function testFetchColumn(): void
    {
        $sql     = 'SELECT test_int FROM binary_fetch_table WHERE test_int = ? AND test_binary = ?';
        $testInt = $this->connection->fetchOne($sql, [1, hex2bin('C0DEF00D')], [1 => ParameterType::BINARY]);

        self::assertEquals(1, $testInt);

        $sql        = 'SELECT test_binary FROM binary_fetch_table WHERE test_int = ? AND test_binary = ?';
        $testBinary = $this->connection->fetchOne($sql, [1, hex2bin('C0DEF00D')], [1 => ParameterType::BINARY]);

        if (is_resource($testBinary)) {
            $testBinary = stream_get_contents($testBinary);
        }

        self::assertEquals(hex2bin('C0DEF00D'), $testBinary);
    }

    public function testFetchOneWithTypes(): void
    {
        $sql    = 'SELECT test_binary FROM binary_fetch_table WHERE test_int = ? AND test_binary = ?';
        $column = $this->connection->fetchOne(
            $sql,
            [1, hex2bin('C0DEF00D')],
            [ParameterType::STRING, Types::BINARY],
        );

        if (is_resource($column)) {
            $column = stream_get_contents($column);
        }

        self::assertIsString($column);
        self::assertEquals(hex2bin('C0DEF00D'), $column);
    }

    public function testNativeArrayListSupport(): void
    {
        $binaryValues = [
            hex2bin('A0AEFA'),
            hex2bin('1F43BA'),
            hex2bin('8C9D2A'),
            hex2bin('72E8AA'),
            hex2bin('5B6F9A'),
            hex2bin('DAB24A'),
            hex2bin('3E71CA'),
            hex2bin('F0D6EA'),
            hex2bin('6A8B5A'),
            hex2bin('C582FA'),
        ];

        for ($i = 100; $i < 110; $i++) {
            $this->connection->insert('binary_fetch_table', [
                'test_int' => $i,
                'test_binary' => $binaryValues[$i - 100],
            ], [
                'test_binary' => ParameterType::BINARY,
            ]);
        }

        $result = $this->connection->executeQuery(
            'SELECT test_int FROM binary_fetch_table WHERE test_int IN (?)',
            [[100, 101, 102, 103, 104]],
            [ArrayParameterType::INTEGER],
        );

        $data = $result->fetchAllNumeric();
        self::assertCount(5, $data);
        self::assertEquals([[100], [101], [102], [103], [104]], $data);

        $result = $this->connection->executeQuery(
            'SELECT test_int FROM binary_fetch_table WHERE test_binary IN (?)',
            [
                [
                    $binaryValues[0],
                    $binaryValues[1],
                    $binaryValues[2],
                    $binaryValues[3],
                    $binaryValues[4],
                ],
            ],
            [ArrayParameterType::BINARY],
        );

        $data = $result->fetchAllNumeric();
        self::assertCount(5, $data);
        self::assertEquals([[100], [101], [102], [103], [104]], $data);

        $result = $this->connection->executeQuery(
            'SELECT test_binary FROM binary_fetch_table WHERE test_binary IN (?)',
            [
                [
                    $binaryValues[0],
                    $binaryValues[1],
                    $binaryValues[2],
                    $binaryValues[3],
                    $binaryValues[4],
                ],
            ],
            [ArrayParameterType::BINARY],
        );

        $data = $result->fetchFirstColumn();
        self::assertCount(5, $data);

        $data = array_map(
            static fn ($binaryField) => is_resource($binaryField)
                ? stream_get_contents($binaryField)
                : $binaryField,
            $data,
        );

        self::assertEquals([
            $binaryValues[0],
            $binaryValues[1],
            $binaryValues[2],
            $binaryValues[3],
            $binaryValues[4],
        ], $data);
    }
}
