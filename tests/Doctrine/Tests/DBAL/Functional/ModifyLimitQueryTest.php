<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;
use const CASE_LOWER;
use function array_change_key_case;
use function count;

class ModifyLimitQueryTest extends DbalFunctionalTestCase
{
    /** @var bool */
    private static $tableCreated = false;

    protected function setUp()
    {
        parent::setUp();

        if (! self::$tableCreated) {
            /** @var AbstractSchemaManager $sm */
            $table = new Table('modify_limit_table');
            $table->addColumn('test_int', 'integer');
            $table->setPrimaryKey(['test_int']);

            $table2 = new Table('modify_limit_table2');
            $table2->addColumn('id', 'integer', ['autoincrement' => true]);
            $table2->addColumn('test_int', 'integer');
            $table2->setPrimaryKey(['id']);

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
        $this->_conn->insert('modify_limit_table', ['test_int' => 1]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 2]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 3]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 4]);

        $sql = 'SELECT * FROM modify_limit_table ORDER BY test_int ASC';

        $this->assertLimitResult([1, 2, 3, 4], $sql, 10, 0);
        $this->assertLimitResult([1, 2], $sql, 2, 0);
        $this->assertLimitResult([3, 4], $sql, 2, 2);
        $this->assertLimitResult([2, 3, 4], $sql, null, 1);
    }

    public function testModifyLimitQueryJoinQuery()
    {
        $this->_conn->insert('modify_limit_table', ['test_int' => 1]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 2]);

        $this->_conn->insert('modify_limit_table2', ['test_int' => 1]);
        $this->_conn->insert('modify_limit_table2', ['test_int' => 1]);
        $this->_conn->insert('modify_limit_table2', ['test_int' => 1]);
        $this->_conn->insert('modify_limit_table2', ['test_int' => 2]);
        $this->_conn->insert('modify_limit_table2', ['test_int' => 2]);

        $sql = 'SELECT modify_limit_table.test_int FROM modify_limit_table INNER JOIN modify_limit_table2 ON modify_limit_table.test_int = modify_limit_table2.test_int ORDER BY modify_limit_table.test_int DESC';

        $this->assertLimitResult([2, 2, 1, 1, 1], $sql, 10, 0);
        $this->assertLimitResult([1, 1, 1], $sql, 3, 2);
        $this->assertLimitResult([2, 2], $sql, 2, 0);
    }

    public function testModifyLimitQueryNonDeterministic()
    {
        $this->_conn->insert('modify_limit_table', ['test_int' => 1]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 2]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 3]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 4]);

        $sql = 'SELECT * FROM modify_limit_table';

        $this->assertLimitResult([4, 3, 2, 1], $sql, 10, 0, false);
        $this->assertLimitResult([4, 3], $sql, 2, 0, false);
        $this->assertLimitResult([2, 1], $sql, 2, 2, false);
    }

    public function testModifyLimitQueryGroupBy()
    {
        $this->_conn->insert('modify_limit_table', ['test_int' => 1]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 2]);

        $this->_conn->insert('modify_limit_table2', ['test_int' => 1]);
        $this->_conn->insert('modify_limit_table2', ['test_int' => 1]);
        $this->_conn->insert('modify_limit_table2', ['test_int' => 1]);
        $this->_conn->insert('modify_limit_table2', ['test_int' => 2]);
        $this->_conn->insert('modify_limit_table2', ['test_int' => 2]);

        $sql = 'SELECT modify_limit_table.test_int FROM modify_limit_table ' .
               'INNER JOIN modify_limit_table2 ON modify_limit_table.test_int = modify_limit_table2.test_int ' .
               'GROUP BY modify_limit_table.test_int ' .
               'ORDER BY modify_limit_table.test_int ASC';
        $this->assertLimitResult([1, 2], $sql, 10, 0);
        $this->assertLimitResult([1], $sql, 1, 0);
        $this->assertLimitResult([2], $sql, 1, 1);
    }

    public function testModifyLimitQuerySubSelect()
    {
        $this->_conn->insert('modify_limit_table', ['test_int' => 1]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 2]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 3]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 4]);

        $sql = 'SELECT modify_limit_table.*, (SELECT COUNT(*) FROM modify_limit_table) AS cnt FROM modify_limit_table ORDER BY test_int DESC';

        $this->assertLimitResult([4, 3, 2, 1], $sql, 10, 0);
        $this->assertLimitResult([4, 3], $sql, 2, 0);
        $this->assertLimitResult([2, 1], $sql, 2, 2);
    }

    public function testModifyLimitQueryFromSubSelect()
    {
        $this->_conn->insert('modify_limit_table', ['test_int' => 1]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 2]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 3]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 4]);

        $sql = 'SELECT * FROM (SELECT * FROM modify_limit_table) sub ORDER BY test_int DESC';

        $this->assertLimitResult([4, 3, 2, 1], $sql, 10, 0);
        $this->assertLimitResult([4, 3], $sql, 2, 0);
        $this->assertLimitResult([2, 1], $sql, 2, 2);
    }

    public function testModifyLimitQueryLineBreaks()
    {
        $this->_conn->insert('modify_limit_table', ['test_int' => 1]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 2]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 3]);

        $sql = <<<SQL
SELECT
*
FROM
modify_limit_table
ORDER
BY
test_int
ASC
SQL;

        $this->assertLimitResult([2], $sql, 1, 1);
    }

    public function testModifyLimitQueryZeroOffsetNoLimit()
    {
        $this->_conn->insert('modify_limit_table', ['test_int' => 1]);
        $this->_conn->insert('modify_limit_table', ['test_int' => 2]);

        $sql = 'SELECT test_int FROM modify_limit_table ORDER BY test_int ASC';

        $this->assertLimitResult([1, 2], $sql, null, 0);
    }

    public function assertLimitResult($expectedResults, $sql, $limit, $offset, $deterministic = true)
    {
        $p    = $this->_conn->getDatabasePlatform();
        $data = [];
        foreach ($this->_conn->fetchAll($p->modifyLimitQuery($sql, $limit, $offset)) as $row) {
            $row    = array_change_key_case($row, CASE_LOWER);
            $data[] = $row['test_int'];
        }

        /**
         * Do not assert the order of results when results are non-deterministic
         */
        if ($deterministic) {
            self::assertEquals($expectedResults, $data);
        } else {
            self::assertCount(count($expectedResults), $data);
        }
    }
}
