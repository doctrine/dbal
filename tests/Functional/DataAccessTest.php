<?php

namespace Doctrine\DBAL\Tests\Functional;

use DateTime;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\TrimMode;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;

use function array_change_key_case;
use function date;
use function strtotime;

use const CASE_LOWER;

class DataAccessTest extends FunctionalTestCase
{
    use VerifyDeprecations;

    protected function setUp(): void
    {
        $table = new Table('fetch_table');
        $table->addColumn('test_int', Types::INTEGER);
        $table->addColumn('test_string', Types::STRING);
        $table->addColumn('test_datetime', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->setPrimaryKey(['test_int']);

        $this->dropAndCreateTable($table);

        $this->connection->insert('fetch_table', [
            'test_int' => 1,
            'test_string' => 'foo',
            'test_datetime' => '2010-01-01 10:10:10',
        ]);
    }

    public function testPrepareWithBindValue(): void
    {
        $sql  = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1);
        $stmt->bindValue(2, 'foo');

        $row = $stmt->execute()->fetchAssociative();

        self::assertIsArray($row);
        $row = array_change_key_case($row, CASE_LOWER);
        self::assertEquals(['test_int' => 1, 'test_string' => 'foo'], $row);
    }

    public function testPrepareWithBindParam(): void
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql  = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindParam(1, $paramInt);
        $stmt->bindParam(2, $paramStr);

        $row = $stmt->execute()->fetchAssociative();

