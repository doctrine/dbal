<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\InvalidColumnDeclaration;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServer;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

/** @extends AbstractPlatformTestCase<SQLServerPlatform> */
class SQLServerPlatformTest extends AbstractPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new SQLServerPlatform();
    }

    protected function createComparator(): Comparator
    {
        return new SQLServer\Comparator($this->platform, '');
    }

    public function getGenerateTableSql(): string
    {
        return 'CREATE TABLE test (id INT IDENTITY NOT NULL, test NVARCHAR(255), PRIMARY KEY (id))';
    }

    /**
     * {@inheritDoc}
     */
    public function getGenerateTableWithMultiColumnUniqueIndexSql(): array
    {
        return [
            'CREATE TABLE test (foo NVARCHAR(255), bar NVARCHAR(255))',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)'
                . ' WHERE foo IS NOT NULL AND bar IS NOT NULL',
        ];
    }

    public function testDoesNotSupportRegexp(): void
    {
        $this->expectException(Exception::class);

        $this->platform->getRegexpExpression();
    }

    public function testGeneratesSqlSnippets(): void
    {
        self::assertEquals('CONVERT(date, GETDATE())', $this->platform->getCurrentDateSQL());
        self::assertEquals('CONVERT(time, GETDATE())', $this->platform->getCurrentTimeSQL());
        self::assertEquals('CURRENT_TIMESTAMP', $this->platform->getCurrentTimestampSQL());
        self::assertEquals(
            'CONCAT(column1, column2, column3)',
            $this->platform->getConcatExpression('column1', 'column2', 'column3'),
        );
    }

    public function testGeneratesTransactionsCommands(): void
    {
        self::assertEquals(
            'SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_UNCOMMITTED),
        );
        self::assertEquals(
            'SET TRANSACTION ISOLATION LEVEL READ COMMITTED',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_COMMITTED),
        );
        self::assertEquals(
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::REPEATABLE_READ),
        );
        self::assertEquals(
            'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::SERIALIZABLE),
        );
    }

    public function testGeneratesDDLSnippets(): void
    {
        $dropDatabaseExpectation = 'DROP DATABASE foobar';

        self::assertEquals('SELECT * FROM sys.databases', $this->platform->getListDatabasesSQL());
        self::assertEquals('CREATE DATABASE foobar', $this->platform->getCreateDatabaseSQL('foobar'));
        self::assertEquals($dropDatabaseExpectation, $this->platform->getDropDatabaseSQL('foobar'));
        self::assertEquals('DROP TABLE foobar', $this->platform->getDropTableSQL('foobar'));
    }

    public function testGeneratesTypeDeclarationForIntegers(): void
    {
        self::assertEquals(
            'INT',
            $this->platform->getIntegerTypeDeclarationSQL([]),
        );
        self::assertEquals(
            'INT IDENTITY',
            $this->platform->getIntegerTypeDeclarationSQL(['autoincrement' => true]),
        );
        self::assertEquals(
            'INT IDENTITY',
            $this->platform->getIntegerTypeDeclarationSQL(
                ['autoincrement' => true, 'primary' => true],
            ),
        );
    }

    public function testGeneratesTypeDeclarationsForStrings(): void
    {
        self::assertSame('VARCHAR(MAX)', $this->platform->getClobTypeDeclarationSQL([]));
        self::assertSame(
            'VARCHAR(MAX)',
            $this->platform->getClobTypeDeclarationSQL(['length' => 5, 'fixed' => true]),
        );
    }

    public function testSupportsIdentityColumns(): void
    {
        self::assertTrue($this->platform->supportsIdentityColumns());
    }

    public function testSupportsSchemas(): void
    {
        self::assertTrue($this->platform->supportsSchemas());
    }

    public function testDoesNotSupportSavePoints(): void
    {
        self::assertTrue($this->platform->supportsSavepoints());
    }

    public function getGenerateIndexSql(): string
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateUniqueIndexSql(): string
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2) WHERE test IS NOT NULL AND test2 IS NOT NULL';
    }

    protected function getGenerateForeignKeySql(): string
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
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
            10,
        );

        self::assertEquals(
            'SELECT * FROM user ORDER BY username DESC, usereamil ASC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql,
        );
    }

    public function testModifyLimitQueryWithSubSelect(): void
    {
        $sql = $this->platform->modifyLimitQuery(
            'SELECT * FROM (SELECT u.id as uid, u.name as uname) dctrn_result',
            10,
        );

        self::assertEquals(
            'SELECT * FROM ('
            . 'SELECT u.id as uid, u.name as uname'
            . ') dctrn_result ORDER BY (SELECT 0) OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql,
        );
    }

    public function testModifyLimitQueryWithSubSelectAndOrder(): void
    {
        $sql = $this->platform->modifyLimitQuery(
            'SELECT * FROM ('
                . 'SELECT u.id as uid, u.name as uname'
                . ') dctrn_result ORDER BY uname DESC',
            10,
        );

        self::assertEquals(
            'SELECT * FROM ('
                . 'SELECT u.id as uid, u.name as uname'
                . ') dctrn_result ORDER BY uname DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql,
        );

        $sql = $this->platform->modifyLimitQuery(
            'SELECT * FROM (SELECT u.id, u.name) dctrn_result ORDER BY name DESC',
            10,
        );

        self::assertEquals(
            'SELECT * FROM ('
                . 'SELECT u.id, u.name'
                . ') dctrn_result ORDER BY name DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql,
        );
    }

    public function testModifyLimitQueryWithSubSelectAndMultipleOrder(): void
    {
        $sql = $this->platform->modifyLimitQuery(
            'SELECT * FROM (SELECT u.id as uid, u.name as uname) dctrn_result ORDER BY uname DESC, uid ASC',
            10,
            5,
        );

        self::assertEquals(
            'SELECT * FROM ('
                . 'SELECT u.id as uid, u.name as uname'
                . ') dctrn_result ORDER BY uname DESC, uid ASC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql,
        );

        $sql = $this->platform->modifyLimitQuery(
            'SELECT * FROM (SELECT u.id uid, u.name uname) dctrn_result ORDER BY uname DESC, uid ASC',
            10,
            5,
        );

        self::assertEquals(
            'SELECT * FROM ('
                . 'SELECT u.id uid, u.name uname'
                . ') dctrn_result ORDER BY uname DESC, uid ASC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql,
        );

        $sql = $this->platform->modifyLimitQuery(
            'SELECT * FROM (SELECT u.id, u.name) dctrn_result ORDER BY name DESC, id ASC',
            10,
            5,
        );

        self::assertEquals(
            'SELECT * FROM ('
                . 'SELECT u.id, u.name'
                . ') dctrn_result ORDER BY name DESC, id ASC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql,
        );
    }

    public function testModifyLimitQueryWithFromColumnNames(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT a.fromFoo, fromBar FROM foo', 10);

        self::assertEquals(
            'SELECT a.fromFoo, fromBar FROM foo ORDER BY (SELECT 0) OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
            $sql,
        );
    }

    public function testModifyLimitQueryWithExtraLongQuery(): void
    {
        $query = 'SELECT table1.column1, table2.column2, table3.column3, table4.column4, '
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
            . ' AND (table7.column7 = table8.column8)';

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
            $this->platform->modifyLimitQuery($query, 10),
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
            . ' WHERE u3_.ID = ? ORDER BY m0_.FECHAINICIO DESC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY';

        $actual = $this->platform->modifyLimitQuery($sql, 10, 5);

        self::assertEquals($expected, $actual);
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
            10,
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
            $sql,
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
            5,
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
            $sql,
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
            0,
        );

        self::assertEquals(
            'SELECT ' .
            'MAX(heading_id) aliased, ' .
            'code ' .
            'FROM operator_model_operator ' .
            'GROUP BY code ' .
            'ORDER BY MAX(heading_id) DESC ' .
            'OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY',
            $sql,
        );
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
            $this->platform->modifyLimitQuery($query, 20),
        );
    }

    public function testQuoteIdentifier(): void
    {
        self::assertEquals('[test].[test]', $this->platform->quoteIdentifier('test.test'));
    }

    public function testCreateClusteredIndex(): void
    {
        $idx = new Index('idx', ['id']);
        $idx->addFlag('clustered');
        self::assertEquals('CREATE CLUSTERED INDEX idx ON tbl (id)', $this->platform->getCreateIndexSQL($idx, 'tbl'));
    }

    public function testCreateNonClusteredPrimaryKeyInTable(): void
    {
        $table = new Table('tbl');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);
        $table->getIndex('primary')->addFlag('nonclustered');

        self::assertEquals(
            ['CREATE TABLE tbl (id INT NOT NULL, PRIMARY KEY NONCLUSTERED (id))'],
            $this->platform->getCreateTableSQL($table),
        );
    }

    public function testCreateNonClusteredPrimaryKey(): void
    {
        $idx = new Index('idx', ['id'], false, true);
        $idx->addFlag('nonclustered');
        self::assertEquals(
            'ALTER TABLE tbl ADD PRIMARY KEY NONCLUSTERED (id)',
            $this->platform->getCreatePrimaryKeySQL($idx, 'tbl'),
        );
    }

    public function testAlterAddPrimaryKey(): void
    {
        $idx = new Index('idx', ['id'], false, true);
        self::assertEquals('ALTER TABLE tbl ADD PRIMARY KEY (id)', $this->platform->getCreateIndexSQL($idx, 'tbl'));
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedColumnInPrimaryKeySQL(): array
    {
        return ['CREATE TABLE [quoted] ([create] NVARCHAR(255) NOT NULL, PRIMARY KEY ([create]))'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedColumnInIndexSQL(): array
    {
        return [
            'CREATE TABLE [quoted] ([create] NVARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB ON [quoted] ([create])',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedNameInIndexSQL(): array
    {
        return [
            'CREATE TABLE test (column1 NVARCHAR(255) NOT NULL)',
            'CREATE INDEX [key] ON test (column1)',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedColumnInForeignKeySQL(): array
    {
        return [
            'CREATE TABLE [quoted] ([create] NVARCHAR(255) NOT NULL, '
                . 'foo NVARCHAR(255) NOT NULL, [bar] NVARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB8C736521D79164E3 ON [quoted] ([create], foo, [bar])',
            'ALTER TABLE [quoted] ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD'
                . ' FOREIGN KEY ([create], foo, [bar]) REFERENCES [foreign] ([create], bar, [foo-bar])',
            'ALTER TABLE [quoted] ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD'
                . ' FOREIGN KEY ([create], foo, [bar]) REFERENCES foo ([create], bar, [foo-bar])',
            'ALTER TABLE [quoted] ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION'
                . ' FOREIGN KEY ([create], foo, [bar]) REFERENCES [foo-bar] ([create], bar, [foo-bar])',
        ];
    }

    public function testGetCreateSchemaSQL(): void
    {
        $schemaName = 'schema';
        $sql        = $this->platform->getCreateSchemaSQL($schemaName);
        self::assertEquals('CREATE SCHEMA ' . $schemaName, $sql);
    }

    public function testCreateTableWithSchemaColumnComments(): void
    {
        $table = new Table('testschema.test');
        $table->addColumn('id', Types::INTEGER, ['comment' => 'This is a comment']);
        $table->setPrimaryKey(['id']);

        $expectedSql = [
            'CREATE TABLE testschema.test (id INT NOT NULL, PRIMARY KEY (id))',
            "EXEC sp_addextendedproperty N'MS_Description', N'This is a comment', "
                . "N'SCHEMA', 'testschema', N'TABLE', 'test', N'COLUMN', id",
        ];

        self::assertEquals($expectedSql, $this->platform->getCreateTableSQL($table));
    }

    public function testAlterTableWithSchemaColumnComments(): void
    {
        $table = new Table('testschema.mytable');

        $tableDiff = new TableDiff($table, [
            new Column(
                'quota',
                Type::getType(Types::INTEGER),
                ['comment' => 'A comment'],
            ),
        ], [], [], [], [], [], [], [], [], [], []);

        $expectedSql = [
            'ALTER TABLE testschema.mytable ADD quota INT NOT NULL',
            "EXEC sp_addextendedproperty N'MS_Description', N'A comment', "
                . "N'SCHEMA', 'testschema', N'TABLE', 'mytable', N'COLUMN', quota",
        ];

        self::assertEquals($expectedSql, $this->platform->getAlterTableSQL($tableDiff));
    }

    public function testAlterTableWithSchemaDropColumnComments(): void
    {
        $table = new Table('testschema.mytable');

        $tableDiff = new TableDiff($table, [], [new ColumnDiff(
            new Column('quota', Type::getType(Types::INTEGER), ['comment' => 'A comment']),
            new Column('quota', Type::getType(Types::INTEGER), []),
        ),
        ], [], [], [], [], [], [], [], [], []);

        $expectedSql = [
            "EXEC sp_dropextendedproperty N'MS_Description'"
                . ", N'SCHEMA', 'testschema', N'TABLE', 'mytable', N'COLUMN', quota",
        ];

        self::assertEquals($expectedSql, $this->platform->getAlterTableSQL($tableDiff));
    }

    public function testAlterTableWithSchemaUpdateColumnComments(): void
    {
        $table = new Table('testschema.mytable');

        $tableDiff = new TableDiff($table, [], [new ColumnDiff(
            new Column('quota', Type::getType(Types::INTEGER), ['comment' => 'A comment']),
            new Column('quota', Type::getType(Types::INTEGER), ['comment' => 'B comment']),
        ),
        ], [], [], [], [], [], [], [], [], []);

        $expectedSql = ["EXEC sp_updateextendedproperty N'MS_Description', N'B comment', "
                . "N'SCHEMA', 'testschema', N'TABLE', 'mytable', N'COLUMN', quota",
        ];

        self::assertEquals($expectedSql, $this->platform->getAlterTableSQL($tableDiff));
    }

    public function testInitializesDoctrineTypeMappings(): void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('bigint'));
        self::assertSame(Types::BIGINT, $this->platform->getDoctrineTypeMapping('bigint'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('numeric'));
        self::assertSame(Types::DECIMAL, $this->platform->getDoctrineTypeMapping('numeric'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('bit'));
        self::assertSame(Types::BOOLEAN, $this->platform->getDoctrineTypeMapping('bit'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('smallint'));
        self::assertSame(Types::SMALLINT, $this->platform->getDoctrineTypeMapping('smallint'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('decimal'));
        self::assertSame(Types::DECIMAL, $this->platform->getDoctrineTypeMapping('decimal'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('smallmoney'));
        self::assertSame(Types::INTEGER, $this->platform->getDoctrineTypeMapping('smallmoney'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('int'));
        self::assertSame(Types::INTEGER, $this->platform->getDoctrineTypeMapping('int'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('tinyint'));
        self::assertSame(Types::SMALLINT, $this->platform->getDoctrineTypeMapping('tinyint'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('money'));
        self::assertSame(Types::INTEGER, $this->platform->getDoctrineTypeMapping('money'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('float'));
        self::assertSame(Types::FLOAT, $this->platform->getDoctrineTypeMapping('float'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('real'));
        self::assertSame(Types::FLOAT, $this->platform->getDoctrineTypeMapping('real'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('double'));
        self::assertSame(Types::FLOAT, $this->platform->getDoctrineTypeMapping('double'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('double precision'));
        self::assertSame(Types::FLOAT, $this->platform->getDoctrineTypeMapping('double precision'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('smalldatetime'));
        self::assertSame(Types::DATETIME_MUTABLE, $this->platform->getDoctrineTypeMapping('smalldatetime'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('datetime'));
        self::assertSame(Types::DATETIME_MUTABLE, $this->platform->getDoctrineTypeMapping('datetime'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('char'));
        self::assertSame(Types::STRING, $this->platform->getDoctrineTypeMapping('char'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('varchar'));
        self::assertSame(Types::STRING, $this->platform->getDoctrineTypeMapping('varchar'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('text'));
        self::assertSame(Types::TEXT, $this->platform->getDoctrineTypeMapping('text'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('nchar'));
        self::assertSame(Types::STRING, $this->platform->getDoctrineTypeMapping('nchar'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('nvarchar'));
        self::assertSame(Types::STRING, $this->platform->getDoctrineTypeMapping('nvarchar'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('ntext'));
        self::assertSame(Types::TEXT, $this->platform->getDoctrineTypeMapping('ntext'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('binary'));
        self::assertSame(Types::BINARY, $this->platform->getDoctrineTypeMapping('binary'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('varbinary'));
        self::assertSame(Types::BINARY, $this->platform->getDoctrineTypeMapping('varbinary'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('image'));
        self::assertSame(Types::BLOB, $this->platform->getDoctrineTypeMapping('image'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('uniqueidentifier'));
        self::assertSame(Types::GUID, $this->platform->getDoctrineTypeMapping('uniqueidentifier'));
    }

    protected function getExpectedFixedLengthStringTypeDeclarationSQLNoLength(): string
    {
        return 'NCHAR';
    }

    protected function getExpectedFixedLengthStringTypeDeclarationSQLWithLength(): string
    {
        return 'NCHAR(16)';
    }

    public function testGetVariableLengthStringTypeDeclarationSQLNoLength(): void
    {
        $this->expectException(InvalidColumnDeclaration::class);

        parent::testGetVariableLengthStringTypeDeclarationSQLNoLength();
    }

    protected function getExpectedVariableLengthStringTypeDeclarationSQLWithLength(): string
    {
        return 'NVARCHAR(16)';
    }

    public function testGetVariableLengthBinaryTypeDeclarationSQLNoLength(): void
    {
        $this->expectException(InvalidColumnDeclaration::class);

        parent::testGetVariableLengthBinaryTypeDeclarationSQLNoLength();
    }

    /**
     * {@inheritDoc}
     */
    protected function getAlterTableRenameIndexSQL(): array
    {
        return ["EXEC sp_rename N'mytable.idx_foo', N'idx_bar', N'INDEX'"];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedAlterTableRenameIndexSQL(): array
    {
        return [
            "EXEC sp_rename N'[table].[create]', N'[select]', N'INDEX'",
            "EXEC sp_rename N'[table].[foo]', N'[bar]', N'INDEX'",
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getAlterTableRenameIndexInSchemaSQL(): array
    {
        return ["EXEC sp_rename N'myschema.mytable.idx_foo', N'idx_bar', N'INDEX'"];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL(): array
    {
        return [
            "EXEC sp_rename N'[schema].[table].[create]', N'[select]', N'INDEX'",
            "EXEC sp_rename N'[schema].[table].[foo]', N'[bar]', N'INDEX'",
        ];
    }

    public function testReturnsGuidTypeDeclarationSQL(): void
    {
        self::assertSame('UNIQUEIDENTIFIER', $this->platform->getGuidTypeDeclarationSQL([]));
    }

    /**
     * {@inheritDoc}
     */
    protected function getCommentOnColumnSQL(): array
    {
        return [
            "COMMENT ON COLUMN foo.bar IS 'comment'",
            "COMMENT ON COLUMN [Foo].[BAR] IS 'comment'",
            "COMMENT ON COLUMN [select].[from] IS 'comment'",
        ];
    }

    /**
     * {@inheritDoc}
     */
    public static function getReturnsForeignKeyReferentialActionSQL(): iterable
    {
        return [
            ['CASCADE', 'CASCADE'],
            ['SET NULL', 'SET NULL'],
            ['NO ACTION', 'NO ACTION'],
            ['RESTRICT', 'NO ACTION'],
            ['SET DEFAULT', 'SET DEFAULT'],
            ['CaScAdE', 'CASCADE'],
        ];
    }

    protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL(): string
    {
        return 'CONSTRAINT [select] UNIQUE (foo)';
    }

    protected function getQuotesReservedKeywordInIndexDeclarationSQL(): string
    {
        return 'INDEX [select] (foo)';
    }

    protected function getQuotesReservedKeywordInTruncateTableSQL(): string
    {
        return 'TRUNCATE TABLE [select]';
    }

    /**
     * {@inheritDoc}
     */
    protected function getAlterStringToFixedStringSQL(): array
    {
        return ['ALTER TABLE mytable ALTER COLUMN name NCHAR(2) NOT NULL'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(): array
    {
        return ["EXEC sp_rename N'mytable.idx_foo', N'idx_foo_renamed', N'INDEX'"];
    }

    protected function getLimitOffsetCastToIntExpectedQuery(): string
    {
        return 'SELECT * FROM user ORDER BY (SELECT 0) OFFSET 2 ROWS FETCH NEXT 1 ROWS ONLY';
    }

    public function testModifyLimitQueryWithTopNSubQueryWithOrderBy(): void
    {
        $query = 'SELECT * FROM test t WHERE t.id = (SELECT TOP 1 t2.id FROM test t2 ORDER BY t2.data DESC)';

        $expected = 'SELECT * FROM test t WHERE t.id = ('
            . 'SELECT TOP 1 t2.id FROM test t2 ORDER BY t2.data DESC'
            . ') ORDER BY (SELECT 0) OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY';

        self::assertEquals(
            $expected,
            $this->platform->modifyLimitQuery($query, 10),
        );

        $query = 'SELECT * FROM test t WHERE t.id = ('
            . 'SELECT TOP 1 t2.id FROM test t2 ORDER BY t2.data DESC'
            . ') ORDER BY t.data2 DESC';

        $expected = 'SELECT * FROM test t WHERE t.id = ('
            . 'SELECT TOP 1 t2.id FROM test t2 ORDER BY t2.data DESC'
            . ') ORDER BY t.data2 DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY';

        self::assertEquals(
            $expected,
            $this->platform->modifyLimitQuery($query, 10),
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
            $this->platform->modifyLimitQuery($query, 10),
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
            $this->platform->modifyLimitQuery($query, 10),
        );
    }

    public function testModifyLimitQueryWithComplexOrderByExpression(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM table ORDER BY (table.x * table.y) DESC', 10);

        $expected = 'SELECT * FROM table ORDER BY (table.x * table.y) DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY';

        self::assertEquals($sql, $expected);
    }

    public function testModifyLimitQueryWithNewlineBeforeOrderBy(): void
    {
        $querySql    = "SELECT * FROM test\nORDER BY col DESC";
        $expectedSql = "SELECT * FROM test\nORDER BY col DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY";
        $sql         = $this->platform->modifyLimitQuery($querySql, 10);
        self::assertEquals($expectedSql, $sql);
    }

    public function testGetDefaultValueDeclarationSQLForDateType(): void
    {
        $currentDateSql = $this->platform->getCurrentDateSQL();
        foreach ([Types::DATE_MUTABLE, Types::DATE_IMMUTABLE] as $type) {
            self::assertSame(
                ' DEFAULT CONVERT(date, GETDATE())',
                $this->platform->getDefaultValueDeclarationSQL([
                    'type' => Type::getType($type),
                    'default' => $currentDateSql,
                ]),
            );
        }
    }

    public function testSupportsColumnCollation(): void
    {
        self::assertTrue($this->platform->supportsColumnCollation());
    }

    public function testColumnCollationDeclarationSQL(): void
    {
        self::assertSame(
            'COLLATE Latin1_General_CS_AS_KS_WS',
            $this->platform->getColumnCollationDeclarationSQL('Latin1_General_CS_AS_KS_WS'),
        );
    }

    public function testGetCreateTableSQLWithColumnCollation(): void
    {
        $table = new Table('foo');
        $table->addColumn('no_collation', Types::STRING, ['length' => 255]);
        $table->addColumn('column_collation', Types::STRING, ['length' => 255])
            ->setPlatformOption('collation', 'Latin1_General_CS_AS_KS_WS');

        self::assertSame(
            ['CREATE TABLE foo (no_collation NVARCHAR(255) NOT NULL, '
                    . 'column_collation NVARCHAR(255) COLLATE Latin1_General_CS_AS_KS_WS NOT NULL)',
            ],
            $this->platform->getCreateTableSQL($table),
        );
    }

    public function testSupportsSequences(): void
    {
        self::assertTrue($this->platform->supportsSequences());
    }

    public function testGeneratesSequenceSqlCommands(): void
    {
        $sequence = new Sequence('myseq', 20, 1);
        self::assertEquals(
            'CREATE SEQUENCE myseq START WITH 1 INCREMENT BY 20 MINVALUE 1',
            $this->platform->getCreateSequenceSQL($sequence),
        );
        self::assertEquals(
            'ALTER SEQUENCE myseq INCREMENT BY 20',
            $this->platform->getAlterSequenceSQL($sequence),
        );
        self::assertEquals(
            'DROP SEQUENCE myseq',
            $this->platform->getDropSequenceSQL('myseq'),
        );
        self::assertEquals(
            'SELECT NEXT VALUE FOR myseq',
            $this->platform->getSequenceNextValSQL('myseq'),
        );
    }

    public function testAlterTableWithSchemaSameColumnComments(): void
    {
        $table = new Table('testschema.mytable');

        $tableDiff = new TableDiff($table, [], [
            new ColumnDiff(
                new Column('quota', Type::getType(Types::INTEGER), ['comment' => 'A comment', 'notnull' => false]),
                new Column('quota', Type::getType(Types::INTEGER), ['comment' => 'A comment', 'notnull' => true]),
            ),
        ], [], [], [], [], [], [], [], [], []);

        $expectedSql = ['ALTER TABLE testschema.mytable ALTER COLUMN quota INT NOT NULL'];

        self::assertEquals($expectedSql, $this->platform->getAlterTableSQL($tableDiff));
    }

    #[DataProvider('getLockHints')]
    public function testAppendsLockHint(LockMode $lockMode, string $lockHint): void
    {
        $fromClause     = 'FROM users';
        $expectedResult = $fromClause . $lockHint;

        self::assertSame($expectedResult, $this->platform->appendLockHint($fromClause, $lockMode));
    }

    /** @return iterable<int, array{LockMode, string}> */
    public static function getLockHints(): iterable
    {
        return [
            [LockMode::NONE, ''],
            [LockMode::OPTIMISTIC, ''],
            [LockMode::PESSIMISTIC_READ, ' WITH (HOLDLOCK, ROWLOCK)'],
            [LockMode::PESSIMISTIC_WRITE, ' WITH (UPDLOCK, ROWLOCK)'],
        ];
    }

    public function testGeneratesTypeDeclarationForDateTimeTz(): void
    {
        self::assertEquals('DATETIMEOFFSET(6)', $this->platform->getDateTimeTzTypeDeclarationSQL([]));
    }
}
