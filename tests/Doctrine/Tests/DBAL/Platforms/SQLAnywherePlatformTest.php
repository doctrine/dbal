<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLAnywherePlatform;
use Doctrine\DBAL\Platforms\TrimMode;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\TransactionIsolationLevel;
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
        self::assertEquals('sqlanywhere', $this->_platform->getName());
    }

    public function testGeneratesCreateTableSQLWithCommonIndexes()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('name', 'string', array('length' => 50));
        $table->setPrimaryKey(array('id'));
        $table->addIndex(array('name'));
        $table->addIndex(array('id', 'name'), 'composite_idx');

        self::assertEquals(
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

        self::assertEquals(
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

        self::assertEquals(
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

        self::assertEquals(
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

        self::assertSame($expectedResult, $this->_platform->appendLockHint($fromClause, $lockMode));
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
        self::assertEquals(128, $this->_platform->getMaxIdentifierLength());
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

        self::assertEquals(
            $fixedSchemaElementName,
            $this->_platform->fixSchemaElementName($schemaElementName)
        );
        self::assertEquals(
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

        self::assertEquals('SMALLINT', $this->_platform->getSmallIntTypeDeclarationSQL(array()));
        self::assertEquals('UNSIGNED SMALLINT', $this->_platform->getSmallIntTypeDeclarationSQL(array(
            'unsigned' => true
        )));
        self::assertEquals('UNSIGNED SMALLINT IDENTITY', $this->_platform->getSmallIntTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('INT', $this->_platform->getIntegerTypeDeclarationSQL(array()));
        self::assertEquals('UNSIGNED INT', $this->_platform->getIntegerTypeDeclarationSQL(array(
            'unsigned' => true
        )));
        self::assertEquals('UNSIGNED INT IDENTITY', $this->_platform->getIntegerTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('BIGINT', $this->_platform->getBigIntTypeDeclarationSQL(array()));
        self::assertEquals('UNSIGNED BIGINT', $this->_platform->getBigIntTypeDeclarationSQL(array(
            'unsigned' => true
        )));
        self::assertEquals('UNSIGNED BIGINT IDENTITY', $this->_platform->getBigIntTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('LONG BINARY', $this->_platform->getBlobTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('BIT', $this->_platform->getBooleanTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('TEXT', $this->_platform->getClobTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('DATE', $this->_platform->getDateTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('DATETIME', $this->_platform->getDateTimeTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('TIME', $this->_platform->getTimeTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('UNIQUEIDENTIFIER', $this->_platform->getGuidTypeDeclarationSQL($fullColumnDef));

        self::assertEquals(1, $this->_platform->getVarcharDefaultLength());
        self::assertEquals(32767, $this->_platform->getVarcharMaxLength());
    }

    public function testHasNativeGuidType()
    {
        self::assertTrue($this->_platform->hasNativeGuidType());
    }

    public function testGeneratesDDLSnippets()
    {
        self::assertEquals("CREATE DATABASE 'foobar'", $this->_platform->getCreateDatabaseSQL('foobar'));
        self::assertEquals("CREATE DATABASE 'foobar'", $this->_platform->getCreateDatabaseSQL('"foobar"'));
        self::assertEquals("CREATE DATABASE 'create'", $this->_platform->getCreateDatabaseSQL('create'));
        self::assertEquals("DROP DATABASE 'foobar'", $this->_platform->getDropDatabaseSQL('foobar'));
        self::assertEquals("DROP DATABASE 'foobar'", $this->_platform->getDropDatabaseSQL('"foobar"'));
        self::assertEquals("DROP DATABASE 'create'", $this->_platform->getDropDatabaseSQL('create'));
        self::assertEquals('CREATE GLOBAL TEMPORARY TABLE', $this->_platform->getCreateTemporaryTableSnippetSQL());
        self::assertEquals("START DATABASE 'foobar' AUTOSTOP OFF", $this->_platform->getStartDatabaseSQL('foobar'));
        self::assertEquals("START DATABASE 'foobar' AUTOSTOP OFF", $this->_platform->getStartDatabaseSQL('"foobar"'));
        self::assertEquals("START DATABASE 'create' AUTOSTOP OFF", $this->_platform->getStartDatabaseSQL('create'));
        self::assertEquals('STOP DATABASE "foobar" UNCONDITIONALLY', $this->_platform->getStopDatabaseSQL('foobar'));
        self::assertEquals('STOP DATABASE "foobar" UNCONDITIONALLY', $this->_platform->getStopDatabaseSQL('"foobar"'));
        self::assertEquals('STOP DATABASE "create" UNCONDITIONALLY', $this->_platform->getStopDatabaseSQL('create'));
        self::assertEquals('TRUNCATE TABLE foobar', $this->_platform->getTruncateTableSQL('foobar'));
        self::assertEquals('TRUNCATE TABLE foobar', $this->_platform->getTruncateTableSQL('foobar'), true);

        $viewSql = 'SELECT * FROM footable';
        self::assertEquals('CREATE VIEW fooview AS ' . $viewSql, $this->_platform->getCreateViewSQL('fooview', $viewSql));
        self::assertEquals('DROP VIEW fooview', $this->_platform->getDropViewSQL('fooview'));
    }

    public function testGeneratesPrimaryKeyDeclarationSQL()
    {
        self::assertEquals(
            'CONSTRAINT pk PRIMARY KEY CLUSTERED (a, b)',
            $this->_platform->getPrimaryKeyDeclarationSQL(
                new Index(null, array('a', 'b'), true, true, array('clustered')),
                'pk'
            )
        );
        self::assertEquals(
            'PRIMARY KEY (a, b)',
            $this->_platform->getPrimaryKeyDeclarationSQL(
                new Index(null, array('a', 'b'), true, true)
            )
        );
    }

    public function testCannotGeneratePrimaryKeyDeclarationSQLWithEmptyColumns()
    {
        $this->expectException('\InvalidArgumentException');

        $this->_platform->getPrimaryKeyDeclarationSQL(new Index('pk', array(), true, true));
    }

    public function testGeneratesCreateUnnamedPrimaryKeySQL()
    {
        self::assertEquals(
            'ALTER TABLE foo ADD PRIMARY KEY CLUSTERED (a, b)',
            $this->_platform->getCreatePrimaryKeySQL(
                new Index('pk', array('a', 'b'), true, true, array('clustered')),
                'foo'
            )
        );
        self::assertEquals(
            'ALTER TABLE foo ADD PRIMARY KEY (a, b)',
            $this->_platform->getCreatePrimaryKeySQL(
                new Index('any_pk_name', array('a', 'b'), true, true),
                new Table('foo')
            )
        );
    }

    public function testGeneratesUniqueConstraintDeclarationSQL()
    {
        self::assertEquals(
            'CONSTRAINT unique_constraint UNIQUE CLUSTERED (a, b)',
            $this->_platform->getUniqueConstraintDeclarationSQL(
                'unique_constraint',
                new UniqueConstraint(null, array('a', 'b'), array('clustered'))
            )
        );
        self::assertEquals(
            'CONSTRAINT UNIQUE (a, b)',
            $this->_platform->getUniqueConstraintDeclarationSQL(null, new UniqueConstraint(null, array('a', 'b')))
        );
    }

    public function testCannotGenerateUniqueConstraintDeclarationSQLWithEmptyColumns()
    {
        $this->expectException('\InvalidArgumentException');

        $this->_platform->getUniqueConstraintDeclarationSQL('constr', new UniqueConstraint('constr', array()));
    }

    public function testGeneratesForeignKeyConstraintsWithAdvancedPlatformOptionsSQL()
    {
        self::assertEquals(
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
        self::assertEquals(
            'FOREIGN KEY (a, b) REFERENCES foreign_table (c, d)',
            $this->_platform->getForeignKeyDeclarationSQL(
                new ForeignKeyConstraint(array('a', 'b'), 'foreign_table', array('c', 'd'))
            )
        );
    }

    public function testGeneratesForeignKeyMatchClausesSQL()
    {
        self::assertEquals('SIMPLE', $this->_platform->getForeignKeyMatchClauseSQL(1));
        self::assertEquals('FULL', $this->_platform->getForeignKeyMatchClauseSQL(2));
        self::assertEquals('UNIQUE SIMPLE', $this->_platform->getForeignKeyMatchClauseSQL(129));
        self::assertEquals('UNIQUE FULL', $this->_platform->getForeignKeyMatchClauseSQL(130));
    }

    public function testCannotGenerateInvalidForeignKeyMatchClauseSQL()
    {
        $this->expectException('\InvalidArgumentException');

        $this->_platform->getForeignKeyMatchCLauseSQL(3);
    }

    public function testCannotGenerateForeignKeyConstraintSQLWithEmptyLocalColumns()
    {
        $this->expectException('\InvalidArgumentException');
        $this->_platform->getForeignKeyDeclarationSQL(new ForeignKeyConstraint(array(), 'foreign_tbl', array('c', 'd')));
    }

    public function testCannotGenerateForeignKeyConstraintSQLWithEmptyForeignColumns()
    {
        $this->expectException('\InvalidArgumentException');
        $this->_platform->getForeignKeyDeclarationSQL(new ForeignKeyConstraint(array('a', 'b'), 'foreign_tbl', array()));
    }

    public function testCannotGenerateForeignKeyConstraintSQLWithEmptyForeignTableName()
    {
        $this->expectException('\InvalidArgumentException');
        $this->_platform->getForeignKeyDeclarationSQL(new ForeignKeyConstraint(array('a', 'b'), '', array('c', 'd')));
    }

    public function testCannotGenerateCommonIndexWithCreateConstraintSQL()
    {
        $this->expectException('\InvalidArgumentException');

        $this->_platform->getCreateConstraintSQL(new Index('fooindex', array()), new Table('footable'));
    }

    public function testCannotGenerateCustomConstraintWithCreateConstraintSQL()
    {
        $this->expectException('\InvalidArgumentException');

        $this->_platform->getCreateConstraintSQL($this->createMock('\Doctrine\DBAL\Schema\Constraint'), 'footable');
    }

    public function testGeneratesCreateIndexWithAdvancedPlatformOptionsSQL()
    {
        self::assertEquals(
            'CREATE UNIQUE INDEX fooindex ON footable (a, b) WITH NULLS DISTINCT',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    ['a', 'b'],
                    true,
                    false,
                    ['with_nulls_distinct']
                ),
                'footable'
            )
        );

        // WITH NULLS DISTINCT clause not available on primary indexes.
        self::assertEquals(
            'ALTER TABLE footable ADD PRIMARY KEY (a, b)',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    ['a', 'b'],
                    false,
                    true,
                    ['with_nulls_distinct']
                ),
                'footable'
            )
        );

        // WITH NULLS DISTINCT clause not available on non-unique indexes.
        self::assertEquals(
            'CREATE INDEX fooindex ON footable (a, b)',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    ['a', 'b'],
                    false,
                    false,
                    ['with_nulls_distinct']
                ),
                'footable'
            )
        );

        self::assertEquals(
            'CREATE VIRTUAL UNIQUE CLUSTERED INDEX fooindex ON footable (a, b) WITH NULLS NOT DISTINCT FOR OLAP WORKLOAD',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    ['a', 'b'],
                    true,
                    false,
                    ['virtual', 'clustered', 'with_nulls_not_distinct', 'for_olap_workload']
                ),
                'footable'
            )
        );
        self::assertEquals(
            'CREATE VIRTUAL CLUSTERED INDEX fooindex ON footable (a, b) FOR OLAP WORKLOAD',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    ['a', 'b'],
                    false,
                    false,
                    ['virtual', 'clustered', 'with_nulls_not_distinct', 'for_olap_workload']
                ),
                'footable'
            )
        );

        // WITH NULLS NOT DISTINCT clause not available on primary indexes.
        self::assertEquals(
            'ALTER TABLE footable ADD PRIMARY KEY (a, b)',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    ['a', 'b'],
                    false,
                    true,
                    ['with_nulls_not_distinct']
                ),
                'footable'
            )
        );

        // WITH NULLS NOT DISTINCT clause not available on non-unique indexes.
        self::assertEquals(
            'CREATE INDEX fooindex ON footable (a, b)',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    ['a', 'b'],
                    false,
                    false,
                    ['with_nulls_not_distinct']
                ),
                'footable'
            )
        );
    }

    public function testThrowsExceptionOnInvalidWithNullsNotDistinctIndexOptions()
    {
        $this->expectException('UnexpectedValueException');

        $this->_platform->getCreateIndexSQL(
            new Index(
                'fooindex',
                ['a', 'b'],
                false,
                false,
                ['with_nulls_distinct', 'with_nulls_not_distinct']
            ),
            'footable'
        );
    }

    public function testDoesNotSupportIndexDeclarationInCreateAlterTableStatements()
    {
        $this->expectException('\Doctrine\DBAL\DBALException');

        $this->_platform->getIndexDeclarationSQL('index', new Index('index', array()));
    }

    public function testGeneratesDropIndexSQL()
    {
        $index = new Index('fooindex', array());

        self::assertEquals('DROP INDEX fooindex', $this->_platform->getDropIndexSQL($index));
        self::assertEquals('DROP INDEX footable.fooindex', $this->_platform->getDropIndexSQL($index, 'footable'));
        self::assertEquals('DROP INDEX footable.fooindex', $this->_platform->getDropIndexSQL(
            $index,
            new Table('footable')
        ));
    }

    public function testCannotGenerateDropIndexSQLWithInvalidIndexParameter()
    {
        $this->expectException('\InvalidArgumentException');

        $this->_platform->getDropIndexSQL(array('index'), 'table');
    }

    public function testCannotGenerateDropIndexSQLWithInvalidTableParameter()
    {
        $this->expectException('\InvalidArgumentException');

        $this->_platform->getDropIndexSQL('index', array('table'));
    }

    public function testGeneratesSQLSnippets()
    {
        self::assertEquals('STRING(column1, "string1", column2, "string2")', $this->_platform->getConcatExpression(
            'column1',
            '"string1"',
            'column2',
            '"string2"'
        ));
        self::assertEquals('CURRENT DATE', $this->_platform->getCurrentDateSQL());
        self::assertEquals('CURRENT TIME', $this->_platform->getCurrentTimeSQL());
        self::assertEquals('CURRENT TIMESTAMP', $this->_platform->getCurrentTimestampSQL());
        self::assertEquals("DATEADD(DAY, 4, '1987/05/02')", $this->_platform->getDateAddDaysExpression("'1987/05/02'", 4));
        self::assertEquals("DATEADD(HOUR, 12, '1987/05/02')", $this->_platform->getDateAddHourExpression("'1987/05/02'", 12));
        self::assertEquals("DATEADD(MINUTE, 2, '1987/05/02')", $this->_platform->getDateAddMinutesExpression("'1987/05/02'", 2));
        self::assertEquals("DATEADD(MONTH, 102, '1987/05/02')", $this->_platform->getDateAddMonthExpression("'1987/05/02'", 102));
        self::assertEquals("DATEADD(QUARTER, 5, '1987/05/02')", $this->_platform->getDateAddQuartersExpression("'1987/05/02'", 5));
        self::assertEquals("DATEADD(SECOND, 1, '1987/05/02')", $this->_platform->getDateAddSecondsExpression("'1987/05/02'", 1));
        self::assertEquals("DATEADD(WEEK, 3, '1987/05/02')", $this->_platform->getDateAddWeeksExpression("'1987/05/02'", 3));
        self::assertEquals("DATEADD(YEAR, 10, '1987/05/02')", $this->_platform->getDateAddYearsExpression("'1987/05/02'", 10));
        self::assertEquals("DATEDIFF(day, '1987/04/01', '1987/05/02')", $this->_platform->getDateDiffExpression("'1987/05/02'", "'1987/04/01'"));
        self::assertEquals("DATEADD(DAY, -1 * 4, '1987/05/02')", $this->_platform->getDateSubDaysExpression("'1987/05/02'", 4));
        self::assertEquals("DATEADD(HOUR, -1 * 12, '1987/05/02')", $this->_platform->getDateSubHourExpression("'1987/05/02'", 12));
        self::assertEquals("DATEADD(MINUTE, -1 * 2, '1987/05/02')", $this->_platform->getDateSubMinutesExpression("'1987/05/02'", 2));
        self::assertEquals("DATEADD(MONTH, -1 * 102, '1987/05/02')", $this->_platform->getDateSubMonthExpression("'1987/05/02'", 102));
        self::assertEquals("DATEADD(QUARTER, -1 * 5, '1987/05/02')", $this->_platform->getDateSubQuartersExpression("'1987/05/02'", 5));
        self::assertEquals("DATEADD(SECOND, -1 * 1, '1987/05/02')", $this->_platform->getDateSubSecondsExpression("'1987/05/02'", 1));
        self::assertEquals("DATEADD(WEEK, -1 * 3, '1987/05/02')", $this->_platform->getDateSubWeeksExpression("'1987/05/02'", 3));
        self::assertEquals("DATEADD(YEAR, -1 * 10, '1987/05/02')", $this->_platform->getDateSubYearsExpression("'1987/05/02'", 10));
        self::assertEquals("Y-m-d H:i:s.u", $this->_platform->getDateTimeFormatString());
        self::assertEquals("H:i:s.u", $this->_platform->getTimeFormatString());
        self::assertEquals('', $this->_platform->getForUpdateSQL());
        self::assertEquals('NEWID()', $this->_platform->getGuidExpression());
        self::assertEquals('LOCATE(string_column, substring_column)', $this->_platform->getLocateExpression('string_column', 'substring_column'));
        self::assertEquals('LOCATE(string_column, substring_column, 1)', $this->_platform->getLocateExpression('string_column', 'substring_column', 1));
        self::assertEquals("HASH(column, 'MD5')", $this->_platform->getMd5Expression('column'));
        self::assertEquals('SUBSTRING(column, 5)', $this->_platform->getSubstringExpression('column', 5));
        self::assertEquals('SUBSTRING(column, 5, 2)', $this->_platform->getSubstringExpression('column', 5, 2));
        self::assertEquals('GLOBAL TEMPORARY', $this->_platform->getTemporaryTableSQL());
        self::assertEquals(
            'LTRIM(column)',
            $this->_platform->getTrimExpression('column', TrimMode::LEADING)
        );
        self::assertEquals(
            'RTRIM(column)',
            $this->_platform->getTrimExpression('column', TrimMode::TRAILING)
        );
        self::assertEquals(
            'TRIM(column)',
            $this->_platform->getTrimExpression('column')
        );
        self::assertEquals(
            'TRIM(column)',
            $this->_platform->getTrimExpression('column', TrimMode::UNSPECIFIED)
        );
        self::assertEquals(
            "SUBSTR(column, PATINDEX('%[^' + c + ']%', column))",
            $this->_platform->getTrimExpression('column', TrimMode::LEADING, 'c')
        );
        self::assertEquals(
            "REVERSE(SUBSTR(REVERSE(column), PATINDEX('%[^' + c + ']%', REVERSE(column))))",
            $this->_platform->getTrimExpression('column', TrimMode::TRAILING, 'c')
        );
        self::assertEquals(
            "REVERSE(SUBSTR(REVERSE(SUBSTR(column, PATINDEX('%[^' + c + ']%', column))), PATINDEX('%[^' + c + ']%', " .
            "REVERSE(SUBSTR(column, PATINDEX('%[^' + c + ']%', column))))))",
            $this->_platform->getTrimExpression('column', null, 'c')
        );
        self::assertEquals(
            "REVERSE(SUBSTR(REVERSE(SUBSTR(column, PATINDEX('%[^' + c + ']%', column))), PATINDEX('%[^' + c + ']%', " .
            "REVERSE(SUBSTR(column, PATINDEX('%[^' + c + ']%', column))))))",
            $this->_platform->getTrimExpression('column', TrimMode::UNSPECIFIED, 'c')
        );
    }

    public function testHasCorrectDateTimeTzFormatString()
    {
        self::assertEquals('Y-m-d H:i:s.uP', $this->_platform->getDateTimeTzFormatString());
    }

    public function testGeneratesDateTimeTzColumnTypeDeclarationSQL()
    {
        self::assertEquals(
            'TIMESTAMP WITH TIME ZONE',
            $this->_platform->getDateTimeTzTypeDeclarationSQL([
                'length' => 10,
                'fixed' => true,
                'unsigned' => true,
                'autoincrement' => true,
            ])
        );
    }

    public function testInitializesDateTimeTzTypeMapping()
    {
        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('timestamp with time zone'));
        self::assertEquals('datetime', $this->_platform->getDoctrineTypeMapping('timestamp with time zone'));
    }

    public function testHasCorrectDefaultTransactionIsolationLevel()
    {
        self::assertEquals(
            TransactionIsolationLevel::READ_UNCOMMITTED,
            $this->_platform->getDefaultTransactionIsolationLevel()
        );
    }

    public function testGeneratesTransactionsCommands()
    {
        self::assertEquals(
            'SET TEMPORARY OPTION isolation_level = 0',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_UNCOMMITTED)
        );
        self::assertEquals(
            'SET TEMPORARY OPTION isolation_level = 1',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_COMMITTED)
        );
        self::assertEquals(
            'SET TEMPORARY OPTION isolation_level = 2',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::REPEATABLE_READ)
        );
        self::assertEquals(
            'SET TEMPORARY OPTION isolation_level = 3',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::SERIALIZABLE)
        );
    }

    public function testCannotGenerateTransactionCommandWithInvalidIsolationLevel()
    {
        $this->expectException('\InvalidArgumentException');

        $this->_platform->getSetTransactionIsolationSQL('invalid_transaction_isolation_level');
    }

    public function testModifiesLimitQuery()
    {
        self::assertEquals(
            'SELECT TOP 10 * FROM user',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 0)
        );
    }

    public function testModifiesLimitQueryWithEmptyOffset()
    {
        self::assertEquals(
            'SELECT TOP 10 * FROM user',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 10)
        );
    }

    public function testModifiesLimitQueryWithOffset()
    {
        self::assertEquals(
            'SELECT TOP 10 START AT 6 * FROM user',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 5)
        );
        self::assertEquals(
            'SELECT TOP ALL START AT 6 * FROM user',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 0, 5)
        );
    }

    public function testModifiesLimitQueryWithSubSelect()
    {
        self::assertEquals(
            'SELECT TOP 10 * FROM (SELECT u.id as uid, u.name as uname FROM user) AS doctrine_tbl',
            $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id as uid, u.name as uname FROM user) AS doctrine_tbl', 10)
        );
    }

    public function testPrefersIdentityColumns()
    {
        self::assertTrue($this->_platform->prefersIdentityColumns());
    }

    public function testDoesNotPreferSequences()
    {
        self::assertFalse($this->_platform->prefersSequences());
    }

    public function testSupportsIdentityColumns()
    {
        self::assertTrue($this->_platform->supportsIdentityColumns());
    }

    public function testSupportsPrimaryConstraints()
    {
        self::assertTrue($this->_platform->supportsPrimaryConstraints());
    }

    public function testSupportsForeignKeyConstraints()
    {
        self::assertTrue($this->_platform->supportsForeignKeyConstraints());
    }

    public function testSupportsForeignKeyOnUpdate()
    {
        self::assertTrue($this->_platform->supportsForeignKeyOnUpdate());
    }

    public function testSupportsAlterTable()
    {
        self::assertTrue($this->_platform->supportsAlterTable());
    }

    public function testSupportsTransactions()
    {
        self::assertTrue($this->_platform->supportsTransactions());
    }

    public function testSupportsSchemas()
    {
        self::assertFalse($this->_platform->supportsSchemas());
    }

    public function testSupportsIndexes()
    {
        self::assertTrue($this->_platform->supportsIndexes());
    }

    public function testSupportsCommentOnStatement()
    {
        self::assertTrue($this->_platform->supportsCommentOnStatement());
    }

    public function testSupportsSavePoints()
    {
        self::assertTrue($this->_platform->supportsSavepoints());
    }

    public function testSupportsReleasePoints()
    {
        self::assertTrue($this->_platform->supportsReleaseSavepoints());
    }

    public function testSupportsCreateDropDatabase()
    {
        self::assertTrue($this->_platform->supportsCreateDropDatabase());
    }

    public function testSupportsGettingAffectedRows()
    {
        self::assertTrue($this->_platform->supportsGettingAffectedRows());
    }

    public function testDoesNotSupportSequences()
    {
        self::markTestSkipped('This version of the platform now supports sequences.');
    }

    public function testSupportsSequences()
    {
        self::assertTrue($this->_platform->supportsSequences());
    }

    public function testGeneratesSequenceSqlCommands()
    {
        $sequence = new Sequence('myseq', 20, 1);
        self::assertEquals(
            'CREATE SEQUENCE myseq INCREMENT BY 20 START WITH 1 MINVALUE 1',
            $this->_platform->getCreateSequenceSQL($sequence)
        );
        self::assertEquals(
            'ALTER SEQUENCE myseq INCREMENT BY 20',
            $this->_platform->getAlterSequenceSQL($sequence)
        );
        self::assertEquals(
            'DROP SEQUENCE myseq',
            $this->_platform->getDropSequenceSQL('myseq')
        );
        self::assertEquals(
            'DROP SEQUENCE myseq',
            $this->_platform->getDropSequenceSQL($sequence)
        );
        self::assertEquals(
            'SELECT myseq.NEXTVAL',
            $this->_platform->getSequenceNextValSQL('myseq')
        );
        self::assertEquals(
            'SELECT sequence_name, increment_by, start_with, min_value FROM SYS.SYSSEQUENCE',
            $this->_platform->getListSequencesSQL(null)
        );
    }

    public function testDoesNotSupportInlineColumnComments()
    {
        self::assertFalse($this->_platform->supportsInlineColumnComments());
    }

    public function testCannotEmulateSchemas()
    {
        self::assertFalse($this->_platform->canEmulateSchemas());
    }

    public function testInitializesDoctrineTypeMappings()
    {
        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('integer'));
        self::assertSame('integer', $this->_platform->getDoctrineTypeMapping('integer'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('binary'));
        self::assertSame('binary', $this->_platform->getDoctrineTypeMapping('binary'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('varbinary'));
        self::assertSame('binary', $this->_platform->getDoctrineTypeMapping('varbinary'));
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
        self::assertSame('VARBINARY(1)', $this->_platform->getBinaryTypeDeclarationSQL(array()));
        self::assertSame('VARBINARY(1)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 0)));
        self::assertSame('VARBINARY(32767)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 32767)));
        self::assertSame('LONG BINARY', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 32768)));

        self::assertSame('BINARY(1)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true)));
        self::assertSame('BINARY(1)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 0)));
        self::assertSame('BINARY(32767)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 32767)));
        self::assertSame('LONG BINARY', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 32768)));
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
        self::assertSame('UNIQUEIDENTIFIER', $this->_platform->getGuidTypeDeclarationSQL(array()));
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

        self::assertInstanceOf('Doctrine\DBAL\Schema\TableDiff', $tableDiff);
        self::assertSame(
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
    protected function getQuotesReservedKeywordInTruncateTableSQL()
    {
        return 'TRUNCATE TABLE "select"';
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
    public function testQuotesTableNameInListTableConstraintsSQL()
    {
        self::assertContains("'Foo''Bar\\'", $this->_platform->getListTableConstraintsSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesSchemaNameInListTableConstraintsSQL()
    {
        self::assertContains(
            "'Foo''Bar\\'",
            $this->_platform->getListTableConstraintsSQL("Foo'Bar\\.baz_table"),
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
}
