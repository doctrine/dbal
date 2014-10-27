<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use PDO;

require_once __DIR__ . '/../../TestInit.php';

class DataAccessTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    static private $generated = false;

    public function setUp()
    {
        parent::setUp();

        if (self::$generated === false) {
            /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
            $table = new \Doctrine\DBAL\Schema\Table("fetch_table");
            $table->addColumn('test_int', 'integer');
            $table->addColumn('test_string', 'string');
            $table->addColumn('test_datetime', 'datetime', array('notnull' => false));
            $table->setPrimaryKey(array('test_int'));

            $sm = $this->_conn->getSchemaManager();
            $sm->createTable($table);

            $this->_conn->insert('fetch_table', array('test_int' => 1, 'test_string' => 'foo', 'test_datetime' => '2010-01-01 10:10:10'));
            self::$generated = true;
        }
    }

    public function testPrepareWithBindValue()
    {
        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $stmt = $this->_conn->prepare($sql);
        $this->assertInstanceOf('Doctrine\DBAL\Statement', $stmt);

        $stmt->bindValue(1, 1);
        $stmt->bindValue(2, 'foo');
        $stmt->execute();

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $row = array_change_key_case($row, \CASE_LOWER);
        $this->assertEquals(array('test_int' => 1, 'test_string' => 'foo'), $row);
    }

    public function testPrepareWithBindParam()
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $stmt = $this->_conn->prepare($sql);
        $this->assertInstanceOf('Doctrine\DBAL\Statement', $stmt);

        $stmt->bindParam(1, $paramInt);
        $stmt->bindParam(2, $paramStr);
        $stmt->execute();

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $row = array_change_key_case($row, \CASE_LOWER);
        $this->assertEquals(array('test_int' => 1, 'test_string' => 'foo'), $row);
    }

    public function testPrepareWithFetchAll()
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $stmt = $this->_conn->prepare($sql);
        $this->assertInstanceOf('Doctrine\DBAL\Statement', $stmt);

        $stmt->bindParam(1, $paramInt);
        $stmt->bindParam(2, $paramStr);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $rows[0] = array_change_key_case($rows[0], \CASE_LOWER);
        $this->assertEquals(array('test_int' => 1, 'test_string' => 'foo'), $rows[0]);
    }

    /**
     * @group DBAL-228
     */
    public function testPrepareWithFetchAllBoth()
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $stmt = $this->_conn->prepare($sql);
        $this->assertInstanceOf('Doctrine\DBAL\Statement', $stmt);

        $stmt->bindParam(1, $paramInt);
        $stmt->bindParam(2, $paramStr);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_BOTH);
        $rows[0] = array_change_key_case($rows[0], \CASE_LOWER);
        $this->assertEquals(array('test_int' => 1, 'test_string' => 'foo', 0 => 1, 1 => 'foo'), $rows[0]);
    }

    public function testPrepareWithFetchColumn()
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql = "SELECT test_int FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $stmt = $this->_conn->prepare($sql);
        $this->assertInstanceOf('Doctrine\DBAL\Statement', $stmt);

        $stmt->bindParam(1, $paramInt);
        $stmt->bindParam(2, $paramStr);
        $stmt->execute();

        $column = $stmt->fetchColumn();
        $this->assertEquals(1, $column);
    }

    public function testPrepareWithIterator()
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $stmt = $this->_conn->prepare($sql);
        $this->assertInstanceOf('Doctrine\DBAL\Statement', $stmt);

        $stmt->bindParam(1, $paramInt);
        $stmt->bindParam(2, $paramStr);
        $stmt->execute();

        $rows = array();
        $stmt->setFetchMode(\PDO::FETCH_ASSOC);
        foreach ($stmt as $row) {
            $rows[] = array_change_key_case($row, \CASE_LOWER);
        }

        $this->assertEquals(array('test_int' => 1, 'test_string' => 'foo'), $rows[0]);
    }

    public function testPrepareWithQuoted()
    {
        $table = 'fetch_table';
        $paramInt = 1;
        $paramStr = 'foo';

        $sql = "SELECT test_int, test_string FROM " . $this->_conn->quoteIdentifier($table) . " ".
               "WHERE test_int = " . $this->_conn->quote($paramInt) . " AND test_string = " . $this->_conn->quote($paramStr);
        $stmt = $this->_conn->prepare($sql);
        $this->assertInstanceOf('Doctrine\DBAL\Statement', $stmt);
    }

    public function testPrepareWithExecuteParams()
    {
        $paramInt = 1;
        $paramStr = 'foo';

        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $stmt = $this->_conn->prepare($sql);
        $this->assertInstanceOf('Doctrine\DBAL\Statement', $stmt);
        $stmt->execute(array($paramInt, $paramStr));

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertTrue($row !== false);
        $row = array_change_key_case($row, \CASE_LOWER);
        $this->assertEquals(array('test_int' => 1, 'test_string' => 'foo'), $row);
    }

    public function testFetchAll()
    {
        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $data = $this->_conn->fetchAll($sql, array(1, 'foo'));

        $this->assertEquals(1, count($data));

        $row = $data[0];
        $this->assertEquals(2, count($row));

        $row = array_change_key_case($row, \CASE_LOWER);
        $this->assertEquals(1, $row['test_int']);
        $this->assertEquals('foo', $row['test_string']);
    }

    /**
     * @group DBAL-209
     */
    public function testFetchAllWithTypes()
    {
        $datetimeString = '2010-01-01 10:10:10';
        $datetime = new \DateTime($datetimeString);
        $sql = "SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?";
        $data = $this->_conn->fetchAll($sql, array(1, $datetime), array(PDO::PARAM_STR, Type::DATETIME));

        $this->assertEquals(1, count($data));

        $row = $data[0];
        $this->assertEquals(2, count($row));

        $row = array_change_key_case($row, \CASE_LOWER);
        $this->assertEquals(1, $row['test_int']);
        $this->assertStringStartsWith($datetimeString, $row['test_datetime']);
    }

    /**
     * @group DBAL-209
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testFetchAllWithMissingTypes()
    {
        if ($this->_conn->getDriver() instanceof \Doctrine\DBAL\Driver\Mysqli\Driver ||
            $this->_conn->getDriver() instanceof \Doctrine\DBAL\Driver\SQLSrv\Driver) {
            $this->markTestSkipped('mysqli and sqlsrv actually supports this');
        }

        $datetimeString = '2010-01-01 10:10:10';
        $datetime = new \DateTime($datetimeString);
        $sql = "SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?";
        $data = $this->_conn->fetchAll($sql, array(1, $datetime));
    }

    public function testFetchBoth()
    {
        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $row = $this->_conn->executeQuery($sql, array(1, 'foo'))->fetch(\PDO::FETCH_BOTH);

        $this->assertTrue($row !== false);

        $row = array_change_key_case($row, \CASE_LOWER);

        $this->assertEquals(1, $row['test_int']);
        $this->assertEquals('foo', $row['test_string']);
        $this->assertEquals(1, $row[0]);
        $this->assertEquals('foo', $row[1]);
    }

    public function testFetchAssoc()
    {
        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $row = $this->_conn->fetchAssoc($sql, array(1, 'foo'));

        $this->assertTrue($row !== false);

        $row = array_change_key_case($row, \CASE_LOWER);

        $this->assertEquals(1, $row['test_int']);
        $this->assertEquals('foo', $row['test_string']);
    }

    public function testFetchAssocWithTypes()
    {
        $datetimeString = '2010-01-01 10:10:10';
        $datetime = new \DateTime($datetimeString);
        $sql = "SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?";
        $row = $this->_conn->fetchAssoc($sql, array(1, $datetime), array(PDO::PARAM_STR, Type::DATETIME));

        $this->assertTrue($row !== false);

        $row = array_change_key_case($row, \CASE_LOWER);

        $this->assertEquals(1, $row['test_int']);
        $this->assertStringStartsWith($datetimeString, $row['test_datetime']);
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testFetchAssocWithMissingTypes()
    {
        if ($this->_conn->getDriver() instanceof \Doctrine\DBAL\Driver\Mysqli\Driver ||
            $this->_conn->getDriver() instanceof \Doctrine\DBAL\Driver\SQLSrv\Driver) {
            $this->markTestSkipped('mysqli and sqlsrv actually supports this');
        }

        $datetimeString = '2010-01-01 10:10:10';
        $datetime = new \DateTime($datetimeString);
        $sql = "SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?";
        $row = $this->_conn->fetchAssoc($sql, array(1, $datetime));
    }

    public function testFetchArray()
    {
        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $row = $this->_conn->fetchArray($sql, array(1, 'foo'));

        $this->assertEquals(1, $row[0]);
        $this->assertEquals('foo', $row[1]);
    }

    public function testFetchArrayWithTypes()
    {
        $datetimeString = '2010-01-01 10:10:10';
        $datetime = new \DateTime($datetimeString);
        $sql = "SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?";
        $row = $this->_conn->fetchArray($sql, array(1, $datetime), array(PDO::PARAM_STR, Type::DATETIME));

        $this->assertTrue($row !== false);

        $row = array_change_key_case($row, \CASE_LOWER);

        $this->assertEquals(1, $row[0]);
        $this->assertStringStartsWith($datetimeString, $row[1]);
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testFetchArrayWithMissingTypes()
    {
        if ($this->_conn->getDriver() instanceof \Doctrine\DBAL\Driver\Mysqli\Driver ||
            $this->_conn->getDriver() instanceof \Doctrine\DBAL\Driver\SQLSrv\Driver) {
            $this->markTestSkipped('mysqli and sqlsrv actually supports this');
        }

        $datetimeString = '2010-01-01 10:10:10';
        $datetime = new \DateTime($datetimeString);
        $sql = "SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?";
        $row = $this->_conn->fetchArray($sql, array(1, $datetime));
    }

    public function testFetchColumn()
    {
        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $testInt = $this->_conn->fetchColumn($sql, array(1, 'foo'), 0);

        $this->assertEquals(1, $testInt);

        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $testString = $this->_conn->fetchColumn($sql, array(1, 'foo'), 1);

        $this->assertEquals('foo', $testString);
    }

    public function testFetchColumnWithTypes()
    {
        $datetimeString = '2010-01-01 10:10:10';
        $datetime = new \DateTime($datetimeString);
        $sql = "SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?";
        $column = $this->_conn->fetchColumn($sql, array(1, $datetime), 1, array(PDO::PARAM_STR, Type::DATETIME));

        $this->assertTrue($column !== false);

        $this->assertStringStartsWith($datetimeString, $column);
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testFetchColumnWithMissingTypes()
    {
        if ($this->_conn->getDriver() instanceof \Doctrine\DBAL\Driver\Mysqli\Driver ||
            $this->_conn->getDriver() instanceof \Doctrine\DBAL\Driver\SQLSrv\Driver) {
            $this->markTestSkipped('mysqli and sqlsrv actually supports this');
        }

        $datetimeString = '2010-01-01 10:10:10';
        $datetime = new \DateTime($datetimeString);
        $sql = "SELECT test_int, test_datetime FROM fetch_table WHERE test_int = ? AND test_datetime = ?";
        $column = $this->_conn->fetchColumn($sql, array(1, $datetime), 1);
    }

    /**
     * @group DDC-697
     */
    public function testExecuteQueryBindDateTimeType()
    {
        $sql = 'SELECT count(*) AS c FROM fetch_table WHERE test_datetime = ?';
        $stmt = $this->_conn->executeQuery($sql,
            array(1 => new \DateTime('2010-01-01 10:10:10')),
            array(1 => Type::DATETIME)
        );

        $this->assertEquals(1, $stmt->fetchColumn());
    }

    /**
     * @group DDC-697
     */
    public function testExecuteUpdateBindDateTimeType()
    {
        $datetime = new \DateTime('2010-02-02 20:20:20');

        $sql = 'INSERT INTO fetch_table (test_int, test_string, test_datetime) VALUES (?, ?, ?)';
        $affectedRows = $this->_conn->executeUpdate($sql,
            array(1 => 50,              2 => 'foo',             3 => $datetime),
            array(1 => PDO::PARAM_INT,  2 => PDO::PARAM_STR,    3 => Type::DATETIME)
        );

        $this->assertEquals(1, $affectedRows);
        $this->assertEquals(1, $this->_conn->executeQuery(
            'SELECT count(*) AS c FROM fetch_table WHERE test_datetime = ?',
            array(1 => $datetime),
            array(1 => Type::DATETIME)
        )->fetchColumn());
    }

    /**
     * @group DDC-697
     */
    public function testPrepareQueryBindValueDateTimeType()
    {
        $sql = 'SELECT count(*) AS c FROM fetch_table WHERE test_datetime = ?';
        $stmt = $this->_conn->prepare($sql);
        $stmt->bindValue(1, new \DateTime('2010-01-01 10:10:10'), Type::DATETIME);
        $stmt->execute();

        $this->assertEquals(1, $stmt->fetchColumn());
    }

    /**
     * @group DBAL-78
     */
    public function testNativeArrayListSupport()
    {
        for ($i = 100; $i < 110; $i++) {
            $this->_conn->insert('fetch_table', array('test_int' => $i, 'test_string' => 'foo' . $i, 'test_datetime' => '2010-01-01 10:10:10'));
        }

        $stmt = $this->_conn->executeQuery('SELECT test_int FROM fetch_table WHERE test_int IN (?)',
            array(array(100, 101, 102, 103, 104)), array(Connection::PARAM_INT_ARRAY));

        $data = $stmt->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals(5, count($data));
        $this->assertEquals(array(array(100), array(101), array(102), array(103), array(104)), $data);

        $stmt = $this->_conn->executeQuery('SELECT test_int FROM fetch_table WHERE test_string IN (?)',
            array(array('foo100', 'foo101', 'foo102', 'foo103', 'foo104')), array(Connection::PARAM_STR_ARRAY));

        $data = $stmt->fetchAll(PDO::FETCH_NUM);
        $this->assertEquals(5, count($data));
        $this->assertEquals(array(array(100), array(101), array(102), array(103), array(104)), $data);
    }

    /**
     * @dataProvider getTrimExpressionData
     */
    public function testTrimExpression($value, $position, $char, $expectedResult)
    {
        $sql = 'SELECT ' .
            $this->_conn->getDatabasePlatform()->getTrimExpression($value, $position, $char) . ' AS trimmed ' .
            'FROM fetch_table';

        $row = $this->_conn->fetchAssoc($sql);
        $row = array_change_key_case($row, CASE_LOWER);

        $this->assertEquals($expectedResult, $row['trimmed']);
    }

    public function getTrimExpressionData()
    {
        return array(
            array('test_string', AbstractPlatform::TRIM_UNSPECIFIED, false, 'foo'),
            array('test_string', AbstractPlatform::TRIM_LEADING, false, 'foo'),
            array('test_string', AbstractPlatform::TRIM_TRAILING, false, 'foo'),
            array('test_string', AbstractPlatform::TRIM_BOTH, false, 'foo'),
            array('test_string', AbstractPlatform::TRIM_UNSPECIFIED, "'f'", 'oo'),
            array('test_string', AbstractPlatform::TRIM_UNSPECIFIED, "'o'", 'f'),
            array('test_string', AbstractPlatform::TRIM_UNSPECIFIED, "'.'", 'foo'),
            array('test_string', AbstractPlatform::TRIM_LEADING, "'f'", 'oo'),
            array('test_string', AbstractPlatform::TRIM_LEADING, "'o'", 'foo'),
            array('test_string', AbstractPlatform::TRIM_LEADING, "'.'", 'foo'),
            array('test_string', AbstractPlatform::TRIM_TRAILING, "'f'", 'foo'),
            array('test_string', AbstractPlatform::TRIM_TRAILING, "'o'", 'f'),
            array('test_string', AbstractPlatform::TRIM_TRAILING, "'.'", 'foo'),
            array('test_string', AbstractPlatform::TRIM_BOTH, "'f'", 'oo'),
            array('test_string', AbstractPlatform::TRIM_BOTH, "'o'", 'f'),
            array('test_string', AbstractPlatform::TRIM_BOTH, "'.'", 'foo'),
            array("' foo '", AbstractPlatform::TRIM_UNSPECIFIED, false, 'foo'),
            array("' foo '", AbstractPlatform::TRIM_LEADING, false, 'foo '),
            array("' foo '", AbstractPlatform::TRIM_TRAILING, false, ' foo'),
            array("' foo '", AbstractPlatform::TRIM_BOTH, false, 'foo'),
            array("' foo '", AbstractPlatform::TRIM_UNSPECIFIED, "'f'", ' foo '),
            array("' foo '", AbstractPlatform::TRIM_UNSPECIFIED, "'o'", ' foo '),
            array("' foo '", AbstractPlatform::TRIM_UNSPECIFIED, "'.'", ' foo '),
            array("' foo '", AbstractPlatform::TRIM_UNSPECIFIED, "' '", 'foo'),
            array("' foo '", AbstractPlatform::TRIM_LEADING, "'f'", ' foo '),
            array("' foo '", AbstractPlatform::TRIM_LEADING, "'o'", ' foo '),
            array("' foo '", AbstractPlatform::TRIM_LEADING, "'.'", ' foo '),
            array("' foo '", AbstractPlatform::TRIM_LEADING, "' '", 'foo '),
            array("' foo '", AbstractPlatform::TRIM_TRAILING, "'f'", ' foo '),
            array("' foo '", AbstractPlatform::TRIM_TRAILING, "'o'", ' foo '),
            array("' foo '", AbstractPlatform::TRIM_TRAILING, "'.'", ' foo '),
            array("' foo '", AbstractPlatform::TRIM_TRAILING, "' '", ' foo'),
            array("' foo '", AbstractPlatform::TRIM_BOTH, "'f'", ' foo '),
            array("' foo '", AbstractPlatform::TRIM_BOTH, "'o'", ' foo '),
            array("' foo '", AbstractPlatform::TRIM_BOTH, "'.'", ' foo '),
            array("' foo '", AbstractPlatform::TRIM_BOTH, "' '", 'foo'),
        );
    }

    /**
     * @group DDC-1014
     */
    public function testDateArithmetics()
    {
        $p = $this->_conn->getDatabasePlatform();
        $sql = 'SELECT ';
        $sql .= $p->getDateDiffExpression('test_datetime', $p->getCurrentTimestampSQL()) .' AS diff, ';
        $sql .= $p->getDateAddSecondsExpression('test_datetime', 1) .' AS add_seconds, ';
        $sql .= $p->getDateSubSecondsExpression('test_datetime', 1) .' AS sub_seconds, ';
        $sql .= $p->getDateAddMinutesExpression('test_datetime', 5) .' AS add_minutes, ';
        $sql .= $p->getDateSubMinutesExpression('test_datetime', 5) .' AS sub_minutes, ';
        $sql .= $p->getDateAddHourExpression('test_datetime', 3) .' AS add_hour, ';
        $sql .= $p->getDateSubHourExpression('test_datetime', 3) .' AS sub_hour, ';
        $sql .= $p->getDateAddDaysExpression('test_datetime', 10) .' AS add_days, ';
        $sql .= $p->getDateSubDaysExpression('test_datetime', 10) .' AS sub_days, ';
        $sql .= $p->getDateAddWeeksExpression('test_datetime', 1) .' AS add_weeks, ';
        $sql .= $p->getDateSubWeeksExpression('test_datetime', 1) .' AS sub_weeks, ';
        $sql .= $p->getDateAddMonthExpression('test_datetime', 2) .' AS add_month, ';
        $sql .= $p->getDateSubMonthExpression('test_datetime', 2) .' AS sub_month, ';
        $sql .= $p->getDateAddQuartersExpression('test_datetime', 3) .' AS add_quarters, ';
        $sql .= $p->getDateSubQuartersExpression('test_datetime', 3) .' AS sub_quarters, ';
        $sql .= $p->getDateAddYearsExpression('test_datetime', 6) .' AS add_years, ';
        $sql .= $p->getDateSubYearsExpression('test_datetime', 6) .' AS sub_years ';
        $sql .= 'FROM fetch_table';

        $row = $this->_conn->fetchAssoc($sql);
        $row = array_change_key_case($row, CASE_LOWER);

        $diff = floor( (strtotime('2010-01-01')-time()) / 3600 / 24);
        $this->assertEquals($diff, (int)$row['diff'], "Date difference should be approx. ".$diff." days.", 1);
        $this->assertEquals('2010-01-01 10:10:11', date('Y-m-d H:i:s', strtotime($row['add_seconds'])), "Adding second should end up on 2010-01-01 10:10:11");
        $this->assertEquals('2010-01-01 10:10:09', date('Y-m-d H:i:s', strtotime($row['sub_seconds'])), "Subtracting second should end up on 2010-01-01 10:10:09");
        $this->assertEquals('2010-01-01 10:15:10', date('Y-m-d H:i:s', strtotime($row['add_minutes'])), "Adding minutes should end up on 2010-01-01 10:15:10");
        $this->assertEquals('2010-01-01 10:05:10', date('Y-m-d H:i:s', strtotime($row['sub_minutes'])), "Subtracting minutes should end up on 2010-01-01 10:05:10");
        $this->assertEquals('2010-01-01 13:10', date('Y-m-d H:i', strtotime($row['add_hour'])), "Adding date should end up on 2010-01-01 13:10");
        $this->assertEquals('2010-01-01 07:10', date('Y-m-d H:i', strtotime($row['sub_hour'])), "Subtracting date should end up on 2010-01-01 07:10");
        $this->assertEquals('2010-01-11', date('Y-m-d', strtotime($row['add_days'])), "Adding date should end up on 2010-01-11");
        $this->assertEquals('2009-12-22', date('Y-m-d', strtotime($row['sub_days'])), "Subtracting date should end up on 2009-12-22");
        $this->assertEquals('2010-01-08', date('Y-m-d', strtotime($row['add_weeks'])), "Adding week should end up on 2010-01-08");
        $this->assertEquals('2009-12-25', date('Y-m-d', strtotime($row['sub_weeks'])), "Subtracting week should end up on 2009-12-25");
        $this->assertEquals('2010-03-01', date('Y-m-d', strtotime($row['add_month'])), "Adding month should end up on 2010-03-01");
        $this->assertEquals('2009-11-01', date('Y-m-d', strtotime($row['sub_month'])), "Substracting month should end up on 2009-11-01");
        $this->assertEquals('2010-10-01', date('Y-m-d', strtotime($row['add_quarters'])), "Adding quarters should end up on 2010-04-01");
        $this->assertEquals('2009-04-01', date('Y-m-d', strtotime($row['sub_quarters'])), "Substracting quarters should end up on 2009-10-01");
        $this->assertEquals('2016-01-01', date('Y-m-d', strtotime($row['add_years'])), "Adding years should end up on 2016-01-01");
        $this->assertEquals('2004-01-01', date('Y-m-d', strtotime($row['sub_years'])), "Substracting years should end up on 2004-01-01");
    }

    public function testLocateExpression()
    {
        $platform = $this->_conn->getDatabasePlatform();

        $sql = 'SELECT ';
        $sql .= $platform->getLocateExpression('test_string', "'oo'") .' AS locate1, ';
        $sql .= $platform->getLocateExpression('test_string', "'foo'") .' AS locate2, ';
        $sql .= $platform->getLocateExpression('test_string', "'bar'") .' AS locate3, ';
        $sql .= $platform->getLocateExpression('test_string', 'test_string') .' AS locate4, ';
        $sql .= $platform->getLocateExpression("'foo'", 'test_string') .' AS locate5, ';
        $sql .= $platform->getLocateExpression("'barfoobaz'", 'test_string') .' AS locate6, ';
        $sql .= $platform->getLocateExpression("'bar'", 'test_string') .' AS locate7, ';
        $sql .= $platform->getLocateExpression('test_string', "'oo'", 2) .' AS locate8, ';
        $sql .= $platform->getLocateExpression('test_string', "'oo'", 3) .' AS locate9 ';
        $sql .= 'FROM fetch_table';

        $row = $this->_conn->fetchAssoc($sql);
        $row = array_change_key_case($row, CASE_LOWER);

        $this->assertEquals(2, $row['locate1']);
        $this->assertEquals(1, $row['locate2']);
        $this->assertEquals(0, $row['locate3']);
        $this->assertEquals(1, $row['locate4']);
        $this->assertEquals(1, $row['locate5']);
        $this->assertEquals(4, $row['locate6']);
        $this->assertEquals(0, $row['locate7']);
        $this->assertEquals(2, $row['locate8']);
        $this->assertEquals(0, $row['locate9']);
    }

    public function testQuoteSQLInjection()
    {
        $sql = "SELECT * FROM fetch_table WHERE test_string = " . $this->_conn->quote("bar' OR '1'='1");
        $rows = $this->_conn->fetchAll($sql);

        $this->assertEquals(0, count($rows), "no result should be returned, otherwise SQL injection is possible");
    }

    /**
     * @group DDC-1213
     */
    public function testBitComparisonExpressionSupport()
    {
        $this->_conn->executeQuery('DELETE FROM fetch_table')->execute();
        $platform = $this->_conn->getDatabasePlatform();
        $bitmap   = array();

        for ($i = 2; $i < 9; $i = $i + 2) {
            $bitmap[$i] = array(
                'bit_or'    => ($i | 2),
                'bit_and'   => ($i & 2)
            );
            $this->_conn->insert('fetch_table', array(
                'test_int'      => $i,
                'test_string'   => json_encode($bitmap[$i]),
                'test_datetime' => '2010-01-01 10:10:10'
            ));
        }

        $sql[]  = 'SELECT ';
        $sql[]  = 'test_int, ';
        $sql[]  = 'test_string, ';
        $sql[]  = $platform->getBitOrComparisonExpression('test_int', 2) . ' AS bit_or, ';
        $sql[]  = $platform->getBitAndComparisonExpression('test_int', 2) . ' AS bit_and ';
        $sql[]  = 'FROM fetch_table';

        $stmt   = $this->_conn->executeQuery(implode(PHP_EOL, $sql));
        $data   = $stmt->fetchAll(PDO::FETCH_ASSOC);


        $this->assertEquals(4, count($data));
        $this->assertEquals(count($bitmap), count($data));
        foreach ($data as $row) {
            $row = array_change_key_case($row, CASE_LOWER);

            $this->assertArrayHasKey('test_int', $row);

            $id = $row['test_int'];

            $this->assertArrayHasKey($id, $bitmap);
            $this->assertArrayHasKey($id, $bitmap);

            $this->assertArrayHasKey('bit_or', $row);
            $this->assertArrayHasKey('bit_and', $row);

            $this->assertEquals($row['bit_or'], $bitmap[$id]['bit_or']);
            $this->assertEquals($row['bit_and'], $bitmap[$id]['bit_and']);
        }
    }

    public function testSetDefaultFetchMode()
    {
        $stmt = $this->_conn->query("SELECT * FROM fetch_table");
        $stmt->setFetchMode(\PDO::FETCH_NUM);

        $row = array_keys($stmt->fetch());
        $this->assertEquals(0, count( array_filter($row, function($v) { return ! is_numeric($v); })), "should be no non-numerical elements in the result.");
    }

    /**
     * @group DBAL-196
     */
    public function testFetchAllSupportFetchClass()
    {
        $this->skipOci8AndMysqli();
        $this->setupFixture();

        $sql    = "SELECT test_int, test_string, test_datetime FROM fetch_table";
        $stmt   = $this->_conn->prepare($sql);
        $stmt->execute();

        $results = $stmt->fetchAll(
            \PDO::FETCH_CLASS,
            __NAMESPACE__.'\\MyFetchClass'
        );

        $this->assertEquals(1, count($results));
        $this->assertInstanceOf(__NAMESPACE__.'\\MyFetchClass', $results[0]);

        $this->assertEquals(1, $results[0]->test_int);
        $this->assertEquals('foo', $results[0]->test_string);
        $this->assertStringStartsWith('2010-01-01 10:10:10', $results[0]->test_datetime);
    }

    /**
     * @group DBAL-241
     */
    public function testFetchAllStyleColumn()
    {
        $sql = "DELETE FROM fetch_table";
        $this->_conn->executeUpdate($sql);

        $this->_conn->insert('fetch_table', array('test_int' => 1, 'test_string' => 'foo'));
        $this->_conn->insert('fetch_table', array('test_int' => 10, 'test_string' => 'foo'));

        $sql = "SELECT test_int FROM fetch_table";
        $rows = $this->_conn->query($sql)->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertEquals(array(1, 10), $rows);
    }

    /**
     * @group DBAL-214
     */
    public function testSetFetchModeClassFetchAll()
    {
        $this->skipOci8AndMysqli();
        $this->setupFixture();

        $sql = "SELECT * FROM fetch_table";
        $stmt = $this->_conn->query($sql);
        $stmt->setFetchMode(\PDO::FETCH_CLASS, __NAMESPACE__ . '\\MyFetchClass');

        $results = $stmt->fetchAll();

        $this->assertEquals(1, count($results));
        $this->assertInstanceOf(__NAMESPACE__.'\\MyFetchClass', $results[0]);

        $this->assertEquals(1, $results[0]->test_int);
        $this->assertEquals('foo', $results[0]->test_string);
        $this->assertStringStartsWith('2010-01-01 10:10:10', $results[0]->test_datetime);
    }

    /**
     * @group DBAL-214
     */
    public function testSetFetchModeClassFetch()
    {
        $this->skipOci8AndMysqli();
        $this->setupFixture();

        $sql = "SELECT * FROM fetch_table";
        $stmt = $this->_conn->query($sql);
        $stmt->setFetchMode(\PDO::FETCH_CLASS, __NAMESPACE__ . '\\MyFetchClass');

        $results = array();
        while ($row = $stmt->fetch()) {
            $results[] = $row;
        }

        $this->assertEquals(1, count($results));
        $this->assertInstanceOf(__NAMESPACE__.'\\MyFetchClass', $results[0]);

        $this->assertEquals(1, $results[0]->test_int);
        $this->assertEquals('foo', $results[0]->test_string);
        $this->assertStringStartsWith('2010-01-01 10:10:10', $results[0]->test_datetime);
    }

    /**
     * @group DBAL-257
     */
    public function testEmptyFetchColumnReturnsFalse()
    {
        $this->_conn->executeQuery('DELETE FROM fetch_table')->execute();
        $this->assertFalse($this->_conn->fetchColumn('SELECT test_int FROM fetch_table'));
        $this->assertFalse($this->_conn->query('SELECT test_int FROM fetch_table')->fetchColumn());
    }

    /**
     * @group DBAL-339
     */
    public function testSetFetchModeOnDbalStatement()
    {
        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $stmt = $this->_conn->executeQuery($sql, array(1, "foo"));
        $stmt->setFetchMode(\PDO::FETCH_NUM);

        while ($row = $stmt->fetch()) {
            $this->assertTrue(isset($row[0]));
            $this->assertTrue(isset($row[1]));
        }
    }

    /**
     * @group DBAL-435
     */
    public function testEmptyParameters()
    {
        $sql = "SELECT * FROM fetch_table WHERE test_int IN (?)";
        $stmt = $this->_conn->executeQuery($sql, array(array()), array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));
        $rows = $stmt->fetchAll();

        $this->assertEquals(array(), $rows);
    }

    /**
     * @group DBAL-1028
     */
    public function testFetchColumnNullValue()
    {
        $this->_conn->executeUpdate(
            'INSERT INTO fetch_table (test_int, test_string) VALUES (?, ?)',
            array(1, 'foo')
        );

        $this->assertNull(
            $this->_conn->fetchColumn('SELECT test_datetime FROM fetch_table WHERE test_int = ?', array(1))
        );
    }

    /**
     * @group DBAL-1028
     */
    public function testFetchColumnNonExistingIndex()
    {
        if ($this->_conn->getDriver()->getName() === 'pdo_sqlsrv') {
            $this->markTestSkipped(
                'Test does not work for pdo_sqlsrv driver as it throws a fatal error for a non-existing column index.'
            );
        }

        $this->assertNull(
            $this->_conn->fetchColumn('SELECT test_int FROM fetch_table WHERE test_int = ?', array(1), 1)
        );
    }

    /**
     * @group DBAL-1028
     */
    public function testFetchColumnNoResult()
    {
        $this->assertFalse(
            $this->_conn->fetchColumn('SELECT test_int FROM fetch_table WHERE test_int = ?', array(-1))
        );
    }

    private function setupFixture()
    {
        $this->_conn->executeQuery('DELETE FROM fetch_table')->execute();
        $this->_conn->insert('fetch_table', array(
            'test_int'      => 1,
            'test_string'   => 'foo',
            'test_datetime' => '2010-01-01 10:10:10'
        ));
    }

    private function skipOci8AndMysqli()
    {
        if (isset($GLOBALS['db_type']) && $GLOBALS['db_type'] == "oci8")  {
            $this->markTestSkipped("Not supported by OCI8");
        }
        if ('mysqli' == $this->_conn->getDriver()->getName()) {
            $this->markTestSkipped('Mysqli driver dont support this feature.');
        }
    }
}

class MyFetchClass
{
    public $test_int, $test_string, $test_datetime;
}