        self::assertIsArray($row);
        $row = array_change_key_case($row, CASE_LOWER);
        self::assertEquals(['test_int' => 1, 'test_string' => 'foo'], $row);
    }

    public function testPrepareWithFetchAllAssociative(): void
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql  = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindParam(1, $paramInt);
        $stmt->bindParam(2, $paramStr);

        $rows    = $stmt->execute()->fetchAllAssociative();
        $rows[0] = array_change_key_case($rows[0], CASE_LOWER);
        self::assertEquals(['test_int' => 1, 'test_string' => 'foo'], $rows[0]);
    }

    public function testPrepareWithFetchOne(): void
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql  = 'SELECT test_int FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindParam(1, $paramInt);
        $stmt->bindParam(2, $paramStr);

        $column = $stmt->execute()->fetchOne();
        self::assertEquals(1, $column);
    }

    public function testPrepareWithExecuteParams(): void
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql    = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt   = $this->connection->prepare($sql);
        $result = $stmt->execute([$paramInt, $paramStr]);

        $row = $result->fetchAssociative();
        self::assertNotFalse($row);
        $row = array_change_key_case($row, CASE_LOWER);
        self::assertEquals(['test_int' => 1, 'test_string' => 'foo'], $row);
    }

    public function testFetchAllAssociative(): void
    {
        $sql  = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $data = $this->connection->fetchAllAssociative($sql, [1, 'foo']);

        self::assertCount(1, $data);

        $row = $data[0];
        self::assertCount(2, $row);

        $row = array_change_key_case($row, CASE_LOWER);
        self::assertEquals(1, $row['test_int']);
        self::assertEquals('foo', $row['test_string']);
    }

    public function testFetchAllWithTypes(): void
    {
        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);

        $sql  = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';
        $data = $this->connection->fetchAllAssociative(
            $sql,
            [1, $datetime],
            [ParameterType::STRING, Types::DATETIME_MUTABLE],
        );

        self::assertCount(1, $data);

        $row = $data[0];
        self::assertCount(2, $row);

        $row = array_change_key_case($row, CASE_LOWER);
        self::assertEquals(1, $row['test_int']);
        self::assertStringStartsWith($datetimeString, $row['test_datetime']);
    }

    public function testFetchAssociative(): void
    {
        $sql = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $row = $this->connection->fetchAssociative($sql, [1, 'foo']);

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row['test_int']);
        self::assertEquals('foo', $row['test_string']);
    }

    public function testFetchAssocWithTypes(): void
    {
        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);

        $sql = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';
        $row = $this->connection->fetchAssociative(
            $sql,
            [1, $datetime],
            [ParameterType::STRING, Types::DATETIME_MUTABLE],
        );

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row['test_int']);
        self::assertStringStartsWith($datetimeString, $row['test_datetime']);
    }

    public function testFetchArray(): void
    {
        $sql = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $row = $this->connection->fetchNumeric($sql, [1, 'foo']);
        self::assertNotFalse($row);

        self::assertEquals(1, $row[0]);
        self::assertEquals('foo', $row[1]);
    }

    public function testFetchArrayWithTypes(): void
    {
        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);

        $sql = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';
        $row = $this->connection->fetchNumeric(
            $sql,
            [1, $datetime],
            [ParameterType::STRING, Types::DATETIME_MUTABLE],
        );

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row[0]);
        self::assertStringStartsWith($datetimeString, $row[1]);
    }

    public function testFetchColumn(): void
    {
        $sql     = 'SELECT test_int FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $testInt = $this->connection->fetchOne($sql, [1, 'foo']);

        self::assertEquals(1, $testInt);

        $sql        = 'SELECT test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $testString = $this->connection->fetchOne($sql, [1, 'foo']);

        self::assertEquals('foo', $testString);
    }

    public function testFetchOneWithTypes(): void
    {
        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);

        $sql    = 'SELECT test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';
        $column = $this->connection->fetchOne(
            $sql,
            [1, $datetime],
            [ParameterType::STRING, Types::DATETIME_MUTABLE],
        );

        self::assertIsString($column);

        self::assertStringStartsWith($datetimeString, $column);
    }

    public function testExecuteQueryBindDateTimeType(): void
    {
        $value = $this->connection->fetchOne(
            'SELECT count(*) AS c FROM fetch_table WHERE test_datetime = ?',
            [new DateTime('2010-01-01 10:10:10')],
            [Types::DATETIME_MUTABLE],
        );

        self::assertEquals(1, $value);
    }

    public function testExecuteStatementBindDateTimeType(): void
    {
        $datetime = new DateTime('2010-02-02 20:20:20');

        $sql          = 'INSERT INTO fetch_table (test_int, test_string, test_datetime) VALUES (?, ?, ?)';
        $affectedRows = $this->connection->executeStatement($sql, [
            50,
            'foo',
            $datetime,
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            Types::DATETIME_MUTABLE,
        ]);

        self::assertEquals(1, $affectedRows);
        self::assertEquals(1, $this->connection->executeQuery(
            'SELECT count(*) AS c FROM fetch_table WHERE test_datetime = ?',
            [$datetime],
            [Types::DATETIME_MUTABLE],
        )->fetchOne());
    }

    public function testPrepareQueryBindValueDateTimeType(): void
    {
        $sql  = 'SELECT count(*) AS c FROM fetch_table WHERE test_datetime = ?';
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(1, new DateTime('2010-01-01 10:10:10'), Types::DATETIME_MUTABLE);
        $result = $stmt->execute();

        self::assertEquals(1, $result->fetchOne());
    }

    public function testNativeArrayListSupport(): void
    {
        for ($i = 100; $i < 110; $i++) {
            $this->connection->insert('fetch_table', [
                'test_int' => $i,
                'test_string' => 'foo' . $i,
                'test_datetime' => '2010-01-01 10:10:10',
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
            'SELECT test_int FROM fetch_table WHERE test_string IN (?)',
            [['foo100', 'foo101', 'foo102', 'foo103', 'foo104']],
            [ArrayParameterType::STRING],
        );

        $data = $result->fetchAllNumeric();
        self::assertCount(5, $data);
        self::assertEquals([[100], [101], [102], [103], [104]], $data);
    }

    /**
     * @param string|false $char
     *
     * @dataProvider getTrimExpressionData
     */
    public function testTrimExpression(string $value, int $position, $char, string $expectedResult): void
    {
        $sql = 'SELECT ' .
            $this->connection->getDatabasePlatform()->getTrimExpression($value, $position, $char) . ' AS trimmed ' .
            'FROM fetch_table';

        $row = $this->connection->fetchAssociative($sql);
        self::assertNotFalse($row);
        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals($expectedResult, $row['trimmed']);
    }

    /** @return array<int, array<int, mixed>> */
    public static function getTrimExpressionData(): iterable
    {
        return [
            ['test_string', TrimMode::UNSPECIFIED, false, 'foo'],
            ['test_string', TrimMode::LEADING, false, 'foo'],
            ['test_string', TrimMode::TRAILING, false, 'foo'],
            ['test_string', TrimMode::BOTH, false, 'foo'],
            ['test_string', TrimMode::UNSPECIFIED, "'f'", 'oo'],
            ['test_string', TrimMode::UNSPECIFIED, "'o'", 'f'],
            ['test_string', TrimMode::UNSPECIFIED, "'.'", 'foo'],
            ['test_string', TrimMode::LEADING, "'f'", 'oo'],
            ['test_string', TrimMode::LEADING, "'o'", 'foo'],
            ['test_string', TrimMode::LEADING, "'.'", 'foo'],
            ['test_string', TrimMode::TRAILING, "'f'", 'foo'],
            ['test_string', TrimMode::TRAILING, "'o'", 'f'],
            ['test_string', TrimMode::TRAILING, "'.'", 'foo'],
            ['test_string', TrimMode::BOTH, "'f'", 'oo'],
            ['test_string', TrimMode::BOTH, "'o'", 'f'],
            ['test_string', TrimMode::BOTH, "'.'", 'foo'],
            ["' foo '", TrimMode::UNSPECIFIED, false, 'foo'],
            ["' foo '", TrimMode::LEADING, false, 'foo '],
            ["' foo '", TrimMode::TRAILING, false, ' foo'],
            ["' foo '", TrimMode::BOTH, false, 'foo'],
            ["' foo '", TrimMode::UNSPECIFIED, "'f'", ' foo '],
            ["' foo '", TrimMode::UNSPECIFIED, "'o'", ' foo '],
            ["' foo '", TrimMode::UNSPECIFIED, "'.'", ' foo '],
            ["' foo '", TrimMode::UNSPECIFIED, "' '", 'foo'],
            ["' foo '", TrimMode::LEADING, "'f'", ' foo '],
            ["' foo '", TrimMode::LEADING, "'o'", ' foo '],
            ["' foo '", TrimMode::LEADING, "'.'", ' foo '],
            ["' foo '", TrimMode::LEADING, "' '", 'foo '],
            ["' foo '", TrimMode::TRAILING, "'f'", ' foo '],
            ["' foo '", TrimMode::TRAILING, "'o'", ' foo '],
            ["' foo '", TrimMode::TRAILING, "'.'", ' foo '],
            ["' foo '", TrimMode::TRAILING, "' '", ' foo'],
            ["' foo '", TrimMode::BOTH, "'f'", ' foo '],
            ["' foo '", TrimMode::BOTH, "'o'", ' foo '],
            ["' foo '", TrimMode::BOTH, "'.'", ' foo '],
            ["' foo '", TrimMode::BOTH, "' '", 'foo'],
        ];
    }

    public function testDateArithmetics(): void
    {
        $p    = $this->connection->getDatabasePlatform();
        $sql  = 'SELECT ';
        $sql .= $p->getDateAddSecondsExpression('test_datetime', 1) . ' AS add_seconds, ';
        $sql .= $p->getDateSubSecondsExpression('test_datetime', 1) . ' AS sub_seconds, ';
        $sql .= $p->getDateAddMinutesExpression('test_datetime', 5) . ' AS add_minutes, ';
        $sql .= $p->getDateSubMinutesExpression('test_datetime', 5) . ' AS sub_minutes, ';
        $sql .= $p->getDateAddHourExpression('test_datetime', 3) . ' AS add_hour, ';
        $sql .= $p->getDateSubHourExpression('test_datetime', 3) . ' AS sub_hour, ';
        $sql .= $p->getDateAddDaysExpression('test_datetime', 10) . ' AS add_days, ';
        $sql .= $p->getDateSubDaysExpression('test_datetime', 10) . ' AS sub_days, ';
        $sql .= $p->getDateAddWeeksExpression('test_datetime', 1) . ' AS add_weeks, ';
        $sql .= $p->getDateSubWeeksExpression('test_datetime', 1) . ' AS sub_weeks, ';
        $sql .= $p->getDateAddMonthExpression('test_datetime', 2) . ' AS add_month, ';
        $sql .= $p->getDateSubMonthExpression('test_datetime', 2) . ' AS sub_month, ';
        $sql .= $p->getDateAddQuartersExpression('test_datetime', 3) . ' AS add_quarters, ';
        $sql .= $p->getDateSubQuartersExpression('test_datetime', 3) . ' AS sub_quarters, ';
        $sql .= $p->getDateAddYearsExpression('test_datetime', 6) . ' AS add_years, ';
        $sql .= $p->getDateSubYearsExpression('test_datetime', 6) . ' AS sub_years ';
        $sql .= 'FROM fetch_table';

        $row = $this->connection->fetchAssociative($sql);
        self::assertNotFalse($row);
        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals('2010-01-01 10:10:11', date('Y-m-d H:i:s', strtotime($row['add_seconds'])));
        self::assertEquals('2010-01-01 10:10:09', date('Y-m-d H:i:s', strtotime($row['sub_seconds'])));
        self::assertEquals('2010-01-01 10:15:10', date('Y-m-d H:i:s', strtotime($row['add_minutes'])));
        self::assertEquals('2010-01-01 10:05:10', date('Y-m-d H:i:s', strtotime($row['sub_minutes'])));
        self::assertEquals('2010-01-01 13:10', date('Y-m-d H:i', strtotime($row['add_hour'])));
        self::assertEquals('2010-01-01 07:10', date('Y-m-d H:i', strtotime($row['sub_hour'])));
        self::assertEquals('2010-01-11', date('Y-m-d', strtotime($row['add_days'])));
        self::assertEquals('2009-12-22', date('Y-m-d', strtotime($row['sub_days'])));
        self::assertEquals('2010-01-08', date('Y-m-d', strtotime($row['add_weeks'])));
        self::assertEquals('2009-12-25', date('Y-m-d', strtotime($row['sub_weeks'])));
        self::assertEquals('2010-03-01', date('Y-m-d', strtotime($row['add_month'])));
        self::assertEquals('2009-11-01', date('Y-m-d', strtotime($row['sub_month'])));
        self::assertEquals('2010-10-01', date('Y-m-d', strtotime($row['add_quarters'])));
        self::assertEquals('2009-04-01', date('Y-m-d', strtotime($row['sub_quarters'])));
        self::assertEquals('2016-01-01', date('Y-m-d', strtotime($row['add_years'])));
        self::assertEquals('2004-01-01', date('Y-m-d', strtotime($row['sub_years'])));
    }

    public function testSqliteDateArithmeticWithDynamicInterval(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform instanceof SqlitePlatform) {
            self::markTestSkipped('test is for sqlite only');
        }

        $table = new Table('fetch_table_date_math');
        $table->addColumn('test_date', Types::DATE_MUTABLE);
        $table->addColumn('test_days', Types::INTEGER);
        $table->setPrimaryKey(['test_date']);

        $sm = $this->connection->getSchemaManager();
        $sm->createTable($table);

        $this->connection->insert('fetch_table_date_math', ['test_date' => '2010-01-01', 'test_days' => 10]);
        $this->connection->insert('fetch_table_date_math', ['test_date' => '2010-06-01', 'test_days' => 20]);

        $sql  = 'SELECT COUNT(*) FROM fetch_table_date_math WHERE ';
        $sql .= $platform->getDateSubDaysExpression('test_date', 'test_days') . " < '2010-05-12'";

        $rowCount = $this->connection->fetchOne($sql);

        self::assertEquals(1, $rowCount);
    }

    public function testLocateExpression(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $sql  = 'SELECT ';
        $sql .= $platform->getLocateExpression('test_string', "'oo'") . ' AS locate1, ';
        $sql .= $platform->getLocateExpression('test_string', "'foo'") . ' AS locate2, ';
        $sql .= $platform->getLocateExpression('test_string', "'bar'") . ' AS locate3, ';
        $sql .= $platform->getLocateExpression('test_string', 'test_string') . ' AS locate4, ';
        $sql .= $platform->getLocateExpression("'foo'", 'test_string') . ' AS locate5, ';
        $sql .= $platform->getLocateExpression("'barfoobaz'", 'test_string') . ' AS locate6, ';
        $sql .= $platform->getLocateExpression("'bar'", 'test_string') . ' AS locate7, ';
        $sql .= $platform->getLocateExpression('test_string', "'oo'", 2) . ' AS locate8, ';
        $sql .= $platform->getLocateExpression('test_string', "'oo'", 3) . ' AS locate9, ';
        $sql .= $platform->getLocateExpression('test_string', "'foo'", 1) . ' AS locate10, ';
        $sql .= $platform->getLocateExpression('test_string', "'oo'", '1 + 1') . ' AS locate11 ';
        $sql .= 'FROM fetch_table';

        $row = $this->connection->fetchAssociative($sql);
        self::assertNotFalse($row);
        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals([
            'locate1' => 2,
            'locate2' => 1,
            'locate3' => 0,
            'locate4' => 1,
            'locate5' => 1,
            'locate6' => 4,
            'locate7' => 0,
            'locate8' => 2,
            'locate9' => 0,
            'locate10' => 1,
            'locate11' => 2,
        ], $row);
    }

    public function testSqliteLocateEmulation(): void
    {
        if (! TestUtil::isDriverOneOf('pdo_sqlite', 'sqlite3')) {
            self::markTestSkipped('test is for SQLite only');
        }

        $sql = <<< 'SQL'
            SELECT
                LOCATE(test_string, 'oo') AS locate1,
                LOCATE(test_string, 'foo') AS locate2,
                LOCATE(test_string, 'bar') AS locate3,
                LOCATE(test_string, test_string) AS locate4,
                LOCATE('foo', test_string) AS locate5,
                LOCATE('barfoobaz', test_string) AS locate6,
                LOCATE('bar', test_string) AS locate7,
                LOCATE(test_string, 'oo', 2) AS locate8,
                LOCATE(test_string, 'oo', 3) AS locate9,
                LOCATE(test_string, 'foo', 1) AS locate10,
                LOCATE(test_string, 'oo', 1 + 1) AS locate11
            FROM fetch_table
            SQL;

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/5749');

        self::assertEquals([
            'locate1' => 2,
            'locate2' => 1,
            'locate3' => 0,
            'locate4' => 1,
            'locate5' => 1,
            'locate6' => 4,
            'locate7' => 0,
            'locate8' => 2,
            'locate9' => 0,
            'locate10' => 1,
            'locate11' => 2,
        ], $this->connection->fetchAssociative($sql));
    }

    public function testQuoteSQLInjection(): void
    {
        $sql  = 'SELECT * FROM fetch_table WHERE test_string = ' . $this->connection->quote("bar' OR '1'='1");
        $rows = $this->connection->fetchAllAssociative($sql);

        self::assertCount(0, $rows, 'no result should be returned, otherwise SQL injection is possible');
    }
}
