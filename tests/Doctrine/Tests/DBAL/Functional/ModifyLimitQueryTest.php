<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

use function array_change_key_case;
use function count;
use function preg_replace;

use const CASE_LOWER;

class ModifyLimitQueryTest extends DbalFunctionalTestCase
{
    /** @var bool */
    private static $tableCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$tableCreated) {
            $table = new Table('modify_limit_table');
            $table->addColumn('test_int', 'integer');
            $table->setPrimaryKey(['test_int']);

            $table2 = new Table('modify_limit_table2');
            $table2->addColumn('id', 'integer', ['autoincrement' => true]);
            $table2->addColumn('test_int', 'integer');
            $table2->setPrimaryKey(['id']);

            $sm = $this->connection->getSchemaManager();
            $sm->createTable($table);
            $sm->createTable($table2);
            self::$tableCreated = true;
        }

        $this->connection->exec($this->connection->getDatabasePlatform()->getTruncateTableSQL('modify_limit_table'));
        $this->connection->exec($this->connection->getDatabasePlatform()->getTruncateTableSQL('modify_limit_table2'));
    }

    public function testModifyLimitQuerySimpleQuery(): void
    {
        $this->connection->insert('modify_limit_table', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table', ['test_int' => 2]);
        $this->connection->insert('modify_limit_table', ['test_int' => 3]);
        $this->connection->insert('modify_limit_table', ['test_int' => 4]);

        $sql = 'SELECT * FROM modify_limit_table ORDER BY test_int ASC';

        $this->assertLimitResult([1, 2, 3, 4], $sql, 10, 0);
        $this->assertLimitResult([1, 2], $sql, 2, 0);
        $this->assertLimitResult([3, 4], $sql, 2, 2);
        $this->assertLimitResult([2, 3, 4], $sql, null, 1);
    }

    public function testModifyLimitQueryJoinQuery(): void
    {
        $this->connection->insert('modify_limit_table', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table', ['test_int' => 2]);

        $this->connection->insert('modify_limit_table2', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table2', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table2', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table2', ['test_int' => 2]);
        $this->connection->insert('modify_limit_table2', ['test_int' => 2]);

        $sql = 'SELECT modify_limit_table.test_int'
            . ' FROM modify_limit_table'
            . ' INNER JOIN modify_limit_table2'
            . ' ON modify_limit_table.test_int = modify_limit_table2.test_int'
            . ' ORDER BY modify_limit_table.test_int DESC';

        $this->assertLimitResult([2, 2, 1, 1, 1], $sql, 10, 0);
        $this->assertLimitResult([1, 1, 1], $sql, 3, 2);
        $this->assertLimitResult([2, 2], $sql, 2, 0);
    }

    public function testModifyLimitQueryNonDeterministic(): void
    {
        $this->connection->insert('modify_limit_table', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table', ['test_int' => 2]);
        $this->connection->insert('modify_limit_table', ['test_int' => 3]);
        $this->connection->insert('modify_limit_table', ['test_int' => 4]);

        $sql = 'SELECT * FROM modify_limit_table';

        $this->assertLimitResult([4, 3, 2, 1], $sql, 10, 0, false);
        $this->assertLimitResult([4, 3], $sql, 2, 0, false);
        $this->assertLimitResult([2, 1], $sql, 2, 2, false);
    }

    public function testModifyLimitQueryGroupBy(): void
    {
        $this->connection->insert('modify_limit_table', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table', ['test_int' => 2]);

        $this->connection->insert('modify_limit_table2', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table2', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table2', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table2', ['test_int' => 2]);
        $this->connection->insert('modify_limit_table2', ['test_int' => 2]);

        $sql = 'SELECT modify_limit_table.test_int FROM modify_limit_table ' .
               'INNER JOIN modify_limit_table2 ON modify_limit_table.test_int = modify_limit_table2.test_int ' .
               'GROUP BY modify_limit_table.test_int ' .
               'ORDER BY modify_limit_table.test_int ASC';
        $this->assertLimitResult([1, 2], $sql, 10, 0);
        $this->assertLimitResult([1], $sql, 1, 0);
        $this->assertLimitResult([2], $sql, 1, 1);
    }

    public function testModifyLimitQuerySubSelect(): void
    {
        $this->connection->insert('modify_limit_table', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table', ['test_int' => 2]);
        $this->connection->insert('modify_limit_table', ['test_int' => 3]);
        $this->connection->insert('modify_limit_table', ['test_int' => 4]);

        $sql = 'SELECT modify_limit_table.*, (SELECT COUNT(*) FROM modify_limit_table) AS cnt'
            . ' FROM modify_limit_table ORDER BY test_int DESC';

        $this->assertLimitResult([4, 3, 2, 1], $sql, 10, 0);
        $this->assertLimitResult([4, 3], $sql, 2, 0);
        $this->assertLimitResult([2, 1], $sql, 2, 2);
    }

    public function testModifyLimitQueryFromSubSelect(): void
    {
        $this->connection->insert('modify_limit_table', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table', ['test_int' => 2]);
        $this->connection->insert('modify_limit_table', ['test_int' => 3]);
        $this->connection->insert('modify_limit_table', ['test_int' => 4]);

        $sql = 'SELECT * FROM (SELECT * FROM modify_limit_table) sub ORDER BY test_int DESC';

        $this->assertLimitResult([4, 3, 2, 1], $sql, 10, 0);
        $this->assertLimitResult([4, 3], $sql, 2, 0);
        $this->assertLimitResult([2, 1], $sql, 2, 2);
    }

    public function testModifyLimitQueryLineBreaks(): void
    {
        $this->connection->insert('modify_limit_table', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table', ['test_int' => 2]);
        $this->connection->insert('modify_limit_table', ['test_int' => 3]);

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

    public function testModifyLimitQueryZeroOffsetNoLimit(): void
    {
        $this->connection->insert('modify_limit_table', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table', ['test_int' => 2]);

        $sql = 'SELECT test_int FROM modify_limit_table ORDER BY test_int ASC';

        $this->assertLimitResult([1, 2], $sql, null, 0);
    }

    /**
     * @param array<int, int> $expectedResults
     */
    private function assertLimitResult(
        array $expectedResults,
        string $sql,
        ?int $limit,
        int $offset,
        bool $deterministic = true
    ): void {
        $p    = $this->connection->getDatabasePlatform();
        $data = [];
        foreach ($this->connection->fetchAll($p->modifyLimitQuery($sql, $limit, $offset)) as $row) {
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

    public function testLimitWhenOrderByWithSubqueryWithOrderBy(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof DB2Platform) {
            self::markTestSkipped('DB2 cannot handle ORDER BY in subquery');
        }

        if ($platform instanceof OraclePlatform) {
            $this->markTestSkipped('Oracle cannot handle ORDER BY in subquery');
        }

        $this->connection->insert('modify_limit_table2', ['test_int' => 3]);
        $this->connection->insert('modify_limit_table2', ['test_int' => 1]);
        $this->connection->insert('modify_limit_table2', ['test_int' => 2]);

        $subquery = 'SELECT test_int FROM modify_limit_table2 T2 WHERE T1.id=T2.id ORDER BY test_int';

        // [SQL Server]The ORDER BY clause is invalid in views, inline functions, derived tables, subqueries,
        // and common table expressions, unless TOP, OFFSET or FOR XML is also specified.
        if (
            $platform instanceof SQLServerPlatform
            && ! $platform instanceof SQLServer2012Platform
        ) {
            $subquery = preg_replace('/^SELECT\s/i', 'SELECT TOP 1 ', $subquery);
        }

        if ($platform instanceof SQLServer2012Platform) {
            $subquery .= ' OFFSET 0 ROWS';
        }

        $sql = 'SELECT test_int FROM modify_limit_table2 T1 ORDER BY (' . $subquery . ') ASC';

        $this->assertLimitResult([1, 2, 3], $sql, 10, 0);
    }
}
