<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Connection;
use PDO;

require_once __DIR__ . '/../../TestInit.php';

class DataAccessTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function setUp()
    {
        parent::setUp();

        try {
            /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
            $table = new \Doctrine\DBAL\Schema\Table("fetch_table");
            $table->addColumn('test_int', 'integer');
            $table->addColumn('test_string', 'string');
            $table->addColumn('test_datetime', 'datetime', array('notnull' => false));

            $sm = $this->_conn->getSchemaManager();
            $sm->createTable($table);

            $this->_conn->insert('fetch_table', array('test_int' => 1, 'test_string' => 'foo', 'test_datetime' => '2010-01-01 10:10:10'));
        } catch(\Exception $e) {
            
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

    public function testFetchRow()
    {
        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $row = $this->_conn->fetchAssoc($sql, array(1, 'foo'));

        $row = array_change_key_case($row, \CASE_LOWER);
        
        $this->assertEquals(1, $row['test_int']);
        $this->assertEquals('foo', $row['test_string']);
    }

    public function testFetchArray()
    {
        $sql = "SELECT test_int, test_string FROM fetch_table WHERE test_int = ? AND test_string = ?";
        $row = $this->_conn->fetchArray($sql, array(1, 'foo'));

        $this->assertEquals(1, $row[0]);
        $this->assertEquals('foo', $row[1]);
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
            array(1 => 1,               2 => 'foo',             3 => $datetime),
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
     * @group DDC-1014
     */
    public function testDateArithmetics()
    {
        $p = $this->_conn->getDatabasePlatform();
        $sql = 'SELECT ';
        $sql .= $p->getDateDiffExpression('test_datetime', $p->getCurrentTimestampSQL()) .' AS diff, ';
        $sql .= $p->getDateAddDaysExpression('test_datetime', 10) .' AS add_days, ';
        $sql .= $p->getDateSubDaysExpression('test_datetime', 10) .' AS sub_days, ';
        $sql .= $p->getDateAddMonthExpression('test_datetime', 2) .' AS add_month, ';
        $sql .= $p->getDateSubMonthExpression('test_datetime', 2) .' AS sub_month ';
        $sql .= 'FROM fetch_table';

        $row = $this->_conn->fetchAssoc($sql);
        $row = array_change_key_case($row, CASE_LOWER);

        $diff = floor( (strtotime('2010-01-01')-time()) / 3600 / 24);
        $this->assertEquals($diff, (int)$row['diff'], "Date difference should be approx. ".$diff." days.", 1);
        $this->assertEquals('2010-01-11', date('Y-m-d', strtotime($row['add_days'])), "Adding date should end up on 2010-01-11");
        $this->assertEquals('2009-12-22', date('Y-m-d', strtotime($row['sub_days'])), "Subtracting date should end up on 2009-12-22");
        $this->assertEquals('2010-03-01', date('Y-m-d', strtotime($row['add_month'])), "Adding month should end up on 2010-03-01");
        $this->assertEquals('2009-11-01', date('Y-m-d', strtotime($row['sub_month'])), "Adding month should end up on 2009-11-01");
    }

    public function testQuoteSQLInjection()
    {
        $sql = "SELECT * FROM fetch_table WHERE test_string = " . $this->_conn->quote("bar' OR '1'='1");
        $rows = $this->_conn->fetchAll($sql);

        $this->assertEquals(0, count($rows), "no result should be returned, otherwise SQL injection is possible");
    }
}