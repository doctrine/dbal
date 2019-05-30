<?php

namespace Doctrine\Tests\DBAL\Functional;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Mysqli\Driver as MySQLiDriver;
use Doctrine\DBAL\Driver\OCI8\Driver as Oci8Driver;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\PDOOracle\Driver as PDOOracleDriver;
use Doctrine\DBAL\Driver\SQLSrv\Driver as SQLSrvDriver;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\TrimMode;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Types\Types;
use Doctrine\Tests\DbalFunctionalTestCase;
use PDO;
use const CASE_LOWER;
use const PHP_EOL;
use function array_change_key_case;
use function array_filter;
use function array_keys;
use function count;
use function date;
use function implode;
use function is_numeric;
use function json_encode;
use function property_exists;
use function sprintf;
use function strtotime;

class DataAccessTest extends DbalFunctionalTestCase
{
    /** @var bool */
    private static $generated = false;

    protected function setUp() : void
    {
        parent::setUp();

        if (self::$generated !== false) {
            return;
        }

        $table = new Table('fetch_table');
        $table->addColumn('test_int', 'integer');
        $table->addColumn('test_string', 'string');
        $table->addColumn('test_datetime', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['test_int']);

        $sm = $this->connection->getSchemaManager();
        $sm->createTable($table);

        $this->connection->insert('fetch_table', ['test_int' => 1, 'test_string' => 'foo', 'test_datetime' => '2010-01-01 10:10:10']);
        self::$generated = true;
    }

    public function testPrepareWithBindValue() : void
    {
        $sql  = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt = $this->connection->prepare($sql);
        self::assertInstanceOf(Statement::class, $stmt);

        $stmt->bindValue(1, 1);
        $stmt->bindValue(2, 'foo');
        $stmt->execute();

        $row = $stmt->fetch(FetchMode::ASSOCIATIVE);
        $row = array_change_key_case($row, CASE_LOWER);
        self::assertEquals(['test_int' => 1, 'test_string' => 'foo'], $row);
    }

    public function testPrepareWithBindParam() : void
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql  = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt = $this->connection->prepare($sql);
        self::assertInstanceOf(Statement::class, $stmt);

        $stmt->bindParam(1, $paramInt);
        $stmt->bindParam(2, $paramStr);
        $stmt->execute();

