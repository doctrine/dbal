<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLAnywherePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;

class SQLAnywherePlatformTest extends AbstractPlatformTestCase
{
    /**
     * @var \Doctrine\DBAL\Platforms\SQLAnywherePlatform
     */
    protected $_platform;

    public function createPlatform()
    {
        return new SQLAnywherePlatform;
    }

    public function getGenerateAlterTableSql()
    {
        return array(
            "ALTER TABLE mytable ADD quota INT DEFAULT NULL, DROP foo, ALTER baz VARCHAR(1) DEFAULT 'def' NOT NULL, ALTER bloo BIT DEFAULT '0' NOT NULL",
            'ALTER TABLE mytable RENAME userlist'
        );
    }

    public function getGenerateForeignKeySql()
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
    }

    public function getGenerateIndexSql()
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id INT IDENTITY NOT NULL, test VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id))';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return array(
            'CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL)',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)'
        );
    }

    public function getGenerateUniqueIndexSql()
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    protected function getQuotedColumnInForeignKeySQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, "bar" VARCHAR(255) NOT NULL, CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES "foreign" ("create", bar, "foo-bar"), CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES foo ("create", bar, "foo-bar"), CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar") REFERENCES "foo-bar" ("create", bar, "foo-bar"))',
        );
    }

    protected function getQuotedColumnInIndexSQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB ON "quoted" ("create")'
        );
    }

    protected function getQuotedNameInIndexSQL()
    {
        return array(
            'CREATE TABLE test (column1 VARCHAR(255) NOT NULL)',
            'CREATE INDEX "key" ON test (column1)',
        );
    }

    protected function getQuotedColumnInPrimaryKeySQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, PRIMARY KEY ("create"))'
        );
    }

    public function getCreateTableColumnCommentsSQL()
    {
        return array(
            "CREATE TABLE test (id INT NOT NULL, PRIMARY KEY (id))",
            "COMMENT ON COLUMN test.id IS 'This is a comment'",
        );
    }

    public function getAlterTableColumnCommentsSQL()
    {
        return array(
            "ALTER TABLE mytable ADD quota INT NOT NULL",
            "COMMENT ON COLUMN mytable.quota IS 'A comment'",
            "COMMENT ON COLUMN mytable.foo IS NULL",
            "COMMENT ON COLUMN mytable.baz IS 'B comment'",
        );
    }

    public function getCreateTableColumnTypeCommentsSQL()
    {
        return array(
            "CREATE TABLE test (id INT NOT NULL, data TEXT NOT NULL, PRIMARY KEY (id))",
            "COMMENT ON COLUMN test.data IS '(DC2Type:array)'"
        );
    }

    public function testHasCorrectPlatformName()
    {
        $this->assertEquals('sqlanywhere', $this->_platform->getName());
    }

    public function testGeneratesCreateTableSQLWithCommonIndexes()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('name', 'string', array('length' => 50));
        $table->setPrimaryKey(array('id'));
        $table->addIndex(array('name'));
        $table->addIndex(array('id', 'name'), 'composite_idx');

        $this->assertEquals(
            array(
                'CREATE TABLE test (id INT NOT NULL, name VARCHAR(50) NOT NULL, PRIMARY KEY (id))',
                'CREATE INDEX IDX_D87F7E0C5E237E06 ON test (name)',
                'CREATE INDEX composite_idx ON test (id, name)'
            ),
            $this->_platform->getCreateTableSQL($table)
        );
    }

    public function testGeneratesCreateTableSQLWithForeignKeyConstraints()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('fk_1', 'integer');
        $table->addColumn('fk_2', 'integer');
        $table->setPrimaryKey(array('id'));
        $table->addForeignKeyConstraint('foreign_table', array('fk_1', 'fk_2'), array('pk_1', 'pk_2'));
        $table->addForeignKeyConstraint(
            'foreign_table2',
            array('fk_1', 'fk_2'),
            array('pk_1', 'pk_2'),
            array(),
            'named_fk'
        );

        $this->assertEquals(
            array(
                'CREATE TABLE test (id INT NOT NULL, fk_1 INT NOT NULL, fk_2 INT NOT NULL, ' .
                'CONSTRAINT FK_D87F7E0C177612A38E7F4319 FOREIGN KEY (fk_1, fk_2) REFERENCES foreign_table (pk_1, pk_2), ' .
                'CONSTRAINT named_fk FOREIGN KEY (fk_1, fk_2) REFERENCES foreign_table2 (pk_1, pk_2))'
            ),
            $this->_platform->getCreateTableSQL($table, AbstractPlatform::CREATE_FOREIGNKEYS)
        );
    }

    public function testGeneratesCreateTableSQLWithCheckConstraints()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('check_max', 'integer', array('platformOptions' => array('max' => 10)));
        $table->addColumn('check_min', 'integer', array('platformOptions' => array('min' => 10)));
        $table->setPrimaryKey(array('id'));

        $this->assertEquals(
            array(
                'CREATE TABLE test (id INT NOT NULL, check_max INT NOT NULL, check_min INT NOT NULL, PRIMARY KEY (id), CHECK (check_max <= 10), CHECK (check_min >= 10))'
            ),
            $this->_platform->getCreateTableSQL($table)
        );
    }

    public function testGeneratesTableAlterationWithRemovedColumnCommentSql()
    {
        $table = new Table('mytable');
        $table->addColumn('foo', 'string', array('comment' => 'foo comment'));

        $tableDiff = new TableDiff('mytable');
        $tableDiff->fromTable = $table;
        $tableDiff->changedColumns['foo'] = new ColumnDiff(
            'foo',
            new Column('foo', Type::getType('string')),
            array('comment')
        );

        $this->assertEquals(
            array(
                "COMMENT ON COLUMN mytable.foo IS NULL"
            ),
            $this->_platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @dataProvider getLockHints
     */
    public function testAppendsLockHint($lockMode, $lockHint)
    {
        $fromClause = 'FROM users';
        $expectedResult = $fromClause . $lockHint;

        $this->assertSame($expectedResult, $this->_platform->appendLockHint($fromClause, $lockMode));
    }

    public function getLockHints()
    {
        return array(
            array(null, ''),
            array(false, ''),
            array(true, ''),
            array(LockMode::NONE, ' WITH (NOLOCK)'),
            array(LockMode::OPTIMISTIC, ''),
            array(LockMode::PESSIMISTIC_READ, ' WITH (UPDLOCK)'),
            array(LockMode::PESSIMISTIC_WRITE, ' WITH (XLOCK)'),
        );
    }

    public function testHasCorrectMaxIdentifierLength()
    {
        $this->assertEquals(128, $this->_platform->getMaxIdentifierLength());
    }

    public function testFixesSchemaElementNames()
    {
        $maxIdentifierLength = $this->_platform->getMaxIdentifierLength();
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $schemaElementName = '';

        for ($i = 0; $i < $maxIdentifierLength + 100; $i++) {
            $schemaElementName .= $characters[mt_rand(0, strlen($characters) - 1)];
        }

        $fixedSchemaElementName = substr($schemaElementName, 0, $maxIdentifierLength);

        $this->assertEquals(
            $fixedSchemaElementName,
            $this->_platform->fixSchemaElementName($schemaElementName)
        );
        $this->assertEquals(
            $fixedSchemaElementName,
            $this->_platform->fixSchemaElementName($fixedSchemaElementName)
        );
    }

    public function testGeneratesColumnTypesDeclarationSQL()
    {
        $fullColumnDef = array(
            'length' => 10,
            'fixed' => true,
            'unsigned' => true,
            'autoincrement' => true
        );

        $this->assertEquals('SMALLINT', $this->_platform->getSmallIntTypeDeclarationSQL(array()));
        $this->assertEquals('UNSIGNED SMALLINT', $this->_platform->getSmallIntTypeDeclarationSQL(array(
            'unsigned' => true
        )));
        $this->assertEquals('UNSIGNED SMALLINT IDENTITY', $this->_platform->getSmallIntTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('INT', $this->_platform->getIntegerTypeDeclarationSQL(array()));
        $this->assertEquals('UNSIGNED INT', $this->_platform->getIntegerTypeDeclarationSQL(array(
            'unsigned' => true
        )));
        $this->assertEquals('UNSIGNED INT IDENTITY', $this->_platform->getIntegerTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('BIGINT', $this->_platform->getBigIntTypeDeclarationSQL(array()));
        $this->assertEquals('UNSIGNED BIGINT', $this->_platform->getBigIntTypeDeclarationSQL(array(
            'unsigned' => true
        )));
        $this->assertEquals('UNSIGNED BIGINT IDENTITY', $this->_platform->getBigIntTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('LONG BINARY', $this->_platform->getBlobTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('BIT', $this->_platform->getBooleanTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('TEXT', $this->_platform->getClobTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('DATE', $this->_platform->getDateTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('DATETIME', $this->_platform->getDateTimeTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('TIME', $this->_platform->getTimeTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('UNIQUEIDENTIFIER', $this->_platform->getGuidTypeDeclarationSQL($fullColumnDef));

        $this->assertEquals(1, $this->_platform->getVarcharDefaultLength());
        $this->assertEquals(32767, $this->_platform->getVarcharMaxLength());
    }

    public function testHasNativeGuidType()
    {
        $this->assertTrue($this->_platform->hasNativeGuidType());
    }

    public function testGeneratesDDLSnippets()
    {
        $this->assertEquals("CREATE DATABASE 'foobar'", $this->_platform->getCreateDatabaseSQL('foobar'));
        $this->assertEquals("DROP DATABASE 'foobar'", $this->_platform->getDropDatabaseSQL('foobar'));
        $this->assertEquals('CREATE GLOBAL TEMPORARY TABLE', $this->_platform->getCreateTemporaryTableSnippetSQL());
        $this->assertEquals("START DATABASE 'foobar' AUTOSTOP OFF", $this->_platform->getStartDatabaseSQL('foobar'));
        $this->assertEquals('STOP DATABASE "foobar" UNCONDITIONALLY', $this->_platform->getStopDatabaseSQL('foobar'));
        $this->assertEquals('TRUNCATE TABLE foobar', $this->_platform->getTruncateTableSQL('foobar'));
        $this->assertEquals('TRUNCATE TABLE foobar', $this->_platform->getTruncateTableSQL('foobar'), true);

        $viewSql = 'SELECT * FROM footable';
        $this->assertEquals('CREATE VIEW fooview AS ' . $viewSql, $this->_platform->getCreateViewSQL('fooview', $viewSql));
        $this->assertEquals('DROP VIEW fooview', $this->_platform->getDropViewSQL('fooview'));
    }

    public function testGeneratesPrimaryKeyDeclarationSQL()
    {
        $this->assertEquals(
            'CONSTRAINT pk PRIMARY KEY CLUSTERED (a, b)',
            $this->_platform->getPrimaryKeyDeclarationSQL(
                new Index(null, array('a', 'b'), true, true, array('clustered')),
                'pk'
            )
        );
        $this->assertEquals(
            'PRIMARY KEY (a, b)',
            $this->_platform->getPrimaryKeyDeclarationSQL(
                new Index(null, array('a', 'b'), true, true)
            )
        );
    }

    public function testCannotGeneratePrimaryKeyDeclarationSQLWithEmptyColumns()
    {
        $this->setExpectedException('\InvalidArgumentException');

        $this->_platform->getPrimaryKeyDeclarationSQL(new Index('pk', array(), true, true));
    }

    public function testGeneratesCreateUnnamedPrimaryKeySQL()
    {
        $this->assertEquals(
            'ALTER TABLE foo ADD PRIMARY KEY CLUSTERED (a, b)',
            $this->_platform->getCreatePrimaryKeySQL(
                new Index('pk', array('a', 'b'), true, true, array('clustered')),
                'foo'
            )
        );
        $this->assertEquals(
            'ALTER TABLE foo ADD PRIMARY KEY (a, b)',
            $this->_platform->getCreatePrimaryKeySQL(
                new Index('any_pk_name', array('a', 'b'), true, true),
                new Table('foo')
            )
        );
    }

    public function testGeneratesUniqueConstraintDeclarationSQL()
    {
        $this->assertEquals(
            'CONSTRAINT unique_constraint UNIQUE CLUSTERED (a, b)',
            $this->_platform->getUniqueConstraintDeclarationSQL(
                'unique_constraint',
                new Index(null, array('a', 'b'), true, false, array('clustered'))
            )
        );
        $this->assertEquals(
            'UNIQUE (a, b)',
            $this->_platform->getUniqueConstraintDeclarationSQL(null, new Index(null, array('a', 'b'), true, false))
        );
    }

    public function testCannotGenerateUniqueConstraintDeclarationSQLWithEmptyColumns()
    {
        $this->setExpectedException('\InvalidArgumentException');

        $this->_platform->getUniqueConstraintDeclarationSQL('constr', new Index('constr', array(), true));
    }

    public function testGeneratesForeignKeyConstraintsWithAdvancedPlatformOptionsSQL()
    {
        $this->assertEquals(
            'CONSTRAINT fk ' .
                'NOT NULL FOREIGN KEY (a, b) ' .
                'REFERENCES foreign_table (c, d) ' .
                'MATCH UNIQUE SIMPLE ON UPDATE CASCADE ON DELETE SET NULL CHECK ON COMMIT CLUSTERED FOR OLAP WORKLOAD',
            $this->_platform->getForeignKeyDeclarationSQL(
                new ForeignKeyConstraint(array('a', 'b'), 'foreign_table', array('c', 'd'), 'fk', array(
                    'notnull' => true,
                    'match' => SQLAnywherePlatform::FOREIGN_KEY_MATCH_SIMPLE_UNIQUE,
                    'onUpdate' => 'CASCADE',
                    'onDelete' => 'SET NULL',
                    'check_on_commit' => true,
                    'clustered' => true,
                    'for_olap_workload' => true
                ))
            )
        );
        $this->assertEquals(
            'FOREIGN KEY (a, b) REFERENCES foreign_table (c, d)',
            $this->_platform->getForeignKeyDeclarationSQL(
                new ForeignKeyConstraint(array('a', 'b'), 'foreign_table', array('c', 'd'))
            )
        );
    }

    public function testGeneratesForeignKeyMatchClausesSQL()
    {
        $this->assertEquals('SIMPLE', $this->_platform->getForeignKeyMatchClauseSQL(1));
        $this->assertEquals('FULL', $this->_platform->getForeignKeyMatchClauseSQL(2));
        $this->assertEquals('UNIQUE SIMPLE', $this->_platform->getForeignKeyMatchClauseSQL(129));
        $this->assertEquals('UNIQUE FULL', $this->_platform->getForeignKeyMatchClauseSQL(130));
    }

    public function testCannotGenerateInvalidForeignKeyMatchClauseSQL()
    {
        $this->setExpectedException('\InvalidArgumentException');

        $this->_platform->getForeignKeyMatchCLauseSQL(3);
    }

    public function testCannotGenerateForeignKeyConstraintSQLWithEmptyLocalColumns()
    {
        $this->setExpectedException('\InvalidArgumentException');
        $this->_platform->getForeignKeyDeclarationSQL(new ForeignKeyConstraint(array(), 'foreign_tbl', array('c', 'd')));
    }

    public function testCannotGenerateForeignKeyConstraintSQLWithEmptyForeignColumns()
    {
        $this->setExpectedException('\InvalidArgumentException');
        $this->_platform->getForeignKeyDeclarationSQL(new ForeignKeyConstraint(array('a', 'b'), 'foreign_tbl', array()));
    }

    public function testCannotGenerateForeignKeyConstraintSQLWithEmptyForeignTableName()
    {
        $this->setExpectedException('\InvalidArgumentException');
        $this->_platform->getForeignKeyDeclarationSQL(new ForeignKeyConstraint(array('a', 'b'), '', array('c', 'd')));
    }

    public function testCannotGenerateCommonIndexWithCreateConstraintSQL()
    {
        $this->setExpectedException('\InvalidArgumentException');

        $this->_platform->getCreateConstraintSQL(new Index('fooindex', array()), new Table('footable'));
    }

    public function testCannotGenerateCustomConstraintWithCreateConstraintSQL()
    {
        $this->setExpectedException('\InvalidArgumentException');

        $this->_platform->getCreateConstraintSQL($this->getMock('\Doctrine\DBAL\Schema\Constraint'), 'footable');
    }

    public function testGeneratesCreateIndexWithAdvancedPlatformOptionsSQL()
    {
        $this->assertEquals(
            'CREATE VIRTUAL UNIQUE CLUSTERED INDEX fooindex ON footable (a, b) FOR OLAP WORKLOAD',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    array('a', 'b'),
                    true,
                    false,
                    array('virtual', 'clustered', 'for_olap_workload')
                ),
                'footable'
            )
        );
    }

    public function testDoesNotSupportIndexDeclarationInCreateAlterTableStatements()
    {
        $this->setExpectedException('\Doctrine\DBAL\DBALException');

        $this->_platform->getIndexDeclarationSQL('index', new Index('index', array()));
    }

    public function testGeneratesDropIndexSQL()
    {
        $index = new Index('fooindex', array());

        $this->assertEquals('DROP INDEX fooindex', $this->_platform->getDropIndexSQL($index));
        $this->assertEquals('DROP INDEX footable.fooindex', $this->_platform->getDropIndexSQL($index, 'footable'));
        $this->assertEquals('DROP INDEX footable.fooindex', $this->_platform->getDropIndexSQL(
            $index,
            new Table('footable')
        ));
    }

    public function testCannotGenerateDropIndexSQLWithInvalidIndexParameter()
    {
        $this->setExpectedException('\InvalidArgumentException');

        $this->_platform->getDropIndexSQL(array('index'), 'table');
    }

    public function testCannotGenerateDropIndexSQLWithInvalidTableParameter()
    {
        $this->setExpectedException('\InvalidArgumentException');

        $this->_platform->getDropIndexSQL('index', array('table'));
    }

    public function testGeneratesSQLSnippets()
    {
        $this->assertEquals('STRING(column1, "string1", column2, "string2")', $this->_platform->getConcatExpression(
            'column1',
            '"string1"',
            'column2',
            '"string2"'
        ));
        $this->assertEquals('CURRENT DATE', $this->_platform->getCurrentDateSQL());
        $this->assertEquals('CURRENT TIME', $this->_platform->getCurrentTimeSQL());
        $this->assertEquals('CURRENT TIMESTAMP', $this->_platform->getCurrentTimestampSQL());
        $this->assertEquals("DATEADD(DAY, 4, '1987/05/02')", $this->_platform->getDateAddDaysExpression("'1987/05/02'", 4));
        $this->assertEquals("DATEADD(HOUR, 12, '1987/05/02')", $this->_platform->getDateAddHourExpression("'1987/05/02'", 12));
        $this->assertEquals("DATEADD(MINUTE, 2, '1987/05/02')", $this->_platform->getDateAddMinutesExpression("'1987/05/02'", 2));
        $this->assertEquals("DATEADD(MONTH, 102, '1987/05/02')", $this->_platform->getDateAddMonthExpression("'1987/05/02'", 102));
        $this->assertEquals("DATEADD(QUARTER, 5, '1987/05/02')", $this->_platform->getDateAddQuartersExpression("'1987/05/02'", 5));
        $this->assertEquals("DATEADD(SECOND, 1, '1987/05/02')", $this->_platform->getDateAddSecondsExpression("'1987/05/02'", 1));
        $this->assertEquals("DATEADD(WEEK, 3, '1987/05/02')", $this->_platform->getDateAddWeeksExpression("'1987/05/02'", 3));
        $this->assertEquals("DATEADD(YEAR, 10, '1987/05/02')", $this->_platform->getDateAddYearsExpression("'1987/05/02'", 10));
        $this->assertEquals("DATEDIFF(day, '1987/04/01', '1987/05/02')", $this->_platform->getDateDiffExpression("'1987/05/02'", "'1987/04/01'"));
        $this->assertEquals("DATEADD(DAY, -1 * 4, '1987/05/02')", $this->_platform->getDateSubDaysExpression("'1987/05/02'", 4));
        $this->assertEquals("DATEADD(HOUR, -1 * 12, '1987/05/02')", $this->_platform->getDateSubHourExpression("'1987/05/02'", 12));
        $this->assertEquals("DATEADD(MINUTE, -1 * 2, '1987/05/02')", $this->_platform->getDateSubMinutesExpression("'1987/05/02'", 2));
        $this->assertEquals("DATEADD(MONTH, -1 * 102, '1987/05/02')", $this->_platform->getDateSubMonthExpression("'1987/05/02'", 102));
        $this->assertEquals("DATEADD(QUARTER, -1 * 5, '1987/05/02')", $this->_platform->getDateSubQuartersExpression("'1987/05/02'", 5));
        $this->assertEquals("DATEADD(SECOND, -1 * 1, '1987/05/02')", $this->_platform->getDateSubSecondsExpression("'1987/05/02'", 1));
        $this->assertEquals("DATEADD(WEEK, -1 * 3, '1987/05/02')", $this->_platform->getDateSubWeeksExpression("'1987/05/02'", 3));
        $this->assertEquals("DATEADD(YEAR, -1 * 10, '1987/05/02')", $this->_platform->getDateSubYearsExpression("'1987/05/02'", 10));
        $this->assertEquals("Y-m-d H:i:s.u", $this->_platform->getDateTimeFormatString());
        $this->assertEquals("H:i:s.u", $this->_platform->getTimeFormatString());
        $this->assertEquals('', $this->_platform->getForUpdateSQL());
        $this->assertEquals('NEWID()', $this->_platform->getGuidExpression());
        $this->assertEquals('LOCATE(string_column, substring_column)', $this->_platform->getLocateExpression('string_column', 'substring_column'));
        $this->assertEquals('LOCATE(string_column, substring_column, 1)', $this->_platform->getLocateExpression('string_column', 'substring_column', 1));
        $this->assertEquals("HASH(column, 'MD5')", $this->_platform->getMd5Expression('column'));
        $this->assertEquals('SUBSTRING(column, 5)', $this->_platform->getSubstringExpression('column', 5));
        $this->assertEquals('SUBSTRING(column, 5, 2)', $this->_platform->getSubstringExpression('column', 5, 2));
        $this->assertEquals('GLOBAL TEMPORARY', $this->_platform->getTemporaryTableSQL());
        $this->assertEquals(
            'LTRIM(column)',
            $this->_platform->getTrimExpression('column', AbstractPlatform::TRIM_LEADING)
        );
        $this->assertEquals(
            'RTRIM(column)',
            $this->_platform->getTrimExpression('column', AbstractPlatform::TRIM_TRAILING)
        );
        $this->assertEquals(
            'TRIM(column)',
            $this->_platform->getTrimExpression('column')
        );
        $this->assertEquals(
            'TRIM(column)',
            $this->_platform->getTrimExpression('column', AbstractPlatform::TRIM_UNSPECIFIED)
        );
        $this->assertEquals(
            "SUBSTR(column, PATINDEX('%[^' + c + ']%', column))",
            $this->_platform->getTrimExpression('column', AbstractPlatform::TRIM_LEADING, 'c')
        );
        $this->assertEquals(
            "REVERSE(SUBSTR(REVERSE(column), PATINDEX('%[^' + c + ']%', REVERSE(column))))",
            $this->_platform->getTrimExpression('column', AbstractPlatform::TRIM_TRAILING, 'c')
        );
        $this->assertEquals(
            "REVERSE(SUBSTR(REVERSE(SUBSTR(column, PATINDEX('%[^' + c + ']%', column))), PATINDEX('%[^' + c + ']%', " .
            "REVERSE(SUBSTR(column, PATINDEX('%[^' + c + ']%', column))))))",
            $this->_platform->getTrimExpression('column', null, 'c')
        );
        $this->assertEquals(
            "REVERSE(SUBSTR(REVERSE(SUBSTR(column, PATINDEX('%[^' + c + ']%', column))), PATINDEX('%[^' + c + ']%', " .
            "REVERSE(SUBSTR(column, PATINDEX('%[^' + c + ']%', column))))))",
            $this->_platform->getTrimExpression('column', AbstractPlatform::TRIM_UNSPECIFIED, 'c')
        );
    }

    public function testDoesNotSupportRegexp()
    {
        $this->setExpectedException('\Doctrine\DBAL\DBALException');

        $this->_platform->getRegexpExpression();
    }

    public function testHasCorrectDateTimeTzFormatString()
    {
        // Date time type with timezone is not supported before version 12.
        // For versions before we have to ensure that the date time with timezone format
        // equals the normal date time format so that it corresponds to the declaration SQL equality (datetimetz -> datetime).
        $this->assertEquals($this->_platform->getDateTimeFormatString(), $this->_platform->getDateTimeTzFormatString());
    }

    public function testHasCorrectDefaultTransactionIsolationLevel()
    {
        $this->assertEquals(
            Connection::TRANSACTION_READ_UNCOMMITTED,
            $this->_platform->getDefaultTransactionIsolationLevel()
        );
    }

    public function testGeneratesTransactionsCommands()
    {
        $this->assertEquals(
            'SET TEMPORARY OPTION isolation_level = 0',
            $this->_platform->getSetTransactionIsolationSQL(Connection::TRANSACTION_READ_UNCOMMITTED)
        );
        $this->assertEquals(
            'SET TEMPORARY OPTION isolation_level = 1',
            $this->_platform->getSetTransactionIsolationSQL(Connection::TRANSACTION_READ_COMMITTED)
        );
        $this->assertEquals(
            'SET TEMPORARY OPTION isolation_level = 2',
            $this->_platform->getSetTransactionIsolationSQL(Connection::TRANSACTION_REPEATABLE_READ)
        );
        $this->assertEquals(
            'SET TEMPORARY OPTION isolation_level = 3',
            $this->_platform->getSetTransactionIsolationSQL(Connection::TRANSACTION_SERIALIZABLE)
        );
    }

    public function testCannotGenerateTransactionCommandWithInvalidIsolationLevel()
    {
        $this->setExpectedException('\InvalidArgumentException');

        $this->_platform->getSetTransactionIsolationSQL('invalid_transaction_isolation_level');
    }

    public function testModifiesLimitQuery()
    {
        $this->assertEquals(
            'SELECT TOP 10 * FROM user',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 0)
        );
    }

    public function testModifiesLimitQueryWithEmptyOffset()
    {
        $this->assertEquals(
            'SELECT TOP 10 * FROM user',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 10)
        );
    }

    public function testModifiesLimitQueryWithOffset()
    {
        $this->assertEquals(
            'SELECT TOP 10 START AT 6 * FROM user',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 5)
        );
        $this->assertEquals(
            'SELECT TOP ALL START AT 6 * FROM user',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 0, 5)
        );
    }

    public function testModifiesLimitQueryWithSubSelect()
    {
        $this->assertEquals(
            'SELECT TOP 10 * FROM (SELECT u.id as uid, u.name as uname FROM user) AS doctrine_tbl',
            $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id as uid, u.name as uname FROM user) AS doctrine_tbl', 10)
        );
    }

    public function testPrefersIdentityColumns()
    {
        $this->assertTrue($this->_platform->prefersIdentityColumns());
    }

    public function testDoesNotPreferSequences()
    {
        $this->assertFalse($this->_platform->prefersSequences());
    }

    public function testSupportsIdentityColumns()
    {
        $this->assertTrue($this->_platform->supportsIdentityColumns());
    }

    public function testSupportsPrimaryConstraints()
    {
        $this->assertTrue($this->_platform->supportsPrimaryConstraints());
    }

    public function testSupportsForeignKeyConstraints()
    {
        $this->assertTrue($this->_platform->supportsForeignKeyConstraints());
    }

    public function testSupportsForeignKeyOnUpdate()
    {
        $this->assertTrue($this->_platform->supportsForeignKeyOnUpdate());
    }

    public function testSupportsAlterTable()
    {
        $this->assertTrue($this->_platform->supportsAlterTable());
    }

    public function testSupportsTransactions()
    {
        $this->assertTrue($this->_platform->supportsTransactions());
    }

    public function testSupportsSchemas()
    {
        $this->assertFalse($this->_platform->supportsSchemas());
    }

    public function testSupportsIndexes()
    {
        $this->assertTrue($this->_platform->supportsIndexes());
    }

    public function testSupportsCommentOnStatement()
    {
        $this->assertTrue($this->_platform->supportsCommentOnStatement());
    }

    public function testSupportsSavePoints()
    {
        $this->assertTrue($this->_platform->supportsSavepoints());
    }

    public function testSupportsReleasePoints()
    {
        $this->assertTrue($this->_platform->supportsReleaseSavepoints());
    }

    public function testSupportsCreateDropDatabase()
    {
        $this->assertTrue($this->_platform->supportsCreateDropDatabase());
    }

    public function testSupportsGettingAffectedRows()
    {
        $this->assertTrue($this->_platform->supportsGettingAffectedRows());
    }

    public function testDoesNotSupportSequences()
    {
        $this->assertFalse($this->_platform->supportsSequences());
    }

    public function testDoesNotSupportInlineColumnComments()
    {
        $this->assertFalse($this->_platform->supportsInlineColumnComments());
    }

    public function testCannotEmulateSchemas()
    {
        $this->assertFalse($this->_platform->canEmulateSchemas());
    }

    public function testInitializesDoctrineTypeMappings()
    {
        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('integer'));
        $this->assertSame('integer', $this->_platform->getDoctrineTypeMapping('integer'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('binary'));
        $this->assertSame('binary', $this->_platform->getDoctrineTypeMapping('binary'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('varbinary'));
        $this->assertSame('binary', $this->_platform->getDoctrineTypeMapping('varbinary'));
    }

    protected function getBinaryDefaultLength()
    {
        return 1;
    }

    protected function getBinaryMaxLength()
    {
        return 32767;
    }

    public function testReturnsBinaryTypeDeclarationSQL()
    {
        $this->assertSame('VARBINARY(1)', $this->_platform->getBinaryTypeDeclarationSQL(array()));
        $this->assertSame('VARBINARY(1)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 0)));
        $this->assertSame('VARBINARY(32767)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 32767)));
        $this->assertSame('LONG BINARY', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 32768)));

        $this->assertSame('BINARY(1)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true)));
        $this->assertSame('BINARY(1)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 0)));
        $this->assertSame('BINARY(32767)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 32767)));
        $this->assertSame('LONG BINARY', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 32768)));
    }

    /**
     * @group DBAL-234
     */
    protected function getAlterTableRenameIndexSQL()
    {
        return array(
            'ALTER INDEX idx_foo ON mytable RENAME TO idx_bar',
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexSQL()
    {
        return array(
            'ALTER INDEX "create" ON "table" RENAME TO "select"',
            'ALTER INDEX "foo" ON "table" RENAME TO "bar"',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableRenameColumnSQL()
    {
        return array(
            'ALTER TABLE mytable RENAME unquoted1 TO unquoted',
            'ALTER TABLE mytable RENAME unquoted2 TO "where"',
            'ALTER TABLE mytable RENAME unquoted3 TO "foo"',
            'ALTER TABLE mytable RENAME "create" TO reserved_keyword',
            'ALTER TABLE mytable RENAME "table" TO "from"',
            'ALTER TABLE mytable RENAME "select" TO "bar"',
            'ALTER TABLE mytable RENAME quoted1 TO quoted',
            'ALTER TABLE mytable RENAME quoted2 TO "and"',
            'ALTER TABLE mytable RENAME quoted3 TO "baz"',
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
            'ALTER INDEX idx_foo ON myschema.mytable RENAME TO idx_bar',
        );
    }

    /**
     * @group DBAL-807
     */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL()
    {
        return array(
            'ALTER INDEX "create" ON "schema"."table" RENAME TO "select"',
            'ALTER INDEX "foo" ON "schema"."table" RENAME TO "bar"',
        );
    }

    /**
     * @group DBAL-423
     */
    public function testReturnsGuidTypeDeclarationSQL()
    {
        $this->assertSame('UNIQUEIDENTIFIER', $this->_platform->getGuidTypeDeclarationSQL(array()));
    }

    /**
     * {@inheritdoc}
     */
    public function getAlterTableRenameColumnSQL()
    {
        return array(
            'ALTER TABLE foo RENAME bar TO baz',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesTableIdentifiersInAlterTableSQL()
    {
        return array(
            'ALTER TABLE "foo" DROP FOREIGN KEY fk1',
            'ALTER TABLE "foo" DROP FOREIGN KEY fk2',
            'ALTER TABLE "foo" RENAME id TO war',
            'ALTER TABLE "foo" ADD bloo INT NOT NULL, DROP baz, ALTER bar INT DEFAULT NULL',
            'ALTER TABLE "foo" RENAME "table"',
            'ALTER TABLE "table" ADD CONSTRAINT fk_add FOREIGN KEY (fk3) REFERENCES fk_table (id)',
            'ALTER TABLE "table" ADD CONSTRAINT fk2 FOREIGN KEY (fk2) REFERENCES fk_table2 (id)',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommentOnColumnSQL()
    {
        return array(
            'COMMENT ON COLUMN foo.bar IS \'comment\'',
            'COMMENT ON COLUMN "Foo"."BAR" IS \'comment\'',
            'COMMENT ON COLUMN "select"."from" IS \'comment\'',
        );
    }

    /**
     * @group DBAL-1004
     */
    public function testAltersTableColumnCommentWithExplicitlyQuotedIdentifiers()
    {
        $table1 = new Table('"foo"', array(new Column('"bar"', Type::getType('integer'))));
        $table2 = new Table('"foo"', array(new Column('"bar"', Type::getType('integer'), array('comment' => 'baz'))));

        $comparator = new Comparator();

        $tableDiff = $comparator->diffTable($table1, $table2);

        $this->assertInstanceOf('Doctrine\DBAL\Schema\TableDiff', $tableDiff);
        $this->assertSame(
            array(
                'COMMENT ON COLUMN "foo"."bar" IS \'baz\'',
            ),
            $this->_platform->getAlterTableSQL($tableDiff)
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
            array('NO ACTION', 'RESTRICT'),
            array('RESTRICT', 'RESTRICT'),
            array('SET DEFAULT', 'SET DEFAULT'),
            array('CaScAdE', 'CASCADE'),
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL()
    {
        return 'CONSTRAINT "select" UNIQUE (foo)';
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInIndexDeclarationSQL()
    {
        return ''; // not supported by this platform
    }

    /**
     * {@inheritdoc}
     */
    protected function supportsInlineIndexDeclaration()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAlterStringToFixedStringSQL()
    {
        return array(
            'ALTER TABLE mytable ALTER name CHAR(2) NOT NULL',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL()
    {
        return array(
            'ALTER INDEX idx_foo ON mytable RENAME TO idx_foo_renamed',
        );
    }
}
