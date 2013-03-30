<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Connection;
use PDO;

require_once __DIR__ . '/../../TestInit.php';

class ModifyLimitQueryTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    private static $tableCreated = false;

    public function setUp()
    {
        parent::setUp();

        if (!self::$tableCreated) {
            /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
            $table = new \Doctrine\DBAL\Schema\Table("modify_limit_table");
            $table->addColumn('test_int', 'integer');
            $table->setPrimaryKey(array('test_int'));

            $table2 = new \Doctrine\DBAL\Schema\Table("modify_limit_table2");
            $table2->addColumn('id', 'integer', array('autoincrement' => true));
            $table2->addColumn('test_int', 'integer');
            $table2->setPrimaryKey(array('id'));

            $sm = $this->_conn->getSchemaManager();
            $sm->createTable($table);
            $sm->createTable($table2);
            self::$tableCreated = true;
        }
        $this->_conn->exec($this->_conn->getDatabasePlatform()->getTruncateTableSQL('modify_limit_table'));
        $this->_conn->exec($this->_conn->getDatabasePlatform()->getTruncateTableSQL('modify_limit_table2'));
    }

    public function testModifyLimitQuerySimpleQuery()
    {
        $this->_conn->insert('modify_limit_table', array('test_int' => 1));
        $this->_conn->insert('modify_limit_table', array('test_int' => 2));
        $this->_conn->insert('modify_limit_table', array('test_int' => 3));
        $this->_conn->insert('modify_limit_table', array('test_int' => 4));

        $sql = "SELECT * FROM modify_limit_table ORDER BY test_int ASC";

        $this->assertLimitResult(array(1, 2, 3, 4), $sql, 10, 0);
        $this->assertLimitResult(array(1, 2), $sql, 2, 0);
        $this->assertLimitResult(array(3, 4), $sql, 2, 2);
    }

    public function testModifyLimitQueryJoinQuery()
    {
        $this->_conn->insert('modify_limit_table', array('test_int' => 1));
        $this->_conn->insert('modify_limit_table', array('test_int' => 2));

        $this->_conn->insert('modify_limit_table2', array('test_int' => 1));
        $this->_conn->insert('modify_limit_table2', array('test_int' => 1));
        $this->_conn->insert('modify_limit_table2', array('test_int' => 1));
        $this->_conn->insert('modify_limit_table2', array('test_int' => 2));
        $this->_conn->insert('modify_limit_table2', array('test_int' => 2));

        $sql = "SELECT modify_limit_table.test_int FROM modify_limit_table INNER JOIN modify_limit_table2 ON modify_limit_table.test_int = modify_limit_table2.test_int ORDER BY modify_limit_table.test_int DESC";

        $this->assertLimitResult(array(2, 2, 1, 1, 1), $sql, 10, 0);
        $this->assertLimitResult(array(1, 1, 1), $sql, 3, 2);
        $this->assertLimitResult(array(2, 2), $sql, 2, 0);
    }

    public function testModifyLimitQueryNonDeterministic()
    {
        $this->_conn->insert('modify_limit_table', array('test_int' => 1));
        $this->_conn->insert('modify_limit_table', array('test_int' => 2));
        $this->_conn->insert('modify_limit_table', array('test_int' => 3));
        $this->_conn->insert('modify_limit_table', array('test_int' => 4));

        $sql = "SELECT * FROM modify_limit_table";

        $this->assertLimitResult(array(4, 3, 2, 1), $sql, 10, 0, false);
        $this->assertLimitResult(array(4, 3), $sql, 2, 0, false);
        $this->assertLimitResult(array(2, 1), $sql, 2, 2, false);
    }

    public function testModifyLimitQueryGroupBy()
    {
        $this->_conn->insert('modify_limit_table', array('test_int' => 1));
        $this->_conn->insert('modify_limit_table', array('test_int' => 2));

        $this->_conn->insert('modify_limit_table2', array('test_int' => 1));
        $this->_conn->insert('modify_limit_table2', array('test_int' => 1));
        $this->_conn->insert('modify_limit_table2', array('test_int' => 1));
        $this->_conn->insert('modify_limit_table2', array('test_int' => 2));
        $this->_conn->insert('modify_limit_table2', array('test_int' => 2));

        $sql = "SELECT modify_limit_table.test_int FROM modify_limit_table " .
               "INNER JOIN modify_limit_table2 ON modify_limit_table.test_int = modify_limit_table2.test_int ".
               "GROUP BY modify_limit_table.test_int " .
               "ORDER BY modify_limit_table.test_int ASC";
        $this->assertLimitResult(array(1, 2), $sql, 10, 0);
        $this->assertLimitResult(array(1), $sql, 1, 0);
        $this->assertLimitResult(array(2), $sql, 1, 1);
    }

    public function assertLimitResult($expectedResults, $sql, $limit, $offset, $deterministic = true)
    {
        $p = $this->_conn->getDatabasePlatform();
        $data = array();
        foreach ($this->_conn->fetchAll($p->modifyLimitQuery($sql, $limit, $offset)) AS $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $data[] = $row['test_int'];
        }

        /**
         * Do not assert the order of results when results are non-deterministic
         */
        if ($deterministic) {
            $this->assertEquals($expectedResults, $data);
        } else {
            $this->assertCount(count($expectedResults), $data);
        }
    }
}