        $row = $stmt->fetch(FetchMode::ASSOCIATIVE);
        $row = array_change_key_case($row, CASE_LOWER);
        self::assertEquals(['test_int' => 1, 'test_string' => 'foo'], $row);
    }

    public function testPrepareWithFetchAll() : void
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql  = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt = $this->connection->prepare($sql);
        self::assertInstanceOf(Statement::class, $stmt);

        $stmt->bindParam(1, $paramInt);
        $stmt->bindParam(2, $paramStr);
        $stmt->execute();

        $rows    = $stmt->fetchAll(FetchMode::ASSOCIATIVE);
        $rows[0] = array_change_key_case($rows[0], CASE_LOWER);
        self::assertEquals(['test_int' => 1, 'test_string' => 'foo'], $rows[0]);
    }

    /**
     * @group DBAL-228
     */
    public function testPrepareWithFetchAllBoth() : void
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql  = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt = $this->connection->prepare($sql);
        self::assertInstanceOf(Statement::class, $stmt);

        $stmt->bindParam(1, $paramInt);
        $stmt->bindParam(2, $paramStr);
        $stmt->execute();

        $rows    = $stmt->fetchAll(FetchMode::MIXED);
        $rows[0] = array_change_key_case($rows[0], CASE_LOWER);
        self::assertEquals(['test_int' => 1, 'test_string' => 'foo', 0 => 1, 1 => 'foo'], $rows[0]);
    }

    public function testPrepareWithFetchColumn() : void
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql  = 'SELECT test_int FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt = $this->connection->prepare($sql);
        self::assertInstanceOf(Statement::class, $stmt);

        $stmt->bindParam(1, $paramInt);
        $stmt->bindParam(2, $paramStr);
        $stmt->execute();

        $column = $stmt->fetchColumn();
        self::assertEquals(1, $column);
    }

    public function testPrepareWithIterator() : void
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql  = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt = $this->connection->prepare($sql);
        self::assertInstanceOf(Statement::class, $stmt);

        $stmt->bindParam(1, $paramInt);
        $stmt->bindParam(2, $paramStr);
        $stmt->execute();

        $rows = [];
        $stmt->setFetchMode(FetchMode::ASSOCIATIVE);
        foreach ($stmt as $row) {
            $rows[] = array_change_key_case($row, CASE_LOWER);
        }

        self::assertEquals(['test_int' => 1, 'test_string' => 'foo'], $rows[0]);
    }

    public function testPrepareWithQuoted() : void
    {
        $table    = 'fetch_table';
        $paramInt = 1;
        $paramStr = 'foo';

        $stmt = $this->connection->prepare(sprintf(
            'SELECT test_int, test_string FROM %s WHERE test_int = %s AND test_string = %s',
            $this->connection->quoteIdentifier($table),
            $this->connection->quote($paramInt),
            $this->connection->quote($paramStr)
        ));
        self::assertInstanceOf(Statement::class, $stmt);
    }

    public function testPrepareWithExecuteParams() : void
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql  = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt = $this->connection->prepare($sql);
        self::assertInstanceOf(Statement::class, $stmt);
        $stmt->execute([$paramInt, $paramStr]);

        $row = $stmt->fetch(FetchMode::ASSOCIATIVE);
        self::assertNotFalse($row);
        $row = array_change_key_case($row, CASE_LOWER);
        self::assertEquals(['test_int' => 1, 'test_string' => 'foo'], $row);
    }

    public function testFetchAll() : void
    {
        $sql  = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $data = $this->connection->fetchAll($sql, [1, 'foo']);

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
    public function testFetchAllWithTypes() : void
    {
        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);

        $sql  = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';
        $data = $this->connection->fetchAll(
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
    public function testFetchAllWithMissingTypes() : void
    {
        if ($this->connection->getDriver() instanceof MySQLiDriver ||
            $this->connection->getDriver() instanceof SQLSrvDriver) {
            $this->markTestSkipped('mysqli and sqlsrv actually supports this');
        }

        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);
        $sql            = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';

        $this->expectException(DBALException::class);

        $this->connection->fetchAll($sql, [1, $datetime]);
    }

    public function testFetchBoth() : void
    {
        $sql = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $row = $this->connection->executeQuery($sql, [1, 'foo'])->fetch(FetchMode::MIXED);

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row['test_int']);
        self::assertEquals('foo', $row['test_string']);
        self::assertEquals(1, $row[0]);
        self::assertEquals('foo', $row[1]);
    }

    public function testFetchNoResult() : void
    {
        self::assertFalse(
            $this->connection->executeQuery('SELECT test_int FROM fetch_table WHERE test_int = ?', [-1])->fetch()
        );
    }

    public function testFetchAssoc() : void
    {
        $sql = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $row = $this->connection->fetchAssoc($sql, [1, 'foo']);

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row['test_int']);
        self::assertEquals('foo', $row['test_string']);
    }

    public function testFetchAssocWithTypes() : void
    {
        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);

        $sql = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';
        $row = $this->connection->fetchAssoc(
            $sql,
            [1, $datetime],
            [ParameterType::STRING, Types::DATETIME_MUTABLE]
        );

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row['test_int']);
        self::assertStringStartsWith($datetimeString, $row['test_datetime']);
    }

    public function testFetchAssocWithMissingTypes() : void
    {
        if ($this->connection->getDriver() instanceof MySQLiDriver ||
            $this->connection->getDriver() instanceof SQLSrvDriver) {
            $this->markTestSkipped('mysqli and sqlsrv actually supports this');
        }

        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);
        $sql            = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';

        $this->expectException(DBALException::class);

        $this->connection->fetchAssoc($sql, [1, $datetime]);
    }

    public function testFetchArray() : void
    {
        $sql = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $row = $this->connection->fetchArray($sql, [1, 'foo']);

        self::assertEquals(1, $row[0]);
        self::assertEquals('foo', $row[1]);
    }

    public function testFetchArrayWithTypes() : void
    {
        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);

        $sql = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';
        $row = $this->connection->fetchArray(
            $sql,
            [1, $datetime],
            [ParameterType::STRING, Types::DATETIME_MUTABLE]
        );

        self::assertNotFalse($row);

        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals(1, $row[0]);
        self::assertStringStartsWith($datetimeString, $row[1]);
    }

    public function testFetchArrayWithMissingTypes() : void
    {
        if ($this->connection->getDriver() instanceof MySQLiDriver ||
            $this->connection->getDriver() instanceof SQLSrvDriver) {
            $this->markTestSkipped('mysqli and sqlsrv actually supports this');
        }

        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);
        $sql            = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';

        $this->expectException(DBALException::class);

        $this->connection->fetchArray($sql, [1, $datetime]);
    }

    public function testFetchColumn() : void
    {
        $sql     = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $testInt = $this->connection->fetchColumn($sql, [1, 'foo'], 0);

        self::assertEquals(1, $testInt);

        $sql        = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $testString = $this->connection->fetchColumn($sql, [1, 'foo'], 1);

        self::assertEquals('foo', $testString);
    }

    public function testFetchColumnWithTypes() : void
    {
        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);

        $sql    = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';
        $column = $this->connection->fetchColumn(
            $sql,
            [1, $datetime],
            1,
            [ParameterType::STRING, Types::DATETIME_MUTABLE]
        );

        self::assertNotFalse($column);

        self::assertStringStartsWith($datetimeString, $column);
    }

    public function testFetchColumnWithMissingTypes() : void
    {
        if ($this->connection->getDriver() instanceof MySQLiDriver ||
            $this->connection->getDriver() instanceof SQLSrvDriver) {
            $this->markTestSkipped('mysqli and sqlsrv actually supports this');
        }

        $datetimeString = '2010-01-01 10:10:10';
        $datetime       = new DateTime($datetimeString);
        $sql            = 'SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?';

        $this->expectException(DBALException::class);

        $this->connection->fetchColumn($sql, [1, $datetime], 1);
    }

    /**
     * @group DDC-697
     */
    public function testExecuteQueryBindDateTimeType() : void
    {
        $sql  = 'SELECT count(*) AS c FROM fetch_table WHERE test_datetime = ?';
        $stmt = $this->connection->executeQuery(
            $sql,
            [1 => new DateTime('2010-01-01 10:10:10')],
            [1 => Types::DATETIME_MUTABLE]
        );

        self::assertEquals(1, $stmt->fetchColumn());
    }

    /**
     * @group DDC-697
     */
    public function testExecuteUpdateBindDateTimeType() : void
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
        )->fetchColumn());
    }

    /**
     * @group DDC-697
     */
    public function testPrepareQueryBindValueDateTimeType() : void
    {
        $sql  = 'SELECT count(*) AS c FROM fetch_table WHERE test_datetime = ?';
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(1, new DateTime('2010-01-01 10:10:10'), Types::DATETIME_MUTABLE);
        $stmt->execute();

        self::assertEquals(1, $stmt->fetchColumn());
    }

    /**
     * @group DBAL-78
     */
    public function testNativeArrayListSupport() : void
    {
        for ($i = 100; $i < 110; $i++) {
            $this->connection->insert('fetch_table', ['test_int' => $i, 'test_string' => 'foo' . $i, 'test_datetime' => '2010-01-01 10:10:10']);
        }

        $stmt = $this->connection->executeQuery(
            'SELECT test_int FROM fetch_table WHERE test_int IN (?)',
            [[100, 101, 102, 103, 104]],
            [Connection::PARAM_INT_ARRAY]
        );

        $data = $stmt->fetchAll(FetchMode::NUMERIC);
        self::assertCount(5, $data);
        self::assertEquals([[100], [101], [102], [103], [104]], $data);

        $stmt = $this->connection->executeQuery(
            'SELECT test_int FROM fetch_table WHERE test_string IN (?)',
            [['foo100', 'foo101', 'foo102', 'foo103', 'foo104']],
            [Connection::PARAM_STR_ARRAY]
        );

        $data = $stmt->fetchAll(FetchMode::NUMERIC);
        self::assertCount(5, $data);
        self::assertEquals([[100], [101], [102], [103], [104]], $data);
    }

    /**
     * @param string|false $char
     *
     * @dataProvider getTrimExpressionData
     */
    public function testTrimExpression(string $value, int $position, $char, string $expectedResult) : void
    {
        $sql = 'SELECT ' .
            $this->connection->getDatabasePlatform()->getTrimExpression($value, $position, $char) . ' AS trimmed ' .
            'FROM fetch_table';

        $row = $this->connection->fetchAssoc($sql);
        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals($expectedResult, $row['trimmed']);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public static function getTrimExpressionData() : iterable
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

    /**
     * @group DDC-1014
     */
    public function testDateArithmetics() : void
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

        $row = $this->connection->fetchAssoc($sql);
        $row = array_change_key_case($row, CASE_LOWER);

        self::assertEquals('2010-01-01 10:10:11', date('Y-m-d H:i:s', strtotime($row['add_seconds'])), 'Adding second should end up on 2010-01-01 10:10:11');
        self::assertEquals('2010-01-01 10:10:09', date('Y-m-d H:i:s', strtotime($row['sub_seconds'])), 'Subtracting second should end up on 2010-01-01 10:10:09');
        self::assertEquals('2010-01-01 10:15:10', date('Y-m-d H:i:s', strtotime($row['add_minutes'])), 'Adding minutes should end up on 2010-01-01 10:15:10');
        self::assertEquals('2010-01-01 10:05:10', date('Y-m-d H:i:s', strtotime($row['sub_minutes'])), 'Subtracting minutes should end up on 2010-01-01 10:05:10');
        self::assertEquals('2010-01-01 13:10', date('Y-m-d H:i', strtotime($row['add_hour'])), 'Adding date should end up on 2010-01-01 13:10');
        self::assertEquals('2010-01-01 07:10', date('Y-m-d H:i', strtotime($row['sub_hour'])), 'Subtracting date should end up on 2010-01-01 07:10');
        self::assertEquals('2010-01-11', date('Y-m-d', strtotime($row['add_days'])), 'Adding date should end up on 2010-01-11');
        self::assertEquals('2009-12-22', date('Y-m-d', strtotime($row['sub_days'])), 'Subtracting date should end up on 2009-12-22');
        self::assertEquals('2010-01-08', date('Y-m-d', strtotime($row['add_weeks'])), 'Adding week should end up on 2010-01-08');
        self::assertEquals('2009-12-25', date('Y-m-d', strtotime($row['sub_weeks'])), 'Subtracting week should end up on 2009-12-25');
        self::assertEquals('2010-03-01', date('Y-m-d', strtotime($row['add_month'])), 'Adding month should end up on 2010-03-01');
        self::assertEquals('2009-11-01', date('Y-m-d', strtotime($row['sub_month'])), 'Subtracting month should end up on 2009-11-01');
        self::assertEquals('2010-10-01', date('Y-m-d', strtotime($row['add_quarters'])), 'Adding quarters should end up on 2010-04-01');
        self::assertEquals('2009-04-01', date('Y-m-d', strtotime($row['sub_quarters'])), 'Subtracting quarters should end up on 2009-10-01');
        self::assertEquals('2016-01-01', date('Y-m-d', strtotime($row['add_years'])), 'Adding years should end up on 2016-01-01');
        self::assertEquals('2004-01-01', date('Y-m-d', strtotime($row['sub_years'])), 'Subtracting years should end up on 2004-01-01');
    }

    public function testSqliteDateArithmeticWithDynamicInterval() : void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform instanceof SqlitePlatform) {
            $this->markTestSkipped('test is for sqlite only');
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

        $rowCount = $this->connection->fetchColumn($sql, [], 0);

        $this->assertEquals(1, $rowCount);
    }

    public function testLocateExpression() : void
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
        $sql .= $platform->getLocateExpression('test_string', "'oo'", 3) . ' AS locate9 ';
        $sql .= 'FROM fetch_table';

        $row = $this->connection->fetchAssoc($sql);
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

    public function testQuoteSQLInjection() : void
    {
        $sql  = 'SELECT * FROM fetch_table WHERE test_string = ' . $this->connection->quote("bar' OR '1'='1");
        $rows = $this->connection->fetchAll($sql);

        self::assertCount(0, $rows, 'no result should be returned, otherwise SQL injection is possible');
    }

    /**
     * @group DDC-1213
     */
    public function testBitComparisonExpressionSupport() : void
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

        $sql[] = 'SELECT ';
        $sql[] = 'test_int, ';
        $sql[] = 'test_string, ';
        $sql[] = $platform->getBitOrComparisonExpression('test_int', 2) . ' AS bit_or, ';
        $sql[] = $platform->getBitAndComparisonExpression('test_int', 2) . ' AS bit_and ';
        $sql[] = 'FROM fetch_table';

        $stmt = $this->connection->executeQuery(implode(PHP_EOL, $sql));
        $data = $stmt->fetchAll(FetchMode::ASSOCIATIVE);

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

    public function testSetDefaultFetchMode() : void
    {
        $stmt = $this->connection->query('SELECT * FROM fetch_table');
        $stmt->setFetchMode(FetchMode::NUMERIC);

        $row = array_keys($stmt->fetch());
        self::assertCount(0, array_filter($row, static function ($v) {
            return ! is_numeric($v);
        }), 'should be no non-numerical elements in the result.');
    }

    /**
     * @group DBAL-1091
     */
    public function testFetchAllStyleObject() : void
    {
        $this->setupFixture();

        $sql  = 'SELECT test_int, test_string, test_datetime FROM fetch_table';
        $stmt = $this->connection->prepare($sql);

        $stmt->execute();

        $results = $stmt->fetchAll(FetchMode::STANDARD_OBJECT);

        self::assertCount(1, $results);
        self::assertInstanceOf('stdClass', $results[0]);

        self::assertEquals(
            1,
            property_exists($results[0], 'test_int') ? $results[0]->test_int : $results[0]->TEST_INT
        );
        self::assertEquals(
            'foo',
            property_exists($results[0], 'test_string') ? $results[0]->test_string : $results[0]->TEST_STRING
        );
        self::assertStringStartsWith(
            '2010-01-01 10:10:10',
            property_exists($results[0], 'test_datetime') ? $results[0]->test_datetime : $results[0]->TEST_DATETIME
        );
    }

    /**
     * @group DBAL-196
     */
    public function testFetchAllSupportFetchClass() : void
    {
        $this->beforeFetchClassTest();
        $this->setupFixture();

        $sql  = 'SELECT test_int, test_string, test_datetime FROM fetch_table';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();

        $results = $stmt->fetchAll(
            FetchMode::CUSTOM_OBJECT,
            MyFetchClass::class
        );

        self::assertCount(1, $results);
        self::assertInstanceOf(MyFetchClass::class, $results[0]);

        self::assertEquals(1, $results[0]->test_int);
        self::assertEquals('foo', $results[0]->test_string);
        self::assertStringStartsWith('2010-01-01 10:10:10', $results[0]->test_datetime);
    }

    /**
     * @group DBAL-241
     */
    public function testFetchAllStyleColumn() : void
    {
        $sql = 'DELETE FROM fetch_table';
        $this->connection->executeUpdate($sql);

        $this->connection->insert('fetch_table', ['test_int' => 1, 'test_string' => 'foo']);
        $this->connection->insert('fetch_table', ['test_int' => 10, 'test_string' => 'foo']);

        $sql  = 'SELECT test_int FROM fetch_table';
        $rows = $this->connection->query($sql)->fetchAll(FetchMode::COLUMN);

        self::assertEquals([1, 10], $rows);
    }

    /**
     * @group DBAL-214
     */
    public function testSetFetchModeClassFetchAll() : void
    {
        $this->beforeFetchClassTest();
        $this->setupFixture();

        $sql  = 'SELECT * FROM fetch_table';
        $stmt = $this->connection->query($sql);
        $stmt->setFetchMode(FetchMode::CUSTOM_OBJECT, MyFetchClass::class);

        $results = $stmt->fetchAll();

        self::assertCount(1, $results);
        self::assertInstanceOf(MyFetchClass::class, $results[0]);

        self::assertEquals(1, $results[0]->test_int);
        self::assertEquals('foo', $results[0]->test_string);
        self::assertStringStartsWith('2010-01-01 10:10:10', $results[0]->test_datetime);
    }

    /**
     * @group DBAL-214
     */
    public function testSetFetchModeClassFetch() : void
    {
        $this->beforeFetchClassTest();
        $this->setupFixture();

        $sql  = 'SELECT * FROM fetch_table';
        $stmt = $this->connection->query($sql);
        $stmt->setFetchMode(FetchMode::CUSTOM_OBJECT, MyFetchClass::class);

        $results = [];
        while ($row = $stmt->fetch()) {
            $results[] = $row;
        }

        self::assertCount(1, $results);
        self::assertInstanceOf(MyFetchClass::class, $results[0]);

        self::assertEquals(1, $results[0]->test_int);
        self::assertEquals('foo', $results[0]->test_string);
        self::assertStringStartsWith('2010-01-01 10:10:10', $results[0]->test_datetime);
    }

    /**
     * @group DBAL-257
     */
    public function testEmptyFetchColumnReturnsFalse() : void
    {
        $this->connection->beginTransaction();
        $this->connection->exec('DELETE FROM fetch_table');
        self::assertFalse($this->connection->fetchColumn('SELECT test_int FROM fetch_table'));
        self::assertFalse($this->connection->query('SELECT test_int FROM fetch_table')->fetchColumn());
        $this->connection->rollBack();
    }

    /**
     * @group DBAL-339
     */
    public function testSetFetchModeOnDbalStatement() : void
    {
        $sql  = 'SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?';
        $stmt = $this->connection->executeQuery($sql, [1, 'foo']);
        $stmt->setFetchMode(FetchMode::NUMERIC);

        $row = $stmt->fetch();

        self::assertArrayHasKey(0, $row);
        self::assertArrayHasKey(1, $row);
        self::assertFalse($stmt->fetch());
    }

    /**
     * @group DBAL-435
     */
    public function testEmptyParameters() : void
    {
        $sql  = 'SELECT * FROM fetch_table WHERE test_int IN (?)';
        $stmt = $this->connection->executeQuery($sql, [[]], [Connection::PARAM_INT_ARRAY]);
        $rows = $stmt->fetchAll();

        self::assertEquals([], $rows);
    }

    /**
     * @group DBAL-1028
     */
    public function testFetchColumnNullValue() : void
    {
        $this->connection->executeUpdate(
            'INSERT INTO fetch_table (test_int, test_string) VALUES (?, ?)',
            [2, 'foo']
        );

        self::assertNull(
            $this->connection->fetchColumn('SELECT test_datetime FROM fetch_table WHERE test_int = ?', [2])
        );
    }

    /**
     * @group DBAL-1028
     */
    public function testFetchColumnNoResult() : void
    {
        self::assertFalse(
            $this->connection->fetchColumn('SELECT test_int FROM fetch_table WHERE test_int = ?', [-1])
        );
    }

    private function setupFixture() : void
    {
        $this->connection->exec('DELETE FROM fetch_table');
        $this->connection->insert('fetch_table', [
            'test_int'      => 1,
            'test_string'   => 'foo',
            'test_datetime' => '2010-01-01 10:10:10',
        ]);
    }

    private function beforeFetchClassTest() : void
    {
        $driver = $this->connection->getDriver();

        if ($driver instanceof Oci8Driver) {
            $this->markTestSkipped('Not supported by OCI8');
        }

        if ($driver instanceof MySQLiDriver) {
            $this->markTestSkipped('Mysqli driver dont support this feature.');
        }

        if (! $driver instanceof PDOOracleDriver) {
            return;
        }

        /** @var PDOConnection $connection */
        $connection = $this->connection->getWrappedConnection();
        $connection->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
    }
}

class MyFetchClass
{
    /** @var int */
    public $test_int;

    /** @var string */
    public $test_string;

    /** @var string */
    public $test_datetime;
}
