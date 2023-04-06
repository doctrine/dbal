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
use function hex2bin;
use function pack;

use const CASE_LOWER;

class BinaryDataAccessTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('pdo_oci')) {
            self::markTestSkipped("PDO_OCI doesn't support binding binary values");
        }

        $table = new Table('fetch_table');
        $table->addColumn('test_int', 'integer');
        $table->addColumn('test_binary', 'binary', ['notnull' => false, 'length' => 4]);
        $table->setPrimaryKey(['test_int']);

        $this->dropAndCreateTable($table);

        $this->connection->insert('fetch_table', [
            'test_int' => 1,
            'test_binary' => hex2bin('C0DEF00D'),
        ], [
            'test_binary' => ParameterType::BINARY,
        ]);
    }

    public function testPrepareWithBindValue(): void
    {
        $sql  = 'SELECT test_int, test_binary FROM fetch_table WHERE test_int = ? AND test_binary = ?';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1);
        $stmt->bindValue(2, hex2bin('C0DEF00D'), ParameterType::BINARY);

        $row = $stmt->executeQuery()->fetchAssociative();

        self::assertIsArray($row);
        $row = array_change_key_case($row, CASE_LOWER);
        self::assertEquals(['test_int' => 1, 'test_binary' => hex2bin('C0DEF00D')], $row);
    }

    public function testPrepareWithFetchAllAssociative(): void
    {
        $paramInt = 1;
        $paramBin = hex2bin('C0DEF00D');

        $sql  = 'SELECT test_int, test_binary FROM fetch_table WHERE test_int = ? AND test_binary = ?';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, $paramInt);
        $stmt->bindValue(2, $paramBin, ParameterType::BINARY);

        $rows    = $stmt->executeQuery()->fetchAllAssociative();
        $rows[0] = array_change_key_case($rows[0], CASE_LOWER);
        self::assertEquals(['test_int' => $paramInt, 'test_binary' => $paramBin], $rows[0]);
    }

    public function testPrepareWithFetchOne(): void
    {
        $paramInt = 1;
        $paramBin = hex2bin('C0DEF00D');

        $sql  = 'SELECT test_int FROM fetch_table WHERE test_int = ? AND test_binary = ?';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, $paramInt);
        $stmt->bindValue(2, $paramBin, ParameterType::BINARY);

        $column = $stmt->executeQuery()->fetchOne();
        self::assertEquals(1, $column);
    }

    public function testFetchAllAssociative(): void
    {
        $sql  = 'SELECT test_int, test_binary FROM fetch_table WHERE test_int = ? AND test_binary = ?';
        $data = $this->connection->fetchAllAssociative($sql, [1, hex2bin('C0DEF00D')], [1 => ParameterType::BINARY]);

        self::assertCount(1, $data);

        $row = $data[0];
        self::assertCount(2, $row);

        $row = array_change_key_case($row, CASE_LOWER);
        self::assertEquals(1, $row['test_int']);
        self::assertEquals(hex2bin('C0DEF00D'), $row['test_binary']);
    }

    public function testFetchAllWithTypes(): void
    {
        $sql  = 'SELECT test_int, test_binary FROM fetch_table WHERE test_int = ? AND test_binary = ?';
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
        self::assertStringStartsWith(hex2bin('C0DEF00D'), $row['test_binary']);
    }

    public function testFetchAssociative(): void
    {
        $sql = 'SELECT test_int, test_binary FROM fetch_table WHERE test_int = ? AND test_binary = ?';
        $row = $this->connection->fetchAssociative($sql, [1, hex2bin('C0DEF00D')], [1 => ParameterType::BINARY]);

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row['test_int']);
        self::assertEquals(hex2bin('C0DEF00D'), $row['test_binary']);
    }

    public function testFetchAssocWithTypes(): void
    {
        $sql = 'SELECT test_int, test_binary FROM fetch_table WHERE test_int = ? AND test_binary = ?';
        $row = $this->connection->fetchAssociative(
            $sql,
            [1, hex2bin('C0DEF00D')],
            [ParameterType::STRING, Types::BINARY],
        );

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row['test_int']);
        self::assertStringStartsWith(hex2bin('C0DEF00D'), $row['test_binary']);
    }

    public function testFetchArray(): void
    {
        $sql = 'SELECT test_int, test_binary FROM fetch_table WHERE test_int = ? AND test_binary = ?';
        $row = $this->connection->fetchNumeric($sql, [1, hex2bin('C0DEF00D')], [1 => ParameterType::BINARY]);
        self::assertNotFalse($row);

        self::assertEquals(1, $row[0]);
        self::assertEquals(hex2bin('C0DEF00D'), $row[1]);
    }

    public function testFetchArrayWithTypes(): void
    {
        $sql = 'SELECT test_int, test_binary FROM fetch_table WHERE test_int = ? AND test_binary = ?';
        $row = $this->connection->fetchNumeric(
            $sql,
            [1, hex2bin('C0DEF00D')],
            [ParameterType::STRING, Types::BINARY],
        );

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row[0]);
        self::assertStringStartsWith(hex2bin('C0DEF00D'), $row[1]);
    }

    public function testFetchColumn(): void
    {
        $sql     = 'SELECT test_int FROM fetch_table WHERE test_int = ? AND test_binary = ?';
        $testInt = $this->connection->fetchOne($sql, [1, hex2bin('C0DEF00D')], [1 => ParameterType::BINARY]);

        self::assertEquals(1, $testInt);

        $sql        = 'SELECT test_binary FROM fetch_table WHERE test_int = ? AND test_binary = ?';
        $testBinary = $this->connection->fetchOne($sql, [1, hex2bin('C0DEF00D')], [1 => ParameterType::BINARY]);

        self::assertEquals(hex2bin('C0DEF00D'), $testBinary);
    }

    public function testFetchOneWithTypes(): void
    {
        $sql    = 'SELECT test_binary FROM fetch_table WHERE test_int = ? AND test_binary = ?';
        $column = $this->connection->fetchOne(
            $sql,
            [1, hex2bin('C0DEF00D')],
            [ParameterType::STRING, Types::BINARY],
        );

        self::assertIsString($column);

        self::assertStringStartsWith(hex2bin('C0DEF00D'), $column);
    }

    public function testNativeArrayListSupport(): void
    {
        for ($i = 100; $i < 110; $i++) {
            $this->connection->insert('fetch_table', [
                'test_int' => $i,
                'test_binary' => pack('L', $i),
            ], [
                'test_binary' => ParameterType::BINARY,
            ]);
        }

        $result = $this->connection->executeQuery(
            'SELECT test_int FROM fetch_table WHERE test_int IN (?)',
            [[100, 101, 102, 103, 104]],
            [ArrayParameterType::INTEGER],
        );

        $data = $result->fetchAllNumeric();
        self::assertCount(5, $data);
        self::assertEquals([[100], [101], [102], [103], [104]], $data);

        $result = $this->connection->executeQuery(
            'SELECT test_int FROM fetch_table WHERE test_binary IN (?)',
            [
                [
                    pack('L', 100),
                    pack('L', 101),
                    pack('L', 102),
                    pack('L', 103),
                    pack('L', 104),
                ],
            ],
            [ArrayParameterType::BINARY],
        );

        $data = $result->fetchAllNumeric();
        self::assertCount(5, $data);
        self::assertEquals([[100], [101], [102], [103], [104]], $data);

        $result = $this->connection->executeQuery(
            'SELECT test_binary FROM fetch_table WHERE test_binary IN (?)',
            [
                [
                    pack('L', 100),
                    pack('L', 101),
                    pack('L', 102),
                    pack('L', 103),
                    pack('L', 104),
                ],
            ],
            [ArrayParameterType::BINARY],
        );

        $data = $result->fetchFirstColumn();
        self::assertCount(5, $data);
        self::assertEquals([
            pack('L', 100),
            pack('L', 101),
            pack('L', 102),
            pack('L', 103),
            pack('L', 104),
        ], $data);
    }
}
