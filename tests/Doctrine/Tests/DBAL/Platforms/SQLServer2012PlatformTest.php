<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Schema\Sequence;

class SQLServer2012PlatformTest extends AbstractSQLServerPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new SQLServer2012Platform();
    }

    public function testSupportsSequences(): void
    {
        self::assertTrue($this->platform->supportsSequences());
    }

    public function testDoesNotPreferSequences(): void
    {
        self::assertFalse($this->platform->prefersSequences());
    }

    public function testGeneratesSequenceSqlCommands(): void
    {
        $sequence = new Sequence('myseq', 20, 1);
        self::assertEquals(
            'CREATE SEQUENCE myseq START WITH 1 INCREMENT BY 20 MINVALUE 1',
            $this->platform->getCreateSequenceSQL($sequence)
        );
        self::assertEquals(
            'ALTER SEQUENCE myseq INCREMENT BY 20',
            $this->platform->getAlterSequenceSQL($sequence)
        );
        self::assertEquals(
            'DROP SEQUENCE myseq',
            $this->platform->getDropSequenceSQL('myseq')
        );
        self::assertEquals(
            'SELECT NEXT VALUE FOR myseq',
            $this->platform->getSequenceNextValSQL('myseq')
        );
    }

    public function testModifyLimitQuery(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        self::assertEquals('SELECT * FROM user ORDER BY (SELECT 0) OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10);
        self::assertEquals('SELECT * FROM user ORDER BY (SELECT 0) OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithOffset(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10, 5);
        self::assertEquals('SELECT * FROM user ORDER BY username DESC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithAscOrderBy(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user ORDER BY username ASC', 10);
        self::assertEquals('SELECT * FROM user ORDER BY username ASC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithLowercaseOrderBy(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user order by username', 10);
        self::assertEquals('SELECT * FROM user order by username OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithDescOrderBy(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10);
        self::assertEquals('SELECT * FROM user ORDER BY username DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithMultipleOrderBy(): void
    {
        $sql = $this->platform->modifyLimitQuery(
            'SELECT * FROM user ORDER BY username DESC, usereamil ASC',
            10
        );

        self::assertEquals(
            'SELECT * FROM user ORDER BY username DESC, usereamil ASC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql
        );
    }

    public function testModifyLimitQueryWithSubSelect(): void
    {
        $sql = $this->platform->modifyLimitQuery(
            'SELECT * FROM (SELECT u.id as uid, u.name as uname) dctrn_result',
            10
        );

        self::assertEquals(
            'SELECT * FROM ('
                . 'SELECT u.id as uid, u.name as uname'
                . ') dctrn_result ORDER BY (SELECT 0) OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql
        );
    }

    public function testModifyLimitQueryWithSubSelectAndOrder(): void
    {
        $sql = $this->platform->modifyLimitQuery(
            'SELECT * FROM ('
                . 'SELECT u.id as uid, u.name as uname'
                . ') dctrn_result ORDER BY uname DESC',
            10
        );

        self::assertEquals(
            'SELECT * FROM ('
                . 'SELECT u.id as uid, u.name as uname'
                . ') dctrn_result ORDER BY uname DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql
        );

        $sql = $this->platform->modifyLimitQuery(
            'SELECT * FROM (SELECT u.id, u.name) dctrn_result ORDER BY name DESC',
            10
        );

        self::assertEquals(
            'SELECT * FROM ('
                . 'SELECT u.id, u.name'
                . ') dctrn_result ORDER BY name DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql
        );
    }

    public function testModifyLimitQueryWithSubSelectAndMultipleOrder(): void
    {
        $sql = $this->platform->modifyLimitQuery(
            'SELECT * FROM (SELECT u.id as uid, u.name as uname) dctrn_result ORDER BY uname DESC, uid ASC',
            10,
            5
        );

        self::assertEquals(
            'SELECT * FROM ('
                . 'SELECT u.id as uid, u.name as uname'
                . ') dctrn_result ORDER BY uname DESC, uid ASC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql
        );

        $sql = $this->platform->modifyLimitQuery(
            'SELECT * FROM (SELECT u.id uid, u.name uname) dctrn_result ORDER BY uname DESC, uid ASC',
            10,
            5
        );

        self::assertEquals(
            'SELECT * FROM ('
                . 'SELECT u.id uid, u.name uname'
                . ') dctrn_result ORDER BY uname DESC, uid ASC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql
        );

        $sql = $this->platform->modifyLimitQuery(
            'SELECT * FROM (SELECT u.id, u.name) dctrn_result ORDER BY name DESC, id ASC',
            10,
            5
        );

        self::assertEquals(
            'SELECT * FROM ('
                . 'SELECT u.id, u.name'
                . ') dctrn_result ORDER BY name DESC, id ASC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql
        );
    }

    public function testModifyLimitQueryWithFromColumnNames(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT a.fromFoo, fromBar FROM foo', 10);

        self::assertEquals(
            'SELECT a.fromFoo, fromBar FROM foo ORDER BY (SELECT 0) OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql
        );
    }

    public function testModifyLimitQueryWithExtraLongQuery(): void
    {
        $expected = 'SELECT table1.column1, table2.column2, table3.column3, table4.column4, '
            . 'table5.column5, table6.column6, table7.column7, table8.column8'
            . ' FROM table1, table2, table3, table4, table5, table6, table7, table8'
            . ' WHERE (table1.column1 = table2.column2) AND (table1.column1 = table3.column3)'
            . ' AND (table1.column1 = table4.column4) AND (table1.column1 = table5.column5)'
            . ' AND (table1.column1 = table6.column6) AND (table1.column1 = table7.column7)'
            . ' AND (table1.column1 = table8.column8)'
            . ' AND (table2.column2 = table3.column3) AND (table2.column2 = table4.column4)'
            . ' AND (table2.column2 = table5.column5) AND (table2.column2 = table6.column6)'
            . ' AND (table2.column2 = table7.column7) AND (table2.column2 = table8.column8)'
            . ' AND (table3.column3 = table4.column4) AND (table3.column3 = table5.column5)'
            . ' AND (table3.column3 = table6.column6) AND (table3.column3 = table7.column7)'
            . ' AND (table3.column3 = table8.column8)'
            . ' AND (table4.column4 = table5.column5) AND (table4.column4 = table6.column6)'
            . ' AND (table4.column4 = table7.column7) AND (table4.column4 = table8.column8)'
            . ' AND (table5.column5 = table6.column6) AND (table5.column5 = table7.column7)'
            . ' AND (table5.column5 = table8.column8)'
            . ' AND (table6.column6 = table7.column7) AND (table6.column6 = table8.column8)'
            . ' AND (table7.column7 = table8.column8)'
            . ' ORDER BY (SELECT 0) OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY';

        self::assertEquals(
            $expected,
            $this->platform->modifyLimitQuery(
                $this->getExtraLongQuery(),
                10
            )
        );
    }

    public function testModifyLimitQueryWithOrderByClause(): void
    {
        $sql = 'SELECT m0_.NOMBRE AS NOMBRE0, m0_.FECHAINICIO AS FECHAINICIO1, m0_.FECHAFIN AS FECHAFIN2'
            . ' FROM MEDICION m0_'
            . ' INNER JOIN ESTUDIO e1_ ON m0_.ESTUDIO_ID = e1_.ID'
            . ' INNER JOIN CLIENTE c2_ ON e1_.CLIENTE_ID = c2_.ID'
            . ' INNER JOIN USUARIO u3_ ON c2_.ID = u3_.CLIENTE_ID'
            . ' WHERE u3_.ID = ? ORDER BY m0_.FECHAINICIO DESC';

        $expected = 'SELECT m0_.NOMBRE AS NOMBRE0, m0_.FECHAINICIO AS FECHAINICIO1, m0_.FECHAFIN AS FECHAFIN2'
            . ' FROM MEDICION m0_'
            . ' INNER JOIN ESTUDIO e1_ ON m0_.ESTUDIO_ID = e1_.ID'
            . ' INNER JOIN CLIENTE c2_ ON e1_.CLIENTE_ID = c2_.ID'
            . ' INNER JOIN USUARIO u3_ ON c2_.ID = u3_.CLIENTE_ID'
            . ' WHERE u3_.ID = ? ORDER BY m0_.FECHAINICIO DESC'
            . ' OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY';

        self::assertEquals(
            $expected,
            $this->platform->modifyLimitQuery($sql, 10, 5)
        );
    }

    public function testModifyLimitQueryWithSubSelectInSelectList(): void
    {
        $sql = $this->platform->modifyLimitQuery(
            'SELECT ' .
            'u.id, ' .
            '(u.foo/2) foodiv, ' .
            'CONCAT(u.bar, u.baz) barbaz, ' .
            '(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) ' .
            'FROM profile p WHERE p.user_id = u.id) login_count ' .
            'FROM user u ' .
            "WHERE u.status = 'disabled'",
            10
        );

        self::assertEquals(
            'SELECT ' .
            'u.id, ' .
            '(u.foo/2) foodiv, ' .
            'CONCAT(u.bar, u.baz) barbaz, ' .
            '(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) ' .
            'FROM profile p WHERE p.user_id = u.id) login_count ' .
            'FROM user u ' .
            "WHERE u.status = 'disabled' " .
            'ORDER BY (SELECT 0) OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql
        );
    }

    public function testModifyLimitQueryWithSubSelectInSelectListAndOrderByClause(): void
    {
        $sql = $this->platform->modifyLimitQuery(
            'SELECT ' .
            'u.id, ' .
            '(u.foo/2) foodiv, ' .
            'CONCAT(u.bar, u.baz) barbaz, ' .
            '(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) ' .
            'FROM profile p WHERE p.user_id = u.id) login_count ' .
            'FROM user u ' .
            "WHERE u.status = 'disabled' " .
            'ORDER BY u.username DESC',
            10,
            5
        );

        self::assertEquals(
            'SELECT ' .
            'u.id, ' .
            '(u.foo/2) foodiv, ' .
            'CONCAT(u.bar, u.baz) barbaz, ' .
            '(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) ' .
            'FROM profile p WHERE p.user_id = u.id) login_count ' .
            'FROM user u ' .
            "WHERE u.status = 'disabled' " .
            'ORDER BY u.username DESC ' .
            'OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql
        );
    }

    public function testModifyLimitQueryWithAggregateFunctionInOrderByClause(): void
    {
        $sql = $this->platform->modifyLimitQuery(
            'SELECT ' .
            'MAX(heading_id) aliased, ' .
            'code ' .
            'FROM operator_model_operator ' .
            'GROUP BY code ' .
            'ORDER BY MAX(heading_id) DESC',
            1,
            0
        );

        self::assertEquals(
            'SELECT ' .
            'MAX(heading_id) aliased, ' .
            'code ' .
            'FROM operator_model_operator ' .
            'GROUP BY code ' .
            'ORDER BY MAX(heading_id) DESC ' .
            'OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY',
            $sql
        );
    }

    public function testModifyLimitQueryWithFromSubquery(): void
    {
        $query = 'SELECT DISTINCT id_0 FROM ('
            . 'SELECT k0_.id AS id_0 FROM key_measure k0_ WHERE (k0_.id_zone in(2))'
            . ') dctrn_result';

        $expected = 'SELECT DISTINCT id_0 FROM ('
            . 'SELECT k0_.id AS id_0 FROM key_measure k0_ WHERE (k0_.id_zone in(2))'
            . ') dctrn_result ORDER BY 1 OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY';

        self::assertEquals(
            $expected,
            $this->platform->modifyLimitQuery($query, 10)
        );
    }

    public function testModifyLimitQueryWithFromSubqueryAndOrder(): void
    {
        $query = 'SELECT DISTINCT id_0, value_1 FROM ('
            . 'SELECT k0_.id AS id_0, k0_.value AS value_1 FROM key_measure k0_ WHERE (k0_.id_zone in(2))'
            . ') dctrn_result ORDER BY value_1 DESC';

        $expected = 'SELECT DISTINCT id_0, value_1 FROM ('
            . 'SELECT k0_.id AS id_0, k0_.value AS value_1 FROM key_measure k0_ WHERE (k0_.id_zone in(2))'
            . ') dctrn_result ORDER BY value_1 DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY';

        self::assertEquals(
            $expected,
            $this->platform->modifyLimitQuery($query, 10)
        );
    }

    public function testModifyLimitQueryWithComplexOrderByExpression(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM table ORDER BY (table.x * table.y) DESC', 10);

        $expected = 'SELECT * FROM table ORDER BY (table.x * table.y) DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY';

        self::assertEquals($sql, $expected);
    }

    public function testModifyLimitSubqueryWithJoinAndSubqueryOrderedByColumnFromBaseTable(): void
    {
        $querySql   = 'SELECT DISTINCT id_0, name_1 '
            . 'FROM ('
            . 'SELECT t1.id AS id_0, t2.name AS name_1 '
            . 'FROM table_parent t1 '
            . 'LEFT JOIN join_table t2 ON t1.id = t2.table_id'
            . ') dctrn_result '
            . 'ORDER BY id_0 ASC';
        $alteredSql = 'SELECT DISTINCT id_0, name_1 '
            . 'FROM ('
            . 'SELECT t1.id AS id_0, t2.name AS name_1 '
            . 'FROM table_parent t1 '
            . 'LEFT JOIN join_table t2 ON t1.id = t2.table_id'
            . ') dctrn_result '
            . 'ORDER BY id_0 ASC OFFSET 0 ROWS FETCH NEXT 5 ROWS ONLY';
        $sql        = $this->platform->modifyLimitQuery($querySql, 5);
        self::assertEquals($alteredSql, $sql);
    }

    public function testModifyLimitSubqueryWithJoinAndSubqueryOrderedByColumnFromJoinTable(): void
    {
        $querySql   = 'SELECT DISTINCT id_0, name_1 '
            . 'FROM ('
            . 'SELECT t1.id AS id_0, t2.name AS name_1 '
            . 'FROM table_parent t1 '
            . 'LEFT JOIN join_table t2 ON t1.id = t2.table_id'
            . ') dctrn_result '
            . 'ORDER BY name_1 ASC';
        $alteredSql = 'SELECT DISTINCT id_0, name_1 '
            . 'FROM ('
            . 'SELECT t1.id AS id_0, t2.name AS name_1 '
            . 'FROM table_parent t1 '
            . 'LEFT JOIN join_table t2 ON t1.id = t2.table_id'
            . ') dctrn_result '
            . 'ORDER BY name_1 ASC OFFSET 0 ROWS FETCH NEXT 5 ROWS ONLY';
        $sql        = $this->platform->modifyLimitQuery($querySql, 5);
        self::assertEquals($alteredSql, $sql);
    }

    public function testModifyLimitSubqueryWithJoinAndSubqueryOrderedByColumnsFromBothTables(): void
    {
        $querySql   = 'SELECT DISTINCT id_0, name_1, foo_2 '
            . 'FROM ('
            . 'SELECT t1.id AS id_0, t2.name AS name_1, t2.foo AS foo_2 '
            . 'FROM table_parent t1 '
            . 'LEFT JOIN join_table t2 ON t1.id = t2.table_id'
            . ') dctrn_result '
            . 'ORDER BY name_1 ASC, foo_2 DESC';
        $alteredSql = 'SELECT DISTINCT id_0, name_1, foo_2 '
            . 'FROM ('
            . 'SELECT t1.id AS id_0, t2.name AS name_1, t2.foo AS foo_2 '
            . 'FROM table_parent t1 '
            . 'LEFT JOIN join_table t2 ON t1.id = t2.table_id'
            . ') dctrn_result '
            . 'ORDER BY name_1 ASC, foo_2 DESC OFFSET 0 ROWS FETCH NEXT 5 ROWS ONLY';
        $sql        = $this->platform->modifyLimitQuery($querySql, 5);
        self::assertEquals($alteredSql, $sql);
    }

    public function testModifyLimitSubquerySimple(): void
    {
        $query = 'SELECT DISTINCT id_0 FROM ('
            . 'SELECT k0_.id AS id_0, k0_.column AS column_1 FROM key_table k0_ WHERE (k0_.where_column IN (1))'
            . ') dctrn_result';

        $expected = 'SELECT DISTINCT id_0 FROM ('
            . 'SELECT k0_.id AS id_0, k0_.column AS column_1 FROM key_table k0_ WHERE (k0_.where_column IN (1))'
            . ') dctrn_result ORDER BY 1 OFFSET 0 ROWS FETCH NEXT 20 ROWS ONLY';

        self::assertEquals(
            $expected,
            $this->platform->modifyLimitQuery($query, 20)
        );
    }

    public function testModifyLimitQueryWithTopNSubQueryWithOrderBy(): void
    {
        $query = 'SELECT * FROM test t WHERE t.id = (SELECT TOP 1 t2.id FROM test t2 ORDER BY t2.data DESC)';

        $expected = 'SELECT * FROM test t WHERE t.id = ('
            . 'SELECT TOP 1 t2.id FROM test t2 ORDER BY t2.data DESC'
            . ') ORDER BY (SELECT 0) OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY';

        self::assertEquals(
            $expected,
            $this->platform->modifyLimitQuery($query, 10)
        );

        $query = 'SELECT * FROM test t WHERE t.id = ('
            . 'SELECT TOP 1 t2.id FROM test t2 ORDER BY t2.data DESC'
            . ') ORDER BY t.data2 DESC';

        $expected = 'SELECT * FROM test t WHERE t.id = ('
            . 'SELECT TOP 1 t2.id FROM test t2 ORDER BY t2.data DESC'
            . ') ORDER BY t.data2 DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY';

        self::assertEquals(
            $expected,
            $this->platform->modifyLimitQuery($query, 10)
        );
    }

    public function testModifyLimitQueryWithNewlineBeforeOrderBy(): void
    {
        $querySql    = "SELECT * FROM test\nORDER BY col DESC";
        $expectedSql = "SELECT * FROM test\nORDER BY col DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY";
        $sql         = $this->platform->modifyLimitQuery($querySql, 10);
        self::assertEquals($expectedSql, $sql);
    }

    public function testModifyLimitQueryWithOrderByWithSubqueryWithOrderBy(): void
    {
        $subquery    = 'SELECT col2 from test ORDER BY col2 OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY';
        $querySql    = 'SELECT col1 FROM test ORDER BY ( ' . $subquery . ' )';
        $expectedSql = 'SELECT col1 FROM test ORDER BY ( ' . $subquery . ' )'
            . ' OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY';
        $sql         = $this->platform->modifyLimitQuery($querySql, 10);
        self::assertEquals($expectedSql, $sql);
    }
}
