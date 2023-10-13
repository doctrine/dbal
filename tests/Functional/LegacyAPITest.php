<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use LogicException;

use function array_change_key_case;
use function array_map;

use const CASE_LOWER;

class LegacyAPITest extends FunctionalTestCase
{
    use VerifyDeprecations;

    protected function setUp(): void
    {
        $table = new Table('legacy_table');
        $table->addColumn('test_int', Types::INTEGER);
        $table->addColumn('test_string', Types::STRING);
        $table->setPrimaryKey(['test_int']);

        $this->dropAndCreateTable($table);

        $this->connection->insert('legacy_table', [
            'test_int' => 1,
            'test_string' => 'foo',
        ]);
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DELETE FROM legacy_table WHERE test_int > 1');
    }

    public function testFetchWithAssociativeMode(): void
    {
        $sql = 'SELECT test_int FROM legacy_table WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4007');

        $row = array_change_key_case($stmt->fetch(FetchMode::ASSOCIATIVE), CASE_LOWER);
        self::assertEquals(1, $row['test_int']);
    }

    public function testFetchWithNumericMode(): void
    {
        $sql = 'SELECT test_int FROM legacy_table WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4007');

        $row = $stmt->fetch(FetchMode::NUMERIC);
        self::assertEquals(1, $row[0]);
    }

    public function testFetchWithColumnMode(): void
    {
        $sql = 'SELECT test_int FROM legacy_table WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4007');

        $row = $stmt->fetch(FetchMode::COLUMN);
        self::assertEquals(1, $row);
    }

    public function testFetchWithTooManyArguments(): void
    {
        $sql = 'SELECT test_int FROM legacy_table WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectException(LogicException::class);

        $stmt->fetch(FetchMode::COLUMN, 2);
    }

    public function testFetchWithUnsupportedFetchMode(): void
    {
        $sql = 'SELECT test_int FROM legacy_table WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectException(LogicException::class);

        $stmt->fetch(1);
    }

    public function testFetchAllWithAssociativeModes(): void
    {
        $sql = 'SELECT test_int FROM legacy_table WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4007');

        $rows = $stmt->fetchAll(FetchMode::ASSOCIATIVE);
        $rows = array_map(static function ($row) {
            return array_change_key_case($row, CASE_LOWER);
        }, $rows);

        self::assertEquals([['test_int' => 1]], $rows);
    }

    public function testFetchAllWithNumericModes(): void
    {
        $sql = 'SELECT test_int FROM legacy_table WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4007');

        $rows = $stmt->fetchAll(FetchMode::NUMERIC);
        self::assertEquals([[0 => 1]], $rows);
    }

    public function testFetchAllWithColumnMode(): void
    {
        $sql = 'SELECT test_int FROM legacy_table WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4007');

        $rows = $stmt->fetchAll(FetchMode::COLUMN);
        self::assertEquals([1], $rows);
    }

    public function testFetchAllWithTooManyArguments(): void
    {
        $sql = 'SELECT test_int FROM legacy_table WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectException(LogicException::class);

        $stmt->fetchAll(FetchMode::COLUMN, 2);
    }

    public function testFetchAllWithUnsupportedFetchMode(): void
    {
        $sql = 'SELECT test_int FROM legacy_table WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectException(LogicException::class);

        $stmt->fetchAll(1);
    }

    public function testExecuteUpdate(): void
    {
        $this->connection->executeUpdate(
            'INSERT INTO legacy_table (test_int, test_string) VALUES (?, ?)',
            [2, 'bar'],
            ['integer', 'string'],
        );

        $sql = 'SELECT test_string FROM legacy_table';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4007');

        $rows = $stmt->fetchAll(FetchMode::COLUMN);
        self::assertEquals(['foo', 'bar'], $rows);
    }

    public function testQuery(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4163');

        $stmt = $this->connection->query('SELECT test_string FROM legacy_table WHERE test_int = 1');

        $this->assertEquals('foo', $stmt->fetchOne());
    }

    public function testExec(): void
    {
        $this->connection->insert('legacy_table', [
            'test_int' => 2,
            'test_string' => 'bar',
        ]);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4163');

        $count = $this->connection->exec('DELETE FROM legacy_table WHERE test_int > 1');

        $this->assertEquals(1, $count);
    }
}
