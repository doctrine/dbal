<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Mysqli\Driver as MySQLiDriver;
use Doctrine\DBAL\Driver\SQLSrv\Driver as SQLSrvDriver;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\TrimMode;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;

use function array_change_key_case;
use function assert;
use function count;
use function date;
use function is_array;
use function json_encode;
use function sprintf;
use function strtotime;

use const CASE_LOWER;

class DataAccessTest extends FunctionalTestCase
{
    /** @var bool */
    private static $generated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$generated !== false) {
            return;
        }

        $table = new Table('fetch_table');
        $table->addColumn('test_int', 'integer');
        $table->addColumn('test_string', 'string', ['length' => 32]);
        $table->addColumn('test_datetime', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['test_int']);

        $sm = $this->connection->getSchemaManager();
        $sm->createTable($table);

        $this->connection->insert('fetch_table', ['test_int' => 1, 'test_string' => 'foo', 'test_datetime' => '2010-01-01 10:10:10']);
        self::$generated = true;
    }

    public function testPrepareWithBindValue(): void
    {
        $sql  = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt = $this->connection->prepare($sql);
        self::assertInstanceOf(Statement::class, $stmt);

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
        self::assertInstanceOf(Statement::class, $stmt);

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
        self::assertInstanceOf(Statement::class, $stmt);

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
        self::assertInstanceOf(Statement::class, $stmt);

        $stmt->bindParam(1, $paramInt);
        $stmt->bindParam(2, $paramStr);

        $column = $stmt->execute()->fetchOne();
        self::assertEquals(1, $column);
    }

    public function testPrepareWithQuoted(): void
    {
        $table    = 'fetch_table';
        $paramInt = 1;
        $paramStr = 'foo';

        $stmt = $this->connection->prepare(sprintf(
            'SELECT test_int, test_string FROM %s WHERE test_int = %d AND test_string = %s',
            $this->connection->quoteIdentifier($table),
            $paramInt,
            $this->connection->quote($paramStr)
        ));
        self::assertInstanceOf(Statement::class, $stmt);
    }

    public function testPrepareWithExecuteParams(): void
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql  = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt = $this->connection->prepare($sql);
        self::assertInstanceOf(Statement::class, $stmt);
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

    /**
     * @group DBAL-209
     */
    public function testFetchAllWithTypes(): void
    {
        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);

        $sql  = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';
        $data = $this->connection->fetchAllAssociative(
            $sql,
            [1, $datetime],
            [ParameterType::STRING, Types::DATETIME_MUTABLE]
        );

        self::assertCount(1, $data);

        $row = $data[0];
        self::assertCount(2, $row);

        $row = array_change_key_case($row, CASE_LOWER);
        self::assertEquals(1, $row['test_int']);
        self::assertStringStartsWith($datetimeString, $row['test_datetime']);
    }

    /**
     * @group DBAL-209
     */
    public function testFetchAllWithMissingTypes(): void
    {
        if (
            $this->connection->getDriver() instanceof MySQLiDriver ||
            $this->connection->getDriver() instanceof SQLSrvDriver
        ) {
            self::markTestSkipped('mysqli and sqlsrv actually supports this');
        }

        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);
        $sql            = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';

        $this->expectException(DBALException::class);

        $this->connection->fetchAllAssociative($sql, [1, $datetime]);
    }

    public function testFetchNoResult(): void
    {
        self::assertFalse(
            $this->connection->executeQuery('SELECT test_int FROM fetch_table WHERE test_int = ?', [-1])->fetchAssociative()
        );
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
            [ParameterType::STRING, Types::DATETIME_MUTABLE]
        );

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row['test_int']);
        self::assertStringStartsWith($datetimeString, $row['test_datetime']);
    }

    public function testFetchAssocWithMissingTypes(): void
    {
        if (
            $this->connection->getDriver() instanceof MySQLiDriver ||
            $this->connection->getDriver() instanceof SQLSrvDriver
        ) {
            self::markTestSkipped('mysqli and sqlsrv actually supports this');
        }

        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);
        $sql            = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';

        $this->expectException(DBALException::class);

        $this->connection->fetchAssociative($sql, [1, $datetime]);
    }

    public function testFetchArray(): void
    {
        $sql = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $row = $this->connection->fetchNumeric($sql, [1, 'foo']);

        self::assertIsArray($row);
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
            [ParameterType::STRING, Types::DATETIME_MUTABLE]
        );

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row[0]);
        self::assertStringStartsWith($datetimeString, $row[1]);
    }

    public function testFetchArrayWithMissingTypes(): void
    {
        if (
            $this->connection->getDriver() instanceof MySQLiDriver ||
            $this->connection->getDriver() instanceof SQLSrvDriver
        ) {
            self::markTestSkipped('mysqli and sqlsrv actually supports this');
        }

        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);
        $sql            = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';

        $this->expectException(DBALException::class);

        $this->connection->fetchNumeric($sql, [1, $datetime]);
    }

    public function testFetchOne(): void
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
            [ParameterType::STRING, Types::DATETIME_MUTABLE]
        );

        self::assertIsString($column);

        self::assertStringStartsWith($datetimeString, $column);
    }

    public function testFetchOneWithMissingTypes(): void
    {
        if (
            $this->connection->getDriver() instanceof MySQLiDriver ||
            $this->connection->getDriver() instanceof SQLSrvDriver
        ) {
            self::markTestSkipped('mysqli and sqlsrv actually supports this');
        }

        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);
        $sql            = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';

        $this->expectException(DBALException::class);

        $this->connection->fetchOne($sql, [1, $datetime]);
    }

    /**
     * @group DDC-697
     */
    public function testExecuteQueryBindDateTimeType(): void
    {
        $value = $this->connection->fetchOne(
            'SELECT count(*) AS c FROM fetch_table WHERE test_datetime = ?',
            [1 => new DateTime('2010-01-01 10:10:10')],
            [1 => Types::DATETIME_MUTABLE]
        );

        self::assertEquals(1, $value);
    }

    /**
     * @group DDC-697
     */
    public function testExecuteUpdateBindDateTimeType(): void
    {
        $datetime = new DateTime('2010-02-02 20:20:20');

        $sql          = 'INSERT INTO fetch_table (test_int, test_string, test_datetime) VALUES (?, ?, ?)';
        $affectedRows = $this->connection->executeUpdate($sql, [
            1 => 50,
            2 => 'foo',
            3 => $datetime,
        ], [
            1 => ParameterType::INTEGER,
            2 => ParameterType::STRING,
            3 => Types::DATETIME_MUTABLE,
        ]);

        self::assertEquals(1, $affectedRows);
        self::assertEquals(1, $this->connection->executeQuery(
            'SELECT count(*) AS c FROM fetch_table WHERE test_datetime = ?',
            [1 => $datetime],
            [1 => Types::DATETIME_MUTABLE]
        )->fetchOne());
    }

    /**
     * @group DDC-697
     */
    public function testPrepareQueryBindValueDateTimeType(): void
    {
        $sql  = 'SELECT count(*) AS c FROM fetch_table WHERE test_datetime = ?';
        $stmt = $this->connection->prepare($sql);
        self::assertInstanceOf(Statement::class, $stmt);
        $stmt->bindValue(1, new DateTime('2010-01-01 10:10:10'), Types::DATETIME_MUTABLE);
        $result = $stmt->execute();

        self::assertEquals(1, $result->fetchOne());
    }

    /**
     * @group DBAL-78
     */
    public function testNativeArrayListSupport(): void
    {
        for ($i = 100; $i < 110; $i++) {
            $this->connection->insert('fetch_table', ['test_int' => $i, 'test_string' => 'foo' . $i, 'test_datetime' => '2010-01-01 10:10:10']);
        }

        $result = $this->connection->executeQuery(
            'SELECT test_int FROM fetch_table WHERE test_int IN (?)',
            [[100, 101, 102, 103, 104]],
            [Connection::PARAM_INT_ARRAY]
        );

        $data = $result->fetchAllNumeric();
        self::assertCount(5, $data);
        self::assertEquals([[100], [101], [102], [103], [104]], $data);

        $result = $this->connection->executeQuery(
            'SELECT test_int FROM fetch_table WHERE test_string IN (?)',
            [['foo100', 'foo101', 'foo102', 'foo103', 'foo104']],
            [Connection::PARAM_STR_ARRAY]
        );

        $data = $result->fetchAllNumeric();
        self::assertCount(5, $data);
        self::assertEquals([[100], [101], [102], [103], [104]], $data);
    }

    /**
     * @dataProvider getTrimExpressionData
     */
    public function testTrimExpression(string $value, int $position, ?string $char, string $expectedResult): void
    {
        $sql = 'SELECT ' .
            $this->connection->getDatabasePlatform()->getTrimExpression($value, $position, $char) . ' AS trimmed ' .
            'FROM fetch_table';

        $row = $this->connection->fetchAssociative($sql);
        self::assertIsArray($row);
        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals($expectedResult, $row['trimmed']);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public static function getTrimExpressionData(): iterable
    {
        return [
            ['test_string', TrimMode::UNSPECIFIED, null, 'foo'],
            ['test_string', TrimMode::LEADING, null, 'foo'],
            ['test_string', TrimMode::TRAILING, null, 'foo'],
            ['test_string', TrimMode::BOTH, null, 'foo'],
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
            ["' foo '", TrimMode::UNSPECIFIED, null, 'foo'],
            ["' foo '", TrimMode::LEADING, null, 'foo '],
            ["' foo '", TrimMode::TRAILING, null, ' foo'],
            ["' foo '", TrimMode::BOTH, null, 'foo'],
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

    public function testTrimExpressionInvalidMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->connection->getDatabasePlatform()->getTrimExpression('Trim me!', 0xBEEF);
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateAddSeconds(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateAddSecondsExpression('test_datetime', $interval);
            },
            1,
            '2010-01-01 10:10:11'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateSubSeconds(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateSubSecondsExpression('test_datetime', $interval);
            },
            1,
            '2010-01-01 10:10:09'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateAddMinutes(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateAddMinutesExpression('test_datetime', $interval);
            },
            5,
            '2010-01-01 10:15:10'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateSubMinutes(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateSubMinutesExpression('test_datetime', $interval);
            },
            5,
            '2010-01-01 10:05:10'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateAddHours(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateAddHourExpression('test_datetime', $interval);
            },
            3,
            '2010-01-01 13:10:10'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateSubHours(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateSubHourExpression('test_datetime', $interval);
            },
            3,
            '2010-01-01 07:10:10'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateAddDays(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateAddDaysExpression('test_datetime', $interval);
            },
            10,
            '2010-01-11 10:10:10'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateSubDays(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateSubDaysExpression('test_datetime', $interval);
            },
            10,
            '2009-12-22 10:10:10'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateAddWeeks(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateAddWeeksExpression('test_datetime', $interval);
            },
            1,
            '2010-01-08 10:10:10'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateSubWeeks(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateSubWeeksExpression('test_datetime', $interval);
            },
            1,
            '2009-12-25 10:10:10'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateAddMonths(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateAddMonthExpression('test_datetime', $interval);
            },
            2,
            '2010-03-01 10:10:10'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateSubMonths(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateSubMonthExpression('test_datetime', $interval);
            },
            2,
            '2009-11-01 10:10:10'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateAddQuarters(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateAddQuartersExpression('test_datetime', $interval);
            },
            3,
            '2010-10-01 10:10:10'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateSubQuarters(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateSubQuartersExpression('test_datetime', $interval);
            },
            3,
            '2009-04-01 10:10:10'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateAddYears(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateAddYearsExpression('test_datetime', $interval);
            },
            6,
            '2016-01-01 10:10:10'
        );
    }

    /**
     * @dataProvider modeProvider
     */
    public function testDateSubYears(callable $buildQuery, callable $bindParams): void
    {
        $this->assertDateExpression(
            $buildQuery,
            $bindParams,
            static function (AbstractPlatform $platform, string $interval): string {
                return $platform->getDateSubYearsExpression('test_datetime', $interval);
            },
            6,
            '2004-01-01 10:10:10'
        );
    }

    /**
     * @param callable $buildQuery Builds the portion of the query representing the interval value
     * @param callable $bindParams Binds the interval value to the statement
     * @param callable $expression Builds the platform-specific interval expression
     * @param int      $interval   Interval value
     * @param string   $expected   Expected value
     */
    private function assertDateExpression(callable $buildQuery, callable $bindParams, callable $expression, int $interval, string $expected): void
    {
        $connection = $this->connection;
        $platform   = $connection->getDatabasePlatform();

        $query = sprintf('SELECT %s FROM fetch_table', $expression($platform, $buildQuery($interval)));
        $stmt  = $connection->prepare($query);
        $bindParams($stmt, $interval);

        $date = $stmt->execute()->fetchOne();

        self::assertEquals($expected, date('Y-m-d H:i:s', strtotime($date)));
    }

    /**
     * @return mixed[][]
     */
    public static function modeProvider(): array
    {
        return [
            'bind' => [
                static function (int $interval): string {
                    return '?';
                },
                static function (Statement $stmt, int $interval): void {
                    $stmt->bindParam(1, $interval, ParameterType::INTEGER);
                },
            ],
            'literal' => [
                static function (int $interval): string {
                    return sprintf('%d', $interval);
                },
                static function (Statement $stmt, int $interval): void {
                },
            ],
            'expression' => [
                static function (int $interval): string {
                    return sprintf('(0 + %d)', $interval);
                },
                static function (Statement $stmt, int $interval): void {
                },
            ],
        ];
    }

    public function testSqliteDateArithmeticWithDynamicInterval(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform instanceof SqlitePlatform) {
            self::markTestSkipped('test is for sqlite only');
        }

        $table = new Table('fetch_table_date_math');
        $table->addColumn('test_date', 'date');
        $table->addColumn('test_days', 'integer');
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
        $sql .= $platform->getLocateExpression('test_string', "'oo'", '2') . ' AS locate8, ';
        $sql .= $platform->getLocateExpression('test_string', "'oo'", '3') . ' AS locate9 ';
        $sql .= 'FROM fetch_table';

        $row = $this->connection->fetchAssociative($sql);
        assert(is_array($row));

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(2, $row['locate1']);
        self::assertEquals(1, $row['locate2']);
        self::assertEquals(0, $row['locate3']);
        self::assertEquals(1, $row['locate4']);
        self::assertEquals(1, $row['locate5']);
        self::assertEquals(4, $row['locate6']);
        self::assertEquals(0, $row['locate7']);
        self::assertEquals(2, $row['locate8']);
        self::assertEquals(0, $row['locate9']);
    }

    /**
     * @dataProvider substringExpressionProvider
     */
    public function testSubstringExpression(string $string, string $start, ?string $length, string $expected): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $query = $platform->getDummySelectSQL(
            $platform->getSubstringExpression($string, $start, $length)
        );

        self::assertEquals($expected, $this->connection->fetchOne($query));
    }

    /**
     * @return mixed[][]
     */
    public static function substringExpressionProvider(): iterable
    {
        return [
            'start-no-length' => [
                "'abcdef'",
                '3',
                null,
                'cdef',
            ],
            'start-with-length' => [
                "'abcdef'",
                '2',
                '4',
                'bcde',
            ],
            'expressions' => [
                "'abcdef'",
                '1 + 1',
                '1 + 1',
                'bc',
            ],
        ];
    }

    public function testQuoteSQLInjection(): void
    {
        $sql  = 'SELECT * FROM fetch_table WHERE test_string = ' . $this->connection->quote("bar' OR '1'='1");
        $rows = $this->connection->fetchAllAssociative($sql);

        self::assertCount(0, $rows, 'no result should be returned, otherwise SQL injection is possible');
    }

    /**
     * @group DDC-1213
     */
    public function testBitComparisonExpressionSupport(): void
    {
        $this->connection->exec('DELETE FROM fetch_table');
        $platform = $this->connection->getDatabasePlatform();
        $bitmap   = [];

        for ($i = 2; $i < 9; $i += 2) {
            $bitmap[$i] = [
                'bit_or'    => ($i | 2),
                'bit_and'   => ($i & 2),
            ];
            $this->connection->insert('fetch_table', [
                'test_int'      => $i,
                'test_string'   => json_encode($bitmap[$i]),
                'test_datetime' => '2010-01-01 10:10:10',
            ]);
        }

        $sql = 'SELECT test_int, test_string'
            . ', ' . $platform->getBitOrComparisonExpression('test_int', '2') . ' AS bit_or'
            . ', ' . $platform->getBitAndComparisonExpression('test_int', '2') . ' AS bit_and'
            . ' FROM fetch_table';

        $data = $this->connection->fetchAllAssociative($sql);

        self::assertCount(4, $data);
        self::assertEquals(count($bitmap), count($data));
        foreach ($data as $row) {
            $row = array_change_key_case($row, CASE_LOWER);

            self::assertArrayHasKey('test_int', $row);

            $id = $row['test_int'];

            self::assertArrayHasKey($id, $bitmap);
            self::assertArrayHasKey($id, $bitmap);

            self::assertArrayHasKey('bit_or', $row);
            self::assertArrayHasKey('bit_and', $row);

            self::assertEquals($row['bit_or'], $bitmap[$id]['bit_or']);
            self::assertEquals($row['bit_and'], $bitmap[$id]['bit_and']);
        }
    }

    /**
     * @group DBAL-241
     */
    public function testFetchAllStyleColumn(): void
    {
        $sql = 'DELETE FROM fetch_table';
        $this->connection->executeUpdate($sql);

        $this->connection->insert('fetch_table', ['test_int' => 1, 'test_string' => 'foo']);
        $this->connection->insert('fetch_table', ['test_int' => 10, 'test_string' => 'foo']);

        $sql    = 'SELECT test_int FROM fetch_table';
        $values = $this->connection->query($sql)->fetchFirstColumn();

        self::assertEquals([1, 10], $values);
    }

    /**
     * @group DBAL-257
     */
    public function testEmptyFetchOneReturnsFalse(): void
    {
        $this->connection->beginTransaction();
        $this->connection->exec('DELETE FROM fetch_table');
        self::assertFalse($this->connection->fetchOne('SELECT test_int FROM fetch_table'));
        self::assertFalse($this->connection->query('SELECT test_int FROM fetch_table')->fetchOne());
        $this->connection->rollBack();
    }

    /**
     * @group DBAL-435
     */
    public function testEmptyParameters(): void
    {
        $sql  = 'SELECT * FROM fetch_table WHERE test_int IN (?)';
        $rows = $this->connection->fetchAllAssociative($sql, [[]], [Connection::PARAM_INT_ARRAY]);

        self::assertEquals([], $rows);
    }

    /**
     * @group DBAL-1028
     */
    public function testFetchOneNoResult(): void
    {
        self::assertFalse(
            $this->connection->fetchOne('SELECT test_int FROM fetch_table WHERE test_int = ?', [-1])
        );
    }
}
