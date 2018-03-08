<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Type;

abstract class AbstractSQLServerPlatformTestCase extends AbstractPlatformTestCase
{
    protected static $selectFromCtePattern = "WITH dctrn_cte AS (%s) SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum FROM dctrn_cte) AS doctrine_tbl WHERE doctrine_rownum BETWEEN %d AND %d ORDER BY doctrine_rownum ASC";

    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id INT IDENTITY NOT NULL, test NVARCHAR(255), PRIMARY KEY (id))';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return array(
            'CREATE TABLE test (foo NVARCHAR(255), bar NVARCHAR(255))',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar) WHERE foo IS NOT NULL AND bar IS NOT NULL'
        );
    }

    public function getGenerateAlterTableSql()
    {
        return array(
            'ALTER TABLE mytable ADD quota INT',
            'ALTER TABLE mytable DROP COLUMN foo',
            'ALTER TABLE mytable ALTER COLUMN baz NVARCHAR(255) NOT NULL',
            "ALTER TABLE mytable ADD CONSTRAINT DF_6B2BD609_78240498 DEFAULT 'def' FOR baz",
            'ALTER TABLE mytable ALTER COLUMN bloo BIT NOT NULL',
            "ALTER TABLE mytable ADD CONSTRAINT DF_6B2BD609_CECED971 DEFAULT '0' FOR bloo",
            "sp_RENAME 'mytable', 'userlist'",
            "DECLARE @sql NVARCHAR(MAX) = N''; " .
            "SELECT @sql += N'EXEC sp_rename N''' + dc.name + ''', N''' " .
            "+ REPLACE(dc.name, '6B2BD609', 'E2B58069') + ''', ''OBJECT'';' " .
            "FROM sys.default_constraints dc " .
            "JOIN sys.tables tbl ON dc.parent_object_id = tbl.object_id " .
            "WHERE tbl.name = 'userlist';" .
            "EXEC sp_executesql @sql"
        );
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testDoesNotSupportRegexp()
    {
        $this->_platform->getRegexpExpression();
    }

    public function testGeneratesSqlSnippets()
    {
        self::assertEquals('CONVERT(date, GETDATE())', $this->_platform->getCurrentDateSQL());
        self::assertEquals('CONVERT(time, GETDATE())', $this->_platform->getCurrentTimeSQL());
        self::assertEquals('CURRENT_TIMESTAMP', $this->_platform->getCurrentTimestampSQL());
        self::assertEquals('"', $this->_platform->getIdentifierQuoteCharacter(), 'Identifier quote character is not correct');
        self::assertEquals('(column1 + column2 + column3)', $this->_platform->getConcatExpression('column1', 'column2', 'column3'), 'Concatenation expression is not correct');
    }

    public function testGeneratesTransactionsCommands()
    {
        self::assertEquals(
            'SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_UNCOMMITTED)
        );
        self::assertEquals(
            'SET TRANSACTION ISOLATION LEVEL READ COMMITTED',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_COMMITTED)
        );
        self::assertEquals(
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::REPEATABLE_READ)
        );
        self::assertEquals(
            'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::SERIALIZABLE)
        );
    }

    public function testGeneratesDDLSnippets()
    {
        $dropDatabaseExpectation = 'DROP DATABASE foobar';

        self::assertEquals('SELECT * FROM sys.databases', $this->_platform->getListDatabasesSQL());
        self::assertEquals('CREATE DATABASE foobar', $this->_platform->getCreateDatabaseSQL('foobar'));
        self::assertEquals($dropDatabaseExpectation, $this->_platform->getDropDatabaseSQL('foobar'));
        self::assertEquals('DROP TABLE foobar', $this->_platform->getDropTableSQL('foobar'));
    }

    public function testGeneratesTypeDeclarationForIntegers()
    {
        self::assertEquals(
                'INT',
                $this->_platform->getIntegerTypeDeclarationSQL(array())
        );
        self::assertEquals(
                'INT IDENTITY',
                $this->_platform->getIntegerTypeDeclarationSQL(array('autoincrement' => true)
        ));
        self::assertEquals(
                'INT IDENTITY',
                $this->_platform->getIntegerTypeDeclarationSQL(
                        array('autoincrement' => true, 'primary' => true)
        ));
    }

    public function testGeneratesTypeDeclarationsForStrings()
    {
        self::assertEquals(
                'NCHAR(10)',
                $this->_platform->getVarcharTypeDeclarationSQL(
                        array('length' => 10, 'fixed' => true)
        ));
        self::assertEquals(
                'NVARCHAR(50)',
                $this->_platform->getVarcharTypeDeclarationSQL(array('length' => 50)),
                'Variable string declaration is not correct'
        );
        self::assertEquals(
                'NVARCHAR(255)',
                $this->_platform->getVarcharTypeDeclarationSQL(array()),
                'Long string declaration is not correct'
        );
        self::assertSame('VARCHAR(MAX)', $this->_platform->getClobTypeDeclarationSQL(array()));
        self::assertSame(
            'VARCHAR(MAX)',
            $this->_platform->getClobTypeDeclarationSQL(array('length' => 5, 'fixed' => true))
        );
    }

    public function testPrefersIdentityColumns()
    {
        self::assertTrue($this->_platform->prefersIdentityColumns());
    }

    public function testSupportsIdentityColumns()
    {
        self::assertTrue($this->_platform->supportsIdentityColumns());
    }

    public function testSupportsCreateDropDatabase()
    {
        self::assertTrue($this->_platform->supportsCreateDropDatabase());
    }

    public function testSupportsSchemas()
    {
        self::assertTrue($this->_platform->supportsSchemas());
    }

    public function testDoesNotSupportSavePoints()
    {
        self::assertTrue($this->_platform->supportsSavepoints());
    }

    public function getGenerateIndexSql()
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateUniqueIndexSql()
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2) WHERE test IS NOT NULL AND test2 IS NOT NULL';
    }

    public function getGenerateForeignKeySql()
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
    }

    public function testModifyLimitQuery()
    {
        $querySql = 'SELECT * FROM user';
        $alteredSql = 'SELECT TOP 10 * FROM user';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10, 0);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 10), $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset()
    {
        $querySql = 'SELECT * FROM user';
        $alteredSql = 'SELECT TOP 10 * FROM user';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 10), $sql);
    }

    public function testModifyLimitQueryWithOffset()
    {
        if ( ! $this->_platform->supportsLimitOffset()) {
            $this->markTestSkipped(sprintf('Platform "%s" does not support offsets in result limiting.', $this->_platform->getName()));
        }

        $querySql = 'SELECT * FROM user ORDER BY username DESC';
        $alteredSql = 'SELECT TOP 15 * FROM user ORDER BY username DESC';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10, 5);

        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 6, 15), $sql);
    }

    public function testModifyLimitQueryWithAscOrderBy()
    {
        $querySql = 'SELECT * FROM user ORDER BY username ASC';
        $alteredSql = 'SELECT TOP 10 * FROM user ORDER BY username ASC';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10);

        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 10), $sql);
    }

    public function testModifyLimitQueryWithLowercaseOrderBy()
    {
        $querySql = 'SELECT * FROM user order by username';
        $alteredSql = 'SELECT TOP 10 * FROM user order by username';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 10), $sql);
    }

    public function testModifyLimitQueryWithDescOrderBy()
    {
        $querySql = 'SELECT * FROM user ORDER BY username DESC';
        $alteredSql = 'SELECT TOP 10 * FROM user ORDER BY username DESC';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 10), $sql);
    }

    public function testModifyLimitQueryWithMultipleOrderBy()
    {
        $querySql = 'SELECT * FROM user ORDER BY username DESC, usereamil ASC';
        $alteredSql = 'SELECT TOP 10 * FROM user ORDER BY username DESC, usereamil ASC';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 10), $sql);
    }

    public function testModifyLimitQueryWithSubSelect()
    {
        $querySql = 'SELECT * FROM (SELECT u.id as uid, u.name as uname) dctrn_result';
        $alteredSql = 'SELECT TOP 10 * FROM (SELECT u.id as uid, u.name as uname) dctrn_result';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 10), $sql);
    }

    public function testModifyLimitQueryWithSubSelectAndOrder()
    {
        $querySql = 'SELECT * FROM (SELECT u.id as uid, u.name as uname ORDER BY u.name DESC) dctrn_result';
        $alteredSql = 'SELECT TOP 10 * FROM (SELECT u.id as uid, u.name as uname) dctrn_result';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 10), $sql);

        $querySql = 'SELECT * FROM (SELECT u.id, u.name ORDER BY u.name DESC) dctrn_result';
        $alteredSql = 'SELECT TOP 10 * FROM (SELECT u.id, u.name) dctrn_result';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 10), $sql);
    }

    public function testModifyLimitQueryWithSubSelectAndMultipleOrder()
    {
        if ( ! $this->_platform->supportsLimitOffset()) {
            $this->markTestSkipped(sprintf('Platform "%s" does not support offsets in result limiting.', $this->_platform->getName()));
        }

        $querySql = 'SELECT * FROM (SELECT u.id as uid, u.name as uname ORDER BY u.name DESC, id ASC) dctrn_result';
        $alteredSql = 'SELECT TOP 15 * FROM (SELECT u.id as uid, u.name as uname) dctrn_result';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10, 5);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 6, 15), $sql);

        $querySql = 'SELECT * FROM (SELECT u.id uid, u.name uname ORDER BY u.name DESC, id ASC) dctrn_result';
        $alteredSql = 'SELECT TOP 15 * FROM (SELECT u.id uid, u.name uname) dctrn_result';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10, 5);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 6, 15), $sql);

        $querySql = 'SELECT * FROM (SELECT u.id, u.name ORDER BY u.name DESC, id ASC) dctrn_result';
        $alteredSql = 'SELECT TOP 15 * FROM (SELECT u.id, u.name) dctrn_result';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10, 5);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 6, 15), $sql);
    }

    public function testModifyLimitQueryWithFromColumnNames()
    {
        $querySql = 'SELECT a.fromFoo, fromBar FROM foo';
        $alteredSql = 'SELECT TOP 10 a.fromFoo, fromBar FROM foo';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 10), $sql);
    }

    /**
     * @group DBAL-927
     */
    public function testModifyLimitQueryWithExtraLongQuery()
    {
        $query = 'SELECT table1.column1, table2.column2, table3.column3, table4.column4, table5.column5, table6.column6, table7.column7, table8.column8 FROM table1, table2, table3, table4, table5, table6, table7, table8 ';
        $query.= 'WHERE (table1.column1 = table2.column2) AND (table1.column1 = table3.column3) AND (table1.column1 = table4.column4) AND (table1.column1 = table5.column5) AND (table1.column1 = table6.column6) AND (table1.column1 = table7.column7) AND (table1.column1 = table8.column8) AND (table2.column2 = table3.column3) AND (table2.column2 = table4.column4) AND (table2.column2 = table5.column5) AND (table2.column2 = table6.column6) ';
        $query.= 'AND (table2.column2 = table7.column7) AND (table2.column2 = table8.column8) AND (table3.column3 = table4.column4) AND (table3.column3 = table5.column5) AND (table3.column3 = table6.column6) AND (table3.column3 = table7.column7) AND (table3.column3 = table8.column8) AND (table4.column4 = table5.column5) AND (table4.column4 = table6.column6) AND (table4.column4 = table7.column7) AND (table4.column4 = table8.column8) ';
        $query.= 'AND (table5.column5 = table6.column6) AND (table5.column5 = table7.column7) AND (table5.column5 = table8.column8) AND (table6.column6 = table7.column7) AND (table6.column6 = table8.column8) AND (table7.column7 = table8.column8)';

        $alteredSql = 'SELECT TOP 10 table1.column1, table2.column2, table3.column3, table4.column4, table5.column5, table6.column6, table7.column7, table8.column8 FROM table1, table2, table3, table4, table5, table6, table7, table8 ';
        $alteredSql.= 'WHERE (table1.column1 = table2.column2) AND (table1.column1 = table3.column3) AND (table1.column1 = table4.column4) AND (table1.column1 = table5.column5) AND (table1.column1 = table6.column6) AND (table1.column1 = table7.column7) AND (table1.column1 = table8.column8) AND (table2.column2 = table3.column3) AND (table2.column2 = table4.column4) AND (table2.column2 = table5.column5) AND (table2.column2 = table6.column6) ';
        $alteredSql.= 'AND (table2.column2 = table7.column7) AND (table2.column2 = table8.column8) AND (table3.column3 = table4.column4) AND (table3.column3 = table5.column5) AND (table3.column3 = table6.column6) AND (table3.column3 = table7.column7) AND (table3.column3 = table8.column8) AND (table4.column4 = table5.column5) AND (table4.column4 = table6.column6) AND (table4.column4 = table7.column7) AND (table4.column4 = table8.column8) ';
        $alteredSql.= 'AND (table5.column5 = table6.column6) AND (table5.column5 = table7.column7) AND (table5.column5 = table8.column8) AND (table6.column6 = table7.column7) AND (table6.column6 = table8.column8) AND (table7.column7 = table8.column8)';

        $sql = $this->_platform->modifyLimitQuery($query, 10);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 10), $sql);
    }

    /**
     * @group DDC-2470
     */
    public function testModifyLimitQueryWithOrderByClause()
    {
        if ( ! $this->_platform->supportsLimitOffset()) {
            $this->markTestSkipped(sprintf('Platform "%s" does not support offsets in result limiting.', $this->_platform->getName()));
        }

        $sql        = 'SELECT m0_.NOMBRE AS NOMBRE0, m0_.FECHAINICIO AS FECHAINICIO1, m0_.FECHAFIN AS FECHAFIN2 FROM MEDICION m0_ WITH (NOLOCK) INNER JOIN ESTUDIO e1_ ON m0_.ESTUDIO_ID = e1_.ID INNER JOIN CLIENTE c2_ ON e1_.CLIENTE_ID = c2_.ID INNER JOIN USUARIO u3_ ON c2_.ID = u3_.CLIENTE_ID WHERE u3_.ID = ? ORDER BY m0_.FECHAINICIO DESC';
        $alteredSql = 'SELECT TOP 15 m0_.NOMBRE AS NOMBRE0, m0_.FECHAINICIO AS FECHAINICIO1, m0_.FECHAFIN AS FECHAFIN2 FROM MEDICION m0_ WITH (NOLOCK) INNER JOIN ESTUDIO e1_ ON m0_.ESTUDIO_ID = e1_.ID INNER JOIN CLIENTE c2_ ON e1_.CLIENTE_ID = c2_.ID INNER JOIN USUARIO u3_ ON c2_.ID = u3_.CLIENTE_ID WHERE u3_.ID = ? ORDER BY m0_.FECHAINICIO DESC';
        $actual     = $this->_platform->modifyLimitQuery($sql, 10, 5);

        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 6, 15), $actual);
    }

    /**
     * @group DBAL-713
     */
    public function testModifyLimitQueryWithSubSelectInSelectList()
    {
        $querySql = "SELECT " .
            "u.id, " .
            "(u.foo/2) foodiv, " .
            "CONCAT(u.bar, u.baz) barbaz, " .
            "(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) FROM profile p WHERE p.user_id = u.id) login_count " .
            "FROM user u " .
            "WHERE u.status = 'disabled'";
        $alteredSql = "SELECT TOP 10 " .
            "u.id, " .
            "(u.foo/2) foodiv, " .
            "CONCAT(u.bar, u.baz) barbaz, " .
            "(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) FROM profile p WHERE p.user_id = u.id) login_count " .
            "FROM user u " .
            "WHERE u.status = 'disabled'";
        $sql = $this->_platform->modifyLimitQuery($querySql, 10);

        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 10), $sql);
    }

    /**
     * @group DBAL-713
     */
    public function testModifyLimitQueryWithSubSelectInSelectListAndOrderByClause()
    {
        if ( ! $this->_platform->supportsLimitOffset()) {
            $this->markTestSkipped(sprintf('Platform "%s" does not support offsets in result limiting.', $this->_platform->getName()));
        }

        $querySql = "SELECT " .
            "u.id, " .
            "(u.foo/2) foodiv, " .
            "CONCAT(u.bar, u.baz) barbaz, " .
            "(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) FROM profile p WHERE p.user_id = u.id) login_count " .
            "FROM user u " .
            "WHERE u.status = 'disabled' " .
            "ORDER BY u.username DESC";
        $alteredSql = "SELECT TOP 15 " .
            "u.id, " .
            "(u.foo/2) foodiv, " .
            "CONCAT(u.bar, u.baz) barbaz, " .
            "(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) FROM profile p WHERE p.user_id = u.id) login_count " .
            "FROM user u " .
            "WHERE u.status = 'disabled' " .
            "ORDER BY u.username DESC";
        $sql = $this->_platform->modifyLimitQuery($querySql, 10, 5);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 6, 15), $sql);
    }

    /**
     * @group DBAL-834
     */
    public function testModifyLimitQueryWithAggregateFunctionInOrderByClause()
    {
        $querySql = "SELECT " .
            "MAX(heading_id) aliased, " .
            "code " .
            "FROM operator_model_operator " .
            "GROUP BY code " .
            "ORDER BY MAX(heading_id) DESC";
        $alteredSql = "SELECT TOP 1 " .
            "MAX(heading_id) aliased, " .
            "code " .
            "FROM operator_model_operator " .
            "GROUP BY code " .
            "ORDER BY MAX(heading_id) DESC";
        $sql = $this->_platform->modifyLimitQuery($querySql, 1, 0);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 1), $sql);
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function testModifyLimitSubqueryWithJoinAndSubqueryOrderedByColumnFromBaseTable()
    {
        $querySql = "SELECT DISTINCT id_0, name_1 "
            . "FROM ("
            . "SELECT t1.id AS id_0, t2.name AS name_1 "
            . "FROM table_parent t1 "
            . "LEFT JOIN join_table t2 ON t1.id = t2.table_id "
            . "ORDER BY t1.id ASC"
            . ") dctrn_result "
            . "ORDER BY id_0 ASC";
        $alteredSql = "SELECT DISTINCT TOP 5 id_0, name_1 "
            . "FROM ("
            . "SELECT t1.id AS id_0, t2.name AS name_1 "
            . "FROM table_parent t1 "
            . "LEFT JOIN join_table t2 ON t1.id = t2.table_id"
            . ") dctrn_result "
            . "ORDER BY id_0 ASC";
        $sql = $this->_platform->modifyLimitQuery($querySql, 5);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 5), $sql);
    }


    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function testModifyLimitSubqueryWithJoinAndSubqueryOrderedByColumnFromJoinTable()
    {
        $querySql = "SELECT DISTINCT id_0, name_1 "
            . "FROM ("
            . "SELECT t1.id AS id_0, t2.name AS name_1 "
            . "FROM table_parent t1 "
            . "LEFT JOIN join_table t2 ON t1.id = t2.table_id "
            . "ORDER BY t2.name ASC"
            . ") dctrn_result "
            . "ORDER BY name_1 ASC";
        $alteredSql = "SELECT DISTINCT TOP 5 id_0, name_1 "
            . "FROM ("
            . "SELECT t1.id AS id_0, t2.name AS name_1 "
            . "FROM table_parent t1 "
            . "LEFT JOIN join_table t2 ON t1.id = t2.table_id"
            . ") dctrn_result "
            . "ORDER BY name_1 ASC";
        $sql = $this->_platform->modifyLimitQuery($querySql, 5);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 5), $sql);
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function testModifyLimitSubqueryWithJoinAndSubqueryOrderedByColumnsFromBothTables()
    {
        $querySql = "SELECT DISTINCT id_0, name_1, foo_2 "
            . "FROM ("
            . "SELECT t1.id AS id_0, t2.name AS name_1, t2.foo AS foo_2 "
            . "FROM table_parent t1 "
            . "LEFT JOIN join_table t2 ON t1.id = t2.table_id "
            . "ORDER BY t2.name ASC, t2.foo DESC"
            . ") dctrn_result "
            . "ORDER BY name_1 ASC, foo_2 DESC";
        $alteredSql = "SELECT DISTINCT TOP 5 id_0, name_1, foo_2 "
            . "FROM ("
            . "SELECT t1.id AS id_0, t2.name AS name_1, t2.foo AS foo_2 "
            . "FROM table_parent t1 "
            . "LEFT JOIN join_table t2 ON t1.id = t2.table_id"
            . ") dctrn_result "
            . "ORDER BY name_1 ASC, foo_2 DESC";
        $sql = $this->_platform->modifyLimitQuery($querySql, 5);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 5), $sql);
    }

    public function testModifyLimitSubquerySimple()
    {
        $querySql = "SELECT DISTINCT id_0 FROM "
            . "(SELECT k0_.id AS id_0, k0_.field AS field_1 "
            . "FROM key_table k0_ WHERE (k0_.where_field IN (1))) dctrn_result";
        $alteredSql = "SELECT DISTINCT TOP 20 id_0 FROM (SELECT k0_.id AS id_0, k0_.field AS field_1 "
            . "FROM key_table k0_ WHERE (k0_.where_field IN (1))) dctrn_result";
        $sql = $this->_platform->modifyLimitQuery($querySql, 20);
        self::assertEquals(sprintf(self::$selectFromCtePattern, $alteredSql, 1, 20), $sql);
    }

    /**
     * @group DDC-1360
     */
    public function testQuoteIdentifier()
    {
        self::assertEquals('[fo][o]', $this->_platform->quoteIdentifier('fo]o'));
        self::assertEquals('[test]', $this->_platform->quoteIdentifier('test'));
        self::assertEquals('[test].[test]', $this->_platform->quoteIdentifier('test.test'));
    }

    /**
     * @group DDC-1360
     */
    public function testQuoteSingleIdentifier()
    {
        self::assertEquals('[fo][o]', $this->_platform->quoteSingleIdentifier('fo]o'));
        self::assertEquals('[test]', $this->_platform->quoteSingleIdentifier('test'));
        self::assertEquals('[test.test]', $this->_platform->quoteSingleIdentifier('test.test'));
    }

    /**
     * @group DBAL-220
     */
    public function testCreateClusteredIndex()
    {
        $idx = new \Doctrine\DBAL\Schema\Index('idx', array('id'));
        $idx->addFlag('clustered');
        self::assertEquals('CREATE CLUSTERED INDEX idx ON tbl (id)', $this->_platform->getCreateIndexSQL($idx, 'tbl'));
    }

    /**
     * @group DBAL-220
     */
    public function testCreateNonClusteredPrimaryKeyInTable()
    {
        $table = new \Doctrine\DBAL\Schema\Table("tbl");
        $table->addColumn("id", "integer");
        $table->setPrimaryKey(Array("id"));
        $table->getIndex('primary')->addFlag('nonclustered');

        self::assertEquals(array('CREATE TABLE tbl (id INT NOT NULL, PRIMARY KEY NONCLUSTERED (id))'), $this->_platform->getCreateTableSQL($table));
    }

    /**
     * @group DBAL-220
     */
    public function testCreateNonClusteredPrimaryKey()
    {
        $idx = new \Doctrine\DBAL\Schema\Index('idx', array('id'), false, true);
        $idx->addFlag('nonclustered');
        self::assertEquals('ALTER TABLE tbl ADD PRIMARY KEY NONCLUSTERED (id)', $this->_platform->getCreatePrimaryKeySQL($idx, 'tbl'));
    }

    public function testAlterAddPrimaryKey()
    {
        $idx = new \Doctrine\DBAL\Schema\Index('idx', array('id'), false, true);
        self::assertEquals('ALTER TABLE tbl ADD PRIMARY KEY (id)', $this->_platform->getCreateIndexSQL($idx, 'tbl'));
    }

    protected function getQuotedColumnInPrimaryKeySQL()
    {
        return array(
            'CREATE TABLE [quoted] ([create] NVARCHAR(255) NOT NULL, PRIMARY KEY ([create]))',
        );
    }

    protected function getQuotedColumnInIndexSQL()
    {
        return array(
            'CREATE TABLE [quoted] ([create] NVARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB ON [quoted] ([create])',
        );
    }

    protected function getQuotedNameInIndexSQL()
    {
        return array(
            'CREATE TABLE test (column1 NVARCHAR(255) NOT NULL)',
            'CREATE INDEX [key] ON test (column1)',
        );
    }

    protected function getQuotedColumnInForeignKeySQL()
    {
        return array(
            'CREATE TABLE [quoted] ([create] NVARCHAR(255) NOT NULL, foo NVARCHAR(255) NOT NULL, [bar] NVARCHAR(255) NOT NULL)',
            'ALTER TABLE [quoted] ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ([create], foo, [bar]) REFERENCES [foreign] ([create], bar, [foo-bar])',
            'ALTER TABLE [quoted] ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ([create], foo, [bar]) REFERENCES foo ([create], bar, [foo-bar])',
            'ALTER TABLE [quoted] ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ([create], foo, [bar]) REFERENCES [foo-bar] ([create], bar, [foo-bar])',
        );
    }

    public function testGetCreateSchemaSQL()
    {
        $schemaName = 'schema';
        $sql = $this->_platform->getCreateSchemaSQL($schemaName);
        self::assertEquals('CREATE SCHEMA ' . $schemaName, $sql);
    }

    /**
     * @group DBAL-543
     */
    public function getCreateTableColumnCommentsSQL()
    {
        return array(
            "CREATE TABLE test (id INT NOT NULL, PRIMARY KEY (id))",
            "EXEC sp_addextendedproperty N'MS_Description', N'This is a comment', N'SCHEMA', dbo, N'TABLE', test, N'COLUMN', id",
        );
    }

    /**
     * @group DBAL-543
     */
    public function getAlterTableColumnCommentsSQL()
    {
        return array(
            "ALTER TABLE mytable ADD quota INT NOT NULL",
            "EXEC sp_addextendedproperty N'MS_Description', N'A comment', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', quota",
            // todo
            //"EXEC sp_addextendedproperty N'MS_Description', N'B comment', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', baz",
        );
    }

    /**
     * @group DBAL-543
     */
    public function getCreateTableColumnTypeCommentsSQL()
    {
        return array(
            "CREATE TABLE test (id INT NOT NULL, data VARCHAR(MAX) NOT NULL, PRIMARY KEY (id))",
            "EXEC sp_addextendedproperty N'MS_Description', N'(DC2Type:array)', N'SCHEMA', dbo, N'TABLE', test, N'COLUMN', data",
        );
    }

    /**
     * @group DBAL-543
     */
    public function testGeneratesCreateTableSQLWithColumnComments()
    {
        $table = new Table('mytable');
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('comment_null', 'integer', array('comment' => null));
        $table->addColumn('comment_false', 'integer', array('comment' => false));
        $table->addColumn('comment_empty_string', 'integer', array('comment' => ''));
        $table->addColumn('comment_integer_0', 'integer', array('comment' => 0));
        $table->addColumn('comment_float_0', 'integer', array('comment' => 0.0));
        $table->addColumn('comment_string_0', 'integer', array('comment' => '0'));
        $table->addColumn('comment', 'integer', array('comment' => 'Doctrine 0wnz you!'));
        $table->addColumn('`comment_quoted`', 'integer', array('comment' => 'Doctrine 0wnz comments for explicitly quoted columns!'));
        $table->addColumn('create', 'integer', array('comment' => 'Doctrine 0wnz comments for reserved keyword columns!'));
        $table->addColumn('commented_type', 'object');
        $table->addColumn('commented_type_with_comment', 'array', array('comment' => 'Doctrine array type.'));
        $table->addColumn('comment_with_string_literal_char', 'string', array('comment' => "O'Reilly"));
        $table->setPrimaryKey(array('id'));

        self::assertEquals(
            array(
                "CREATE TABLE mytable (id INT IDENTITY NOT NULL, comment_null INT NOT NULL, comment_false INT NOT NULL, comment_empty_string INT NOT NULL, comment_integer_0 INT NOT NULL, comment_float_0 INT NOT NULL, comment_string_0 INT NOT NULL, comment INT NOT NULL, [comment_quoted] INT NOT NULL, [create] INT NOT NULL, commented_type VARCHAR(MAX) NOT NULL, commented_type_with_comment VARCHAR(MAX) NOT NULL, comment_with_string_literal_char NVARCHAR(255) NOT NULL, PRIMARY KEY (id))",
                "EXEC sp_addextendedproperty N'MS_Description', N'0', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment_integer_0",
                "EXEC sp_addextendedproperty N'MS_Description', N'0', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment_float_0",
                "EXEC sp_addextendedproperty N'MS_Description', N'0', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment_string_0",
                "EXEC sp_addextendedproperty N'MS_Description', N'Doctrine 0wnz you!', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment",
                "EXEC sp_addextendedproperty N'MS_Description', N'Doctrine 0wnz comments for explicitly quoted columns!', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', [comment_quoted]",
                "EXEC sp_addextendedproperty N'MS_Description', N'Doctrine 0wnz comments for reserved keyword columns!', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', [create]",
                "EXEC sp_addextendedproperty N'MS_Description', N'(DC2Type:object)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', commented_type",
                "EXEC sp_addextendedproperty N'MS_Description', N'Doctrine array type.(DC2Type:array)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', commented_type_with_comment",
                "EXEC sp_addextendedproperty N'MS_Description', N'O''Reilly', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment_with_string_literal_char",
            ),
            $this->_platform->getCreateTableSQL($table)
        );
    }

    /**
     * @group DBAL-543
     * @group DBAL-1011
     */
    public function testGeneratesAlterTableSQLWithColumnComments()
    {
        $table = new Table('mytable');
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('comment_null', 'integer', array('comment' => null));
        $table->addColumn('comment_false', 'integer', array('comment' => false));
        $table->addColumn('comment_empty_string', 'integer', array('comment' => ''));
        $table->addColumn('comment_integer_0', 'integer', array('comment' => 0));
        $table->addColumn('comment_float_0', 'integer', array('comment' => 0.0));
        $table->addColumn('comment_string_0', 'integer', array('comment' => '0'));
        $table->addColumn('comment', 'integer', array('comment' => 'Doctrine 0wnz you!'));
        $table->addColumn('`comment_quoted`', 'integer', array('comment' => 'Doctrine 0wnz comments for explicitly quoted columns!'));
        $table->addColumn('create', 'integer', array('comment' => 'Doctrine 0wnz comments for reserved keyword columns!'));
        $table->addColumn('commented_type', 'object');
        $table->addColumn('commented_type_with_comment', 'array', array('comment' => 'Doctrine array type.'));
        $table->addColumn('comment_with_string_literal_quote_char', 'array', array('comment' => "O'Reilly"));
        $table->setPrimaryKey(array('id'));

        $tableDiff = new TableDiff('mytable');
        $tableDiff->fromTable = $table;
        $tableDiff->addedColumns['added_comment_none'] = new Column('added_comment_none', Type::getType('integer'));
        $tableDiff->addedColumns['added_comment_null'] = new Column('added_comment_null', Type::getType('integer'), array('comment' => null));
        $tableDiff->addedColumns['added_comment_false'] = new Column('added_comment_false', Type::getType('integer'), array('comment' => false));
        $tableDiff->addedColumns['added_comment_empty_string'] = new Column('added_comment_empty_string', Type::getType('integer'), array('comment' => ''));
        $tableDiff->addedColumns['added_comment_integer_0'] = new Column('added_comment_integer_0', Type::getType('integer'), array('comment' => 0));
        $tableDiff->addedColumns['added_comment_float_0'] = new Column('added_comment_float_0', Type::getType('integer'), array('comment' => 0.0));
        $tableDiff->addedColumns['added_comment_string_0'] = new Column('added_comment_string_0', Type::getType('integer'), array('comment' => '0'));
        $tableDiff->addedColumns['added_comment'] = new Column('added_comment', Type::getType('integer'), array('comment' => 'Doctrine'));
        $tableDiff->addedColumns['`added_comment_quoted`'] = new Column('`added_comment_quoted`', Type::getType('integer'), array('comment' => 'rulez'));
        $tableDiff->addedColumns['select'] = new Column('select', Type::getType('integer'), array('comment' => '666'));
        $tableDiff->addedColumns['added_commented_type'] = new Column('added_commented_type', Type::getType('object'));
        $tableDiff->addedColumns['added_commented_type_with_comment'] = new Column('added_commented_type_with_comment', Type::getType('array'), array('comment' => '666'));
        $tableDiff->addedColumns['added_comment_with_string_literal_char'] = new Column('added_comment_with_string_literal_char', Type::getType('string'), array('comment' => "''"));

        $tableDiff->renamedColumns['comment_float_0'] = new Column('comment_double_0', Type::getType('decimal'), array('comment' => 'Double for real!'));

        // Add comment to non-commented column.
        $tableDiff->changedColumns['id'] = new ColumnDiff(
            'id',
            new Column('id', Type::getType('integer'), array('autoincrement' => true, 'comment' => 'primary')),
            array('comment'),
            new Column('id', Type::getType('integer'), array('autoincrement' => true))
        );

        // Remove comment from null-commented column.
        $tableDiff->changedColumns['comment_null'] = new ColumnDiff(
            'comment_null',
            new Column('comment_null', Type::getType('string')),
            array('type'),
            new Column('comment_null', Type::getType('integer'), array('comment' => null))
        );

        // Add comment to false-commented column.
        $tableDiff->changedColumns['comment_false'] = new ColumnDiff(
            'comment_false',
            new Column('comment_false', Type::getType('integer'), array('comment' => 'false')),
            array('comment'),
            new Column('comment_false', Type::getType('integer'), array('comment' => false))
        );

        // Change type to custom type from empty string commented column.
        $tableDiff->changedColumns['comment_empty_string'] = new ColumnDiff(
            'comment_empty_string',
            new Column('comment_empty_string', Type::getType('object')),
            array('type'),
            new Column('comment_empty_string', Type::getType('integer'), array('comment' => ''))
        );

        // Change comment to false-comment from zero-string commented column.
        $tableDiff->changedColumns['comment_string_0'] = new ColumnDiff(
            'comment_string_0',
            new Column('comment_string_0', Type::getType('integer'), array('comment' => false)),
            array('comment'),
            new Column('comment_string_0', Type::getType('integer'), array('comment' => '0'))
        );

        // Remove comment from regular commented column.
        $tableDiff->changedColumns['comment'] = new ColumnDiff(
            'comment',
            new Column('comment', Type::getType('integer')),
            array('comment'),
            new Column('comment', Type::getType('integer'), array('comment' => 'Doctrine 0wnz you!'))
        );

        // Change comment and change type to custom type from regular commented column.
        $tableDiff->changedColumns['`comment_quoted`'] = new ColumnDiff(
            '`comment_quoted`',
            new Column('`comment_quoted`', Type::getType('array'), array('comment' => 'Doctrine array.')),
            array('comment', 'type'),
            new Column('`comment_quoted`', Type::getType('integer'), array('comment' => 'Doctrine 0wnz you!'))
        );

        // Remove comment and change type to custom type from regular commented column.
        $tableDiff->changedColumns['create'] = new ColumnDiff(
            'create',
            new Column('create', Type::getType('object')),
            array('comment', 'type'),
            new Column('create', Type::getType('integer'), array('comment' => 'Doctrine 0wnz comments for reserved keyword columns!'))
        );

        // Add comment and change custom type to regular type from non-commented column.
        $tableDiff->changedColumns['commented_type'] = new ColumnDiff(
            'commented_type',
            new Column('commented_type', Type::getType('integer'), array('comment' => 'foo')),
            array('comment', 'type'),
            new Column('commented_type', Type::getType('object'))
        );

        // Remove comment from commented custom type column.
        $tableDiff->changedColumns['commented_type_with_comment'] = new ColumnDiff(
            'commented_type_with_comment',
            new Column('commented_type_with_comment', Type::getType('array')),
            array('comment'),
            new Column('commented_type_with_comment', Type::getType('array'), array('comment' => 'Doctrine array type.'))
        );

        // Change comment from comment with string literal char column.
        $tableDiff->changedColumns['comment_with_string_literal_char'] = new ColumnDiff(
            'comment_with_string_literal_char',
            new Column('comment_with_string_literal_char', Type::getType('string'), array('comment' => "'")),
            array('comment'),
            new Column('comment_with_string_literal_char', Type::getType('array'), array('comment' => "O'Reilly"))
        );

        $tableDiff->removedColumns['comment_integer_0'] = new Column('comment_integer_0', Type::getType('integer'), array('comment' => 0));

        self::assertEquals(
            array(
                // Renamed columns.
                "sp_RENAME 'mytable.comment_float_0', 'comment_double_0', 'COLUMN'",

                // Added columns.
                "ALTER TABLE mytable ADD added_comment_none INT NOT NULL",
                "ALTER TABLE mytable ADD added_comment_null INT NOT NULL",
                "ALTER TABLE mytable ADD added_comment_false INT NOT NULL",
                "ALTER TABLE mytable ADD added_comment_empty_string INT NOT NULL",
                "ALTER TABLE mytable ADD added_comment_integer_0 INT NOT NULL",
                "ALTER TABLE mytable ADD added_comment_float_0 INT NOT NULL",
                "ALTER TABLE mytable ADD added_comment_string_0 INT NOT NULL",
                "ALTER TABLE mytable ADD added_comment INT NOT NULL",
                "ALTER TABLE mytable ADD [added_comment_quoted] INT NOT NULL",
                "ALTER TABLE mytable ADD [select] INT NOT NULL",
                "ALTER TABLE mytable ADD added_commented_type VARCHAR(MAX) NOT NULL",
                "ALTER TABLE mytable ADD added_commented_type_with_comment VARCHAR(MAX) NOT NULL",
                "ALTER TABLE mytable ADD added_comment_with_string_literal_char NVARCHAR(255) NOT NULL",
                "ALTER TABLE mytable DROP COLUMN comment_integer_0",
                "ALTER TABLE mytable ALTER COLUMN comment_null NVARCHAR(255) NOT NULL",
                "ALTER TABLE mytable ALTER COLUMN comment_empty_string VARCHAR(MAX) NOT NULL",
                "ALTER TABLE mytable ALTER COLUMN [comment_quoted] VARCHAR(MAX) NOT NULL",
                "ALTER TABLE mytable ALTER COLUMN [create] VARCHAR(MAX) NOT NULL",
                "ALTER TABLE mytable ALTER COLUMN commented_type INT NOT NULL",

                // Added columns.
                "EXEC sp_addextendedproperty N'MS_Description', N'0', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', added_comment_integer_0",
                "EXEC sp_addextendedproperty N'MS_Description', N'0', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', added_comment_float_0",
                "EXEC sp_addextendedproperty N'MS_Description', N'0', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', added_comment_string_0",
                "EXEC sp_addextendedproperty N'MS_Description', N'Doctrine', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', added_comment",
                "EXEC sp_addextendedproperty N'MS_Description', N'rulez', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', [added_comment_quoted]",
                "EXEC sp_addextendedproperty N'MS_Description', N'666', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', [select]",
                "EXEC sp_addextendedproperty N'MS_Description', N'(DC2Type:object)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', added_commented_type",
                "EXEC sp_addextendedproperty N'MS_Description', N'666(DC2Type:array)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', added_commented_type_with_comment",
                "EXEC sp_addextendedproperty N'MS_Description', N'''''', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', added_comment_with_string_literal_char",

                // Changed columns.
                "EXEC sp_addextendedproperty N'MS_Description', N'primary', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', id",
                "EXEC sp_addextendedproperty N'MS_Description', N'false', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment_false",
                "EXEC sp_addextendedproperty N'MS_Description', N'(DC2Type:object)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment_empty_string",
                "EXEC sp_dropextendedproperty N'MS_Description', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment_string_0",
                "EXEC sp_dropextendedproperty N'MS_Description', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment",
                "EXEC sp_updateextendedproperty N'MS_Description', N'Doctrine array.(DC2Type:array)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', [comment_quoted]",
                "EXEC sp_updateextendedproperty N'MS_Description', N'(DC2Type:object)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', [create]",
                "EXEC sp_updateextendedproperty N'MS_Description', N'foo', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', commented_type",
                "EXEC sp_updateextendedproperty N'MS_Description', N'(DC2Type:array)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', commented_type_with_comment",
                "EXEC sp_updateextendedproperty N'MS_Description', N'''', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment_with_string_literal_char",
            ),
            $this->_platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @group DBAL-122
     */
    public function testInitializesDoctrineTypeMappings()
    {
        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('bigint'));
        self::assertSame('bigint', $this->_platform->getDoctrineTypeMapping('bigint'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('numeric'));
        self::assertSame('decimal', $this->_platform->getDoctrineTypeMapping('numeric'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('bit'));
        self::assertSame('boolean', $this->_platform->getDoctrineTypeMapping('bit'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('smallint'));
        self::assertSame('smallint', $this->_platform->getDoctrineTypeMapping('smallint'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('decimal'));
        self::assertSame('decimal', $this->_platform->getDoctrineTypeMapping('decimal'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('smallmoney'));
        self::assertSame('integer', $this->_platform->getDoctrineTypeMapping('smallmoney'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('int'));
        self::assertSame('integer', $this->_platform->getDoctrineTypeMapping('int'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('tinyint'));
        self::assertSame('smallint', $this->_platform->getDoctrineTypeMapping('tinyint'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('money'));
        self::assertSame('integer', $this->_platform->getDoctrineTypeMapping('money'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('float'));
        self::assertSame('float', $this->_platform->getDoctrineTypeMapping('float'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('real'));
        self::assertSame('float', $this->_platform->getDoctrineTypeMapping('real'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('double'));
        self::assertSame('float', $this->_platform->getDoctrineTypeMapping('double'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('double precision'));
        self::assertSame('float', $this->_platform->getDoctrineTypeMapping('double precision'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('smalldatetime'));
        self::assertSame('datetime', $this->_platform->getDoctrineTypeMapping('smalldatetime'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('datetime'));
        self::assertSame('datetime', $this->_platform->getDoctrineTypeMapping('datetime'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('char'));
        self::assertSame('string', $this->_platform->getDoctrineTypeMapping('char'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('varchar'));
        self::assertSame('string', $this->_platform->getDoctrineTypeMapping('varchar'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('text'));
        self::assertSame('text', $this->_platform->getDoctrineTypeMapping('text'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('nchar'));
        self::assertSame('string', $this->_platform->getDoctrineTypeMapping('nchar'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('nvarchar'));
        self::assertSame('string', $this->_platform->getDoctrineTypeMapping('nvarchar'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('ntext'));
        self::assertSame('text', $this->_platform->getDoctrineTypeMapping('ntext'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('binary'));
        self::assertSame('binary', $this->_platform->getDoctrineTypeMapping('binary'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('varbinary'));
        self::assertSame('binary', $this->_platform->getDoctrineTypeMapping('varbinary'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('image'));
        self::assertSame('blob', $this->_platform->getDoctrineTypeMapping('image'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('uniqueidentifier'));
        self::assertSame('guid', $this->_platform->getDoctrineTypeMapping('uniqueidentifier'));
    }

    protected function getBinaryMaxLength()
    {
        return 8000;
    }

    public function testReturnsBinaryTypeDeclarationSQL()
    {
        self::assertSame('VARBINARY(255)', $this->_platform->getBinaryTypeDeclarationSQL(array()));
        self::assertSame('VARBINARY(255)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 0)));
        self::assertSame('VARBINARY(8000)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 8000)));
        self::assertSame('VARBINARY(MAX)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 8001)));

        self::assertSame('BINARY(255)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true)));
        self::assertSame('BINARY(255)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 0)));
        self::assertSame('BINARY(8000)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 8000)));
        self::assertSame('VARBINARY(MAX)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 8001)));
    }

    /**
     * @group DBAL-234
     */
    protected function getAlterTableRenameIndexSQL()
    {
        return array(
            "EXEC sp_RENAME N'mytable.idx_foo', N'idx_bar', N'INDEX'",
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexSQL()
    {
        return array(
            "EXEC sp_RENAME N'[table].[create]', N'[select]', N'INDEX'",
            "EXEC sp_RENAME N'[table].[foo]', N'[bar]', N'INDEX'",
        );
    }

    /**
     * @group DBAL-825
     */
    public function testChangeColumnsTypeWithDefaultValue()
    {
        $tableName = 'column_def_change_type';
        $table     = new Table($tableName);

        $table->addColumn('col_int', 'smallint', array('default' => 666));
        $table->addColumn('col_string', 'string', array('default' => 'foo'));

        $tableDiff = new TableDiff($tableName);
        $tableDiff->fromTable = $table;
        $tableDiff->changedColumns['col_int'] = new ColumnDiff(
            'col_int',
            new Column('col_int', Type::getType('integer'), array('default' => 666)),
            array('type'),
            new Column('col_int', Type::getType('smallint'), array('default' => 666))
        );

        $tableDiff->changedColumns['col_string'] = new ColumnDiff(
            'col_string',
            new Column('col_string', Type::getType('string'), array('default' => 666, 'fixed' => true)),
            array('fixed'),
            new Column('col_string', Type::getType('string'), array('default' => 666))
        );

        $expected = $this->_platform->getAlterTableSQL($tableDiff);

        self::assertSame(
            $expected,
            array(
                'ALTER TABLE column_def_change_type DROP CONSTRAINT DF_829302E0_FA2CB292',
                'ALTER TABLE column_def_change_type ALTER COLUMN col_int INT NOT NULL',
                'ALTER TABLE column_def_change_type ADD CONSTRAINT DF_829302E0_FA2CB292 DEFAULT 666 FOR col_int',
                'ALTER TABLE column_def_change_type DROP CONSTRAINT DF_829302E0_2725A6D0',
                'ALTER TABLE column_def_change_type ALTER COLUMN col_string NCHAR(255) NOT NULL',
                "ALTER TABLE column_def_change_type ADD CONSTRAINT DF_829302E0_2725A6D0 DEFAULT '666' FOR col_string",
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableRenameColumnSQL()
    {
        return array(
            "sp_RENAME 'mytable.unquoted1', 'unquoted', 'COLUMN'",
            "sp_RENAME 'mytable.unquoted2', '[where]', 'COLUMN'",
            "sp_RENAME 'mytable.unquoted3', '[foo]', 'COLUMN'",
            "sp_RENAME 'mytable.[create]', 'reserved_keyword', 'COLUMN'",
            "sp_RENAME 'mytable.[table]', '[from]', 'COLUMN'",
            "sp_RENAME 'mytable.[select]', '[bar]', 'COLUMN'",
            "sp_RENAME 'mytable.quoted1', 'quoted', 'COLUMN'",
            "sp_RENAME 'mytable.quoted2', '[and]', 'COLUMN'",
            "sp_RENAME 'mytable.quoted3', '[baz]', 'COLUMN'",
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableChangeColumnLengthSQL()
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    /**
     * @group DBAL-807
     */
    protected function getAlterTableRenameIndexInSchemaSQL()
    {
        return array(
            "EXEC sp_RENAME N'myschema.mytable.idx_foo', N'idx_bar', N'INDEX'",
        );
    }

    /**
     * @group DBAL-807
     */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL()
    {
        return array(
            "EXEC sp_RENAME N'[schema].[table].[create]', N'[select]', N'INDEX'",
            "EXEC sp_RENAME N'[schema].[table].[foo]', N'[bar]', N'INDEX'",
        );
    }

    protected function getQuotesDropForeignKeySQL()
    {
        return 'ALTER TABLE [table] DROP CONSTRAINT [select]';
    }

    protected function getQuotesDropConstraintSQL()
    {
        return 'ALTER TABLE [table] DROP CONSTRAINT [select]';
    }

    /**
     * @dataProvider getGeneratesIdentifierNamesInDefaultConstraintDeclarationSQL
     * @group DBAL-830
     */
    public function testGeneratesIdentifierNamesInDefaultConstraintDeclarationSQL($table, $column, $expectedSql)
    {
        self::assertSame($expectedSql, $this->_platform->getDefaultConstraintDeclarationSQL($table, $column));
    }

    public function getGeneratesIdentifierNamesInDefaultConstraintDeclarationSQL()
    {
        return array(
            // Unquoted identifiers non-reserved keywords.
            array('mytable', array('name' => 'mycolumn', 'default' => 'foo'), " CONSTRAINT DF_6B2BD609_9BADD926 DEFAULT 'foo' FOR mycolumn"),
            // Quoted identifiers non-reserved keywords.
            array('`mytable`', array('name' => '`mycolumn`', 'default' => 'foo'), " CONSTRAINT DF_6B2BD609_9BADD926 DEFAULT 'foo' FOR [mycolumn]"),
            // Unquoted identifiers reserved keywords.
            array('table', array('name' => 'select', 'default' => 'foo'), " CONSTRAINT DF_F6298F46_4BF2EAC0 DEFAULT 'foo' FOR [select]"),
            // Quoted identifiers reserved keywords.
            array('`table`', array('name' => '`select`', 'default' => 'foo'), " CONSTRAINT DF_F6298F46_4BF2EAC0 DEFAULT 'foo' FOR [select]"),
        );
    }

    /**
     * @dataProvider getGeneratesIdentifierNamesInCreateTableSQL
     * @group DBAL-830
     */
    public function testGeneratesIdentifierNamesInCreateTableSQL($table, $expectedSql)
    {
        self::assertSame($expectedSql, $this->_platform->getCreateTableSQL($table));
    }

    public function getGeneratesIdentifierNamesInCreateTableSQL()
    {
        return array(
            // Unquoted identifiers non-reserved keywords.
            array(
                new Table('mytable', array(new Column('mycolumn', Type::getType('string'), array('default' => 'foo')))),
                array(
                    'CREATE TABLE mytable (mycolumn NVARCHAR(255) NOT NULL)',
                    "ALTER TABLE mytable ADD CONSTRAINT DF_6B2BD609_9BADD926 DEFAULT 'foo' FOR mycolumn"
                )
            ),
            // Quoted identifiers reserved keywords.
            array(
                new Table('`mytable`', array(new Column('`mycolumn`', Type::getType('string'), array('default' => 'foo')))),
                array(
                    'CREATE TABLE [mytable] ([mycolumn] NVARCHAR(255) NOT NULL)',
                    "ALTER TABLE [mytable] ADD CONSTRAINT DF_6B2BD609_9BADD926 DEFAULT 'foo' FOR [mycolumn]"
                )
            ),
            // Unquoted identifiers reserved keywords.
            array(
                new Table('table', array(new Column('select', Type::getType('string'), array('default' => 'foo')))),
                array(
                    'CREATE TABLE [table] ([select] NVARCHAR(255) NOT NULL)',
                    "ALTER TABLE [table] ADD CONSTRAINT DF_F6298F46_4BF2EAC0 DEFAULT 'foo' FOR [select]"
                )
            ),
            // Quoted identifiers reserved keywords.
            array(
                new Table('`table`', array(new Column('`select`', Type::getType('string'), array('default' => 'foo')))),
                array(
                    'CREATE TABLE [table] ([select] NVARCHAR(255) NOT NULL)',
                    "ALTER TABLE [table] ADD CONSTRAINT DF_F6298F46_4BF2EAC0 DEFAULT 'foo' FOR [select]"
                )
            ),
        );
    }

    /**
     * @dataProvider getGeneratesIdentifierNamesInAlterTableSQL
     * @group DBAL-830
     */
    public function testGeneratesIdentifierNamesInAlterTableSQL($tableDiff, $expectedSql)
    {
        self::assertSame($expectedSql, $this->_platform->getAlterTableSQL($tableDiff));
    }

    public function getGeneratesIdentifierNamesInAlterTableSQL()
    {
        return array(
            // Unquoted identifiers non-reserved keywords.
            array(
                new TableDiff(
                    'mytable',
                    array(new Column('addcolumn', Type::getType('string'), array('default' => 'foo'))),
                    array(
                        'mycolumn' => new ColumnDiff(
                            'mycolumn',
                            new Column('mycolumn', Type::getType('string'), array('default' => 'bar')),
                            array('default'),
                            new Column('mycolumn', Type::getType('string'), array('default' => 'foo'))
                        )
                    ),
                    array(new Column('removecolumn', Type::getType('string'), array('default' => 'foo')))
                ),
                array(
                    'ALTER TABLE mytable ADD addcolumn NVARCHAR(255) NOT NULL',
                    "ALTER TABLE mytable ADD CONSTRAINT DF_6B2BD609_4AD86123 DEFAULT 'foo' FOR addcolumn",
                    'ALTER TABLE mytable DROP COLUMN removecolumn',
                    'ALTER TABLE mytable DROP CONSTRAINT DF_6B2BD609_9BADD926',
                    'ALTER TABLE mytable ALTER COLUMN mycolumn NVARCHAR(255) NOT NULL',
                    "ALTER TABLE mytable ADD CONSTRAINT DF_6B2BD609_9BADD926 DEFAULT 'bar' FOR mycolumn"
                )
            ),
            // Quoted identifiers non-reserved keywords.
            array(
                new TableDiff(
                    '`mytable`',
                    array(new Column('`addcolumn`', Type::getType('string'), array('default' => 'foo'))),
                    array(
                        'mycolumn' => new ColumnDiff(
                            '`mycolumn`',
                            new Column('`mycolumn`', Type::getType('string'), array('default' => 'bar')),
                            array('default'),
                            new Column('`mycolumn`', Type::getType('string'), array('default' => 'foo'))
                        )
                    ),
                    array(new Column('`removecolumn`', Type::getType('string'), array('default' => 'foo')))
                ),
                array(
                    'ALTER TABLE [mytable] ADD [addcolumn] NVARCHAR(255) NOT NULL',
                    "ALTER TABLE [mytable] ADD CONSTRAINT DF_6B2BD609_4AD86123 DEFAULT 'foo' FOR [addcolumn]",
                    'ALTER TABLE [mytable] DROP COLUMN [removecolumn]',
                    'ALTER TABLE [mytable] DROP CONSTRAINT DF_6B2BD609_9BADD926',
                    'ALTER TABLE [mytable] ALTER COLUMN [mycolumn] NVARCHAR(255) NOT NULL',
                    "ALTER TABLE [mytable] ADD CONSTRAINT DF_6B2BD609_9BADD926 DEFAULT 'bar' FOR [mycolumn]"
                )
            ),
            // Unquoted identifiers reserved keywords.
            array(
                new TableDiff(
                    'table',
                    array(new Column('add', Type::getType('string'), array('default' => 'foo'))),
                    array(
                        'select' => new ColumnDiff(
                            'select',
                            new Column('select', Type::getType('string'), array('default' => 'bar')),
                            array('default'),
                            new Column('select', Type::getType('string'), array('default' => 'foo'))
                        )
                    ),
                    array(new Column('drop', Type::getType('string'), array('default' => 'foo')))
                ),
                array(
                    'ALTER TABLE [table] ADD [add] NVARCHAR(255) NOT NULL',
                    "ALTER TABLE [table] ADD CONSTRAINT DF_F6298F46_FD1A73E7 DEFAULT 'foo' FOR [add]",
                    'ALTER TABLE [table] DROP COLUMN [drop]',
                    'ALTER TABLE [table] DROP CONSTRAINT DF_F6298F46_4BF2EAC0',
                    'ALTER TABLE [table] ALTER COLUMN [select] NVARCHAR(255) NOT NULL',
                    "ALTER TABLE [table] ADD CONSTRAINT DF_F6298F46_4BF2EAC0 DEFAULT 'bar' FOR [select]"
                )
            ),
            // Quoted identifiers reserved keywords.
            array(
                new TableDiff(
                    '`table`',
                    array(new Column('`add`', Type::getType('string'), array('default' => 'foo'))),
                    array(
                        'select' => new ColumnDiff(
                            '`select`',
                            new Column('`select`', Type::getType('string'), array('default' => 'bar')),
                            array('default'),
                            new Column('`select`', Type::getType('string'), array('default' => 'foo'))
                        )
                    ),
                    array(new Column('`drop`', Type::getType('string'), array('default' => 'foo')))
                ),
                array(
                    'ALTER TABLE [table] ADD [add] NVARCHAR(255) NOT NULL',
                    "ALTER TABLE [table] ADD CONSTRAINT DF_F6298F46_FD1A73E7 DEFAULT 'foo' FOR [add]",
                    'ALTER TABLE [table] DROP COLUMN [drop]',
                    'ALTER TABLE [table] DROP CONSTRAINT DF_F6298F46_4BF2EAC0',
                    'ALTER TABLE [table] ALTER COLUMN [select] NVARCHAR(255) NOT NULL',
                    "ALTER TABLE [table] ADD CONSTRAINT DF_F6298F46_4BF2EAC0 DEFAULT 'bar' FOR [select]"
                )
            ),
        );
    }

    /**
     * @group DBAL-423
     */
    public function testReturnsGuidTypeDeclarationSQL()
    {
        self::assertSame('UNIQUEIDENTIFIER', $this->_platform->getGuidTypeDeclarationSQL(array()));
    }

    /**
     * {@inheritdoc}
     */
    public function getAlterTableRenameColumnSQL()
    {
        return array(
            "sp_RENAME 'foo.bar', 'baz', 'COLUMN'",
            'ALTER TABLE foo DROP CONSTRAINT DF_8C736521_76FF8CAA',
            'ALTER TABLE foo ADD CONSTRAINT DF_8C736521_78240498 DEFAULT 666 FOR baz',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesTableIdentifiersInAlterTableSQL()
    {
        return array(
            'ALTER TABLE [foo] DROP CONSTRAINT fk1',
            'ALTER TABLE [foo] DROP CONSTRAINT fk2',
            "sp_RENAME '[foo].id', 'war', 'COLUMN'",
            'ALTER TABLE [foo] ADD bloo INT NOT NULL',
            'ALTER TABLE [foo] DROP COLUMN baz',
            'ALTER TABLE [foo] ALTER COLUMN bar INT',
            "sp_RENAME '[foo]', 'table'",
            "DECLARE @sql NVARCHAR(MAX) = N''; " .
            "SELECT @sql += N'EXEC sp_rename N''' + dc.name + ''', N''' + REPLACE(dc.name, '8C736521', 'F6298F46') + ''', " .
            "''OBJECT'';' FROM sys.default_constraints dc JOIN sys.tables tbl ON dc.parent_object_id = tbl.object_id " .
            "WHERE tbl.name = 'table';EXEC sp_executesql @sql",
            'ALTER TABLE [table] ADD CONSTRAINT fk_add FOREIGN KEY (fk3) REFERENCES fk_table (id)',
            'ALTER TABLE [table] ADD CONSTRAINT fk2 FOREIGN KEY (fk2) REFERENCES fk_table2 (id)',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommentOnColumnSQL()
    {
        return array(
            "COMMENT ON COLUMN foo.bar IS 'comment'",
            "COMMENT ON COLUMN [Foo].[BAR] IS 'comment'",
            "COMMENT ON COLUMN [select].[from] IS 'comment'",
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnsForeignKeyReferentialActionSQL()
    {
        return array(
            array('CASCADE', 'CASCADE'),
            array('SET NULL', 'SET NULL'),
            array('NO ACTION', 'NO ACTION'),
            array('RESTRICT', 'NO ACTION'),
            array('SET DEFAULT', 'SET DEFAULT'),
            array('CaScAdE', 'CASCADE'),
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL()
    {
        return 'CONSTRAINT [select] UNIQUE (foo) WHERE foo IS NOT NULL';
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInIndexDeclarationSQL()
    {
        return 'INDEX [select] (foo)';
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInTruncateTableSQL()
    {
        return 'TRUNCATE TABLE [select]';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAlterStringToFixedStringSQL()
    {
        return array(
            'ALTER TABLE mytable ALTER COLUMN name NCHAR(2) NOT NULL',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL()
    {
        return array(
            "EXEC sp_RENAME N'mytable.idx_foo', N'idx_foo_renamed', N'INDEX'",
        );
    }

    public function testModifyLimitQueryWithTopNSubQueryWithOrderBy()
    {
        $querySql = 'SELECT * FROM test t WHERE t.id = (SELECT TOP 1 t2.id FROM test t2 ORDER BY t2.data DESC)';
        $alteredSql = 'SELECT TOP 10 * FROM test t WHERE t.id = (SELECT TOP 1 t2.id FROM test t2 ORDER BY t2.data DESC)';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10);
        self::assertEquals(sprintf(static::$selectFromCtePattern, $alteredSql, 1, 10), $sql);

        $querySql = 'SELECT * FROM test t WHERE t.id = (SELECT TOP 1 t2.id FROM test t2 ORDER BY t2.data DESC) ORDER BY t.data2 DESC';
        $alteredSql = 'SELECT TOP 10 * FROM test t WHERE t.id = (SELECT TOP 1 t2.id FROM test t2 ORDER BY t2.data DESC) ORDER BY t.data2 DESC';
        $sql = $this->_platform->modifyLimitQuery($querySql, 10);
        self::assertEquals(sprintf(static::$selectFromCtePattern, $alteredSql, 1, 10), $sql);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableColumnsSQL()
    {
        self::assertContains("'Foo''Bar\\'", $this->_platform->getListTableColumnsSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesSchemaNameInListTableColumnsSQL()
    {
        self::assertContains(
            "'Foo''Bar\\'",
            $this->_platform->getListTableColumnsSQL("Foo'Bar\\.baz_table"),
            '',
            true
        );
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableForeignKeysSQL()
    {
        self::assertContains("'Foo''Bar\\'", $this->_platform->getListTableForeignKeysSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesSchemaNameInListTableForeignKeysSQL()
    {
        self::assertContains(
            "'Foo''Bar\\'",
            $this->_platform->getListTableForeignKeysSQL("Foo'Bar\\.baz_table"),
            '',
            true
        );
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableIndexesSQL()
    {
        self::assertContains("'Foo''Bar\\'", $this->_platform->getListTableIndexesSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesSchemaNameInListTableIndexesSQL()
    {
        self::assertContains(
            "'Foo''Bar\\'",
            $this->_platform->getListTableIndexesSQL("Foo'Bar\\.baz_table"),
            '',
            true
        );
    }

    /**
     * @group 2859
     */
    public function testGetDefaultValueDeclarationSQLForDateType() : void
    {
        $currentDateSql = $this->_platform->getCurrentDateSQL();
        foreach (['date', 'date_immutable'] as $type) {
            $field = [
                'type'    => Type::getType($type),
                'default' => $currentDateSql,
            ];

            self::assertSame(
                " DEFAULT '" . $currentDateSql . "'",
                $this->_platform->getDefaultValueDeclarationSQL($field)
            );
        }
    }
}
