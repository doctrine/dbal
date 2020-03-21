<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Type;
use function array_walk;
use function preg_replace;
use function sprintf;
use function strtoupper;
use function uniqid;

class OraclePlatformTest extends AbstractPlatformTestCase
{
    /**
     * @return mixed[][]
     */
    public static function dataValidIdentifiers() : iterable
    {
        return [
            ['a'],
            ['foo'],
            ['Foo'],
            ['Foo123'],
            ['Foo#bar_baz$'],
            ['"a"'],
            ['"1"'],
            ['"foo_bar"'],
            ['"@$%&!"'],
        ];
    }

    /**
     * @dataProvider dataValidIdentifiers
     */
    public function testValidIdentifiers(string $identifier) : void
    {
        $platform = $this->createPlatform();
        $platform->assertValidIdentifier($identifier);

        $this->addToAssertionCount(1);
    }

    /**
     * @return mixed[][]
     */
    public static function dataInvalidIdentifiers() : iterable
    {
        return [
            ['1'],
            ['abc&'],
            ['abc-def'],
            ['"'],
            ['"foo"bar"'],
        ];
    }

    /**
     * @dataProvider dataInvalidIdentifiers
     */
    public function testInvalidIdentifiers(string $identifier) : void
    {
        $this->expectException(DBALException::class);

        $platform = $this->createPlatform();
        $platform->assertValidIdentifier($identifier);
    }

    public function createPlatform() : AbstractPlatform
    {
        return new OraclePlatform();
    }

    public function getGenerateTableSql() : string
    {
        return 'CREATE TABLE test (id NUMBER(10) NOT NULL, test VARCHAR2(255) DEFAULT NULL NULL, PRIMARY KEY(id))';
    }

    /**
     * @return mixed[]
     */
    public function getGenerateTableWithMultiColumnUniqueIndexSql() : array
    {
        return [
            'CREATE TABLE test (foo VARCHAR2(255) DEFAULT NULL NULL, bar VARCHAR2(255) DEFAULT NULL NULL)',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getGenerateAlterTableSql() : array
    {
        return [
            'ALTER TABLE mytable ADD (quota NUMBER(10) DEFAULT NULL NULL)',
            "ALTER TABLE mytable MODIFY (baz VARCHAR2(255) DEFAULT 'def' NOT NULL, bloo NUMBER(1) DEFAULT '0' NOT NULL)",
            'ALTER TABLE mytable DROP (foo)',
            'ALTER TABLE mytable RENAME TO userlist',
        ];
    }

    public function testRLike() : void
    {
        $this->expectException(DBALException::class);

        self::assertEquals('RLIKE', $this->platform->getRegexpExpression(), 'Regular expression operator is not correct');
    }

    public function testGeneratesSqlSnippets() : void
    {
        self::assertEquals('"', $this->platform->getIdentifierQuoteCharacter(), 'Identifier quote character is not correct');
        self::assertEquals('column1 || column2 || column3', $this->platform->getConcatExpression('column1', 'column2', 'column3'), 'Concatenation expression is not correct');
    }

    public function testGeneratesTransactionsCommands() : void
    {
        self::assertEquals(
            'SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_UNCOMMITTED)
        );
        self::assertEquals(
            'SET TRANSACTION ISOLATION LEVEL READ COMMITTED',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_COMMITTED)
        );
        self::assertEquals(
            'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::REPEATABLE_READ)
        );
        self::assertEquals(
            'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::SERIALIZABLE)
        );
    }

    public function testCreateDatabaseThrowsException() : void
    {
        $this->expectException(DBALException::class);

        self::assertEquals('CREATE DATABASE foobar', $this->platform->getCreateDatabaseSQL('foobar'));
    }

    public function testDropDatabaseThrowsException() : void
    {
        self::assertEquals('DROP USER foobar CASCADE', $this->platform->getDropDatabaseSQL('foobar'));
    }

    public function testDropTable() : void
    {
        self::assertEquals('DROP TABLE foobar', $this->platform->getDropTableSQL('foobar'));
    }

    public function testGeneratesTypeDeclarationForIntegers() : void
    {
        self::assertEquals(
            'NUMBER(10)',
            $this->platform->getIntegerTypeDeclarationSQL([])
        );
        self::assertEquals(
            'NUMBER(10)',
            $this->platform->getIntegerTypeDeclarationSQL(['autoincrement' => true])
        );
        self::assertEquals(
            'NUMBER(10)',
            $this->platform->getIntegerTypeDeclarationSQL(
                ['autoincrement' => true, 'primary' => true]
            )
        );
    }

    public function testGeneratesTypeDeclarationsForStrings() : void
    {
        self::assertEquals(
            'CHAR(10)',
            $this->platform->getVarcharTypeDeclarationSQL(
                ['length' => 10, 'fixed' => true]
            )
        );
        self::assertEquals(
            'VARCHAR2(50)',
            $this->platform->getVarcharTypeDeclarationSQL(['length' => 50]),
            'Variable string declaration is not correct'
        );
        self::assertEquals(
            'VARCHAR2(255)',
            $this->platform->getVarcharTypeDeclarationSQL([]),
            'Long string declaration is not correct'
        );
    }

    public function testPrefersIdentityColumns() : void
    {
        self::assertFalse($this->platform->prefersIdentityColumns());
    }

    public function testSupportsIdentityColumns() : void
    {
        self::assertFalse($this->platform->supportsIdentityColumns());
    }

    public function testSupportsSavePoints() : void
    {
        self::assertTrue($this->platform->supportsSavepoints());
    }

    /**
     * {@inheritdoc}
     */
    protected function supportsCommentOnStatement() : bool
    {
        return true;
    }

    public function getGenerateIndexSql() : string
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateUniqueIndexSql() : string
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    public function getGenerateForeignKeySql() : string
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
    }

    /**
     * @param mixed[] $options
     *
     * @group DBAL-1097
     * @dataProvider getGeneratesAdvancedForeignKeyOptionsSQLData
     */
    public function testGeneratesAdvancedForeignKeyOptionsSQL(array $options, string $expectedSql) : void
    {
        $foreignKey = new ForeignKeyConstraint(['foo'], 'foreign_table', ['bar'], null, $options);

        self::assertSame($expectedSql, $this->platform->getAdvancedForeignKeyOptionsSQL($foreignKey));
    }

    /**
     * @return mixed[][]
     */
    public static function getGeneratesAdvancedForeignKeyOptionsSQLData() : iterable
    {
        return [
            [[], ''],
            [['onUpdate' => 'CASCADE'], ''],
            [['onDelete' => 'CASCADE'], ' ON DELETE CASCADE'],
            [['onDelete' => 'NO ACTION'], ''],
            [['onDelete' => 'RESTRICT'], ''],
            [['onUpdate' => 'SET NULL', 'onDelete' => 'SET NULL'], ' ON DELETE SET NULL'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getReturnsForeignKeyReferentialActionSQL() : iterable
    {
        return [
            ['CASCADE', 'CASCADE'],
            ['SET NULL', 'SET NULL'],
            ['NO ACTION', ''],
            ['RESTRICT', ''],
            ['CaScAdE', 'CASCADE'],
        ];
    }

    public function testModifyLimitQuery() : void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        self::assertEquals('SELECT a.* FROM (SELECT * FROM user) a WHERE ROWNUM <= 10', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset() : void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10);
        self::assertEquals('SELECT a.* FROM (SELECT * FROM user) a WHERE ROWNUM <= 10', $sql);
    }

    public function testModifyLimitQueryWithNonEmptyOffset() : void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10, 10);
        self::assertEquals('SELECT * FROM (SELECT a.*, ROWNUM AS doctrine_rownum FROM (SELECT * FROM user) a WHERE ROWNUM <= 20) WHERE doctrine_rownum >= 11', $sql);
    }

    public function testModifyLimitQueryWithEmptyLimit() : void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', null, 10);
        self::assertEquals('SELECT * FROM (SELECT a.*, ROWNUM AS doctrine_rownum FROM (SELECT * FROM user) a) WHERE doctrine_rownum >= 11', $sql);
    }

    public function testModifyLimitQueryWithAscOrderBy() : void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user ORDER BY username ASC', 10);
        self::assertEquals('SELECT a.* FROM (SELECT * FROM user ORDER BY username ASC) a WHERE ROWNUM <= 10', $sql);
    }

    public function testModifyLimitQueryWithDescOrderBy() : void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10);
        self::assertEquals('SELECT a.* FROM (SELECT * FROM user ORDER BY username DESC) a WHERE ROWNUM <= 10', $sql);
    }

    public function testGenerateTableWithAutoincrement() : void
    {
        $columnName = strtoupper('id' . uniqid());
        $tableName  = strtoupper('table' . uniqid());
        $table      = new Table($tableName);
        $column     = $table->addColumn($columnName, 'integer');
        $column->setAutoincrement(true);
        $targets    = [
            sprintf('CREATE TABLE %s (%s NUMBER(10) NOT NULL)', $tableName, $columnName),
            sprintf(
                "DECLARE constraints_Count NUMBER; BEGIN SELECT COUNT(CONSTRAINT_NAME) INTO constraints_Count FROM USER_CONSTRAINTS WHERE TABLE_NAME = '%s' AND CONSTRAINT_TYPE = 'P'; IF constraints_Count = 0 OR constraints_Count = '' THEN EXECUTE IMMEDIATE 'ALTER TABLE %s ADD CONSTRAINT %s_AI_PK PRIMARY KEY (%s)'; END IF; END;",
                $tableName,
                $tableName,
                $tableName,
                $columnName
            ),
            sprintf('CREATE SEQUENCE %s_SEQ START WITH 1 MINVALUE 1 INCREMENT BY 1', $tableName),
            sprintf(
                "CREATE TRIGGER %s_AI_PK BEFORE INSERT ON %s FOR EACH ROW DECLARE last_Sequence NUMBER; last_InsertID NUMBER; BEGIN SELECT %s_SEQ.NEXTVAL INTO :NEW.%s FROM DUAL; IF (:NEW.%s IS NULL OR :NEW.%s = 0) THEN SELECT %s_SEQ.NEXTVAL INTO :NEW.%s FROM DUAL; ELSE SELECT NVL(Last_Number, 0) INTO last_Sequence FROM User_Sequences WHERE Sequence_Name = '%s_SEQ'; SELECT :NEW.%s INTO last_InsertID FROM DUAL; WHILE (last_InsertID > last_Sequence) LOOP SELECT %s_SEQ.NEXTVAL INTO last_Sequence FROM DUAL; END LOOP; END IF; END;",
                $tableName,
                $tableName,
                $tableName,
                $columnName,
                $columnName,
                $columnName,
                $tableName,
                $columnName,
                $tableName,
                $columnName,
                $tableName
            ),
        ];
        $statements = $this->platform->getCreateTableSQL($table);
        //strip all the whitespace from the statements
        array_walk($statements, static function (&$value) : void {
            $value = preg_replace('/\s+/', ' ', $value);
        });
        foreach ($targets as $key => $sql) {
            self::assertArrayHasKey($key, $statements);
            self::assertEquals($sql, $statements[$key]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTableColumnCommentsSQL() : array
    {
        return [
            'CREATE TABLE test (id NUMBER(10) NOT NULL, PRIMARY KEY(id))',
            "COMMENT ON COLUMN test.id IS 'This is a comment'",
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTableColumnTypeCommentsSQL() : array
    {
        return [
            'CREATE TABLE test (id NUMBER(10) NOT NULL, data CLOB NOT NULL, PRIMARY KEY(id))',
            "COMMENT ON COLUMN test.data IS '(DC2Type:array)'",
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableColumnCommentsSQL() : array
    {
        return [
            'ALTER TABLE mytable ADD (quota NUMBER(10) NOT NULL)',
            "COMMENT ON COLUMN mytable.quota IS 'A comment'",
            "COMMENT ON COLUMN mytable.foo IS ''",
            "COMMENT ON COLUMN mytable.baz IS 'B comment'",
        ];
    }

    public function getBitAndComparisonExpressionSql(string $value1, string $value2) : string
    {
        return 'BITAND(' . $value1 . ', ' . $value2 . ')';
    }

    public function getBitOrComparisonExpressionSql(string $value1, string $value2) : string
    {
        return '(' . $value1 . '-' .
        $this->getBitAndComparisonExpressionSql($value1, $value2)
        . '+' . $value2 . ')';
    }

    /**
     * @return mixed[]
     */
    protected function getQuotedColumnInPrimaryKeySQL() : array
    {
        return ['CREATE TABLE "quoted" ("create" VARCHAR2(255) NOT NULL, PRIMARY KEY("create"))'];
    }

    /**
     * @return mixed[]
     */
    protected function getQuotedColumnInIndexSQL() : array
    {
        return [
            'CREATE TABLE "quoted" ("create" VARCHAR2(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB ON "quoted" ("create")',
        ];
    }

    /**
     * @return mixed[]
     */
    protected function getQuotedNameInIndexSQL() : array
    {
        return [
            'CREATE TABLE test (column1 VARCHAR2(255) NOT NULL)',
            'CREATE INDEX "key" ON test (column1)',
        ];
    }

    /**
     * @return mixed[]
     */
    protected function getQuotedColumnInForeignKeySQL() : array
    {
        return [
            'CREATE TABLE "quoted" ("create" VARCHAR2(255) NOT NULL, foo VARCHAR2(255) NOT NULL, "bar" VARCHAR2(255) NOT NULL)',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES foreign ("create", bar, "foo-bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES foo ("create", bar, "foo-bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar") REFERENCES "foo-bar" ("create", bar, "foo-bar")',
        ];
    }

    /**
     * @group DBAL-472
     * @group DBAL-1001
     */
    public function testAlterTableNotNULL() : void
    {
        $tableDiff                          = new TableDiff('mytable');
        $tableDiff->changedColumns['foo']   = new ColumnDiff(
            'foo',
            new Column(
                'foo',
                Type::getType('string'),
                ['default' => 'bla', 'notnull' => true]
            ),
            ['type']
        );
        $tableDiff->changedColumns['bar']   = new ColumnDiff(
            'bar',
            new Column(
                'baz',
                Type::getType('string'),
                ['default' => 'bla', 'notnull' => true]
            ),
            ['type', 'notnull']
        );
        $tableDiff->changedColumns['metar'] = new ColumnDiff(
            'metar',
            new Column(
                'metar',
                Type::getType('string'),
                ['length' => 2000, 'notnull' => false]
            ),
            ['notnull']
        );

        $expectedSql = ["ALTER TABLE mytable MODIFY (foo VARCHAR2(255) DEFAULT 'bla', baz VARCHAR2(255) DEFAULT 'bla' NOT NULL, metar VARCHAR2(2000) DEFAULT NULL NULL)"];
        self::assertEquals($expectedSql, $this->platform->getAlterTableSQL($tableDiff));
    }

    /**
     * @group DBAL-2555
     */
    public function testInitializesDoctrineTypeMappings() : void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('long raw'));
        self::assertSame('blob', $this->platform->getDoctrineTypeMapping('long raw'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('raw'));
        self::assertSame('binary', $this->platform->getDoctrineTypeMapping('raw'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('date'));
        self::assertSame('date', $this->platform->getDoctrineTypeMapping('date'));
    }

    protected function getBinaryMaxLength() : int
    {
        return 2000;
    }

    public function testReturnsBinaryTypeDeclarationSQL() : void
    {
        self::assertSame('RAW(255)', $this->platform->getBinaryTypeDeclarationSQL([]));
        self::assertSame('RAW(2000)', $this->platform->getBinaryTypeDeclarationSQL(['length' => 0]));
        self::assertSame('RAW(2000)', $this->platform->getBinaryTypeDeclarationSQL(['length' => 2000]));

        self::assertSame('RAW(255)', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true]));
        self::assertSame('RAW(2000)', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 0]));
        self::assertSame('RAW(2000)', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 2000]));
    }

    /**
     * @group legacy
     * @expectedDeprecation Binary field length 2001 is greater than supported by the platform (2000). Reduce the field length or use a BLOB field instead.
     */
    public function testReturnsBinaryTypeLongerThanMaxDeclarationSQL() : void
    {
        self::assertSame('BLOB', $this->platform->getBinaryTypeDeclarationSQL(['length' => 2001]));
        self::assertSame('BLOB', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 2001]));
    }

    public function testDoesNotPropagateUnnecessaryTableAlterationOnBinaryType() : void
    {
        $table1 = new Table('mytable');
        $table1->addColumn('column_varbinary', 'binary');
        $table1->addColumn('column_binary', 'binary', ['fixed' => true]);

        $table2 = new Table('mytable');
        $table2->addColumn('column_varbinary', 'binary', ['fixed' => true]);
        $table2->addColumn('column_binary', 'binary');

        $comparator = new Comparator();

        // VARBINARY -> BINARY
        // BINARY    -> VARBINARY
        self::assertEmpty($this->platform->getAlterTableSQL($comparator->diffTable($table1, $table2)));
    }

    /**
     * @group DBAL-563
     */
    public function testUsesSequenceEmulatedIdentityColumns() : void
    {
        self::assertTrue($this->platform->usesSequenceEmulatedIdentityColumns());
    }

    /**
     * @group DBAL-563
     * @group DBAL-831
     */
    public function testReturnsIdentitySequenceName() : void
    {
        self::assertSame('MYTABLE_SEQ', $this->platform->getIdentitySequenceName('mytable', 'mycolumn'));
        self::assertSame('"mytable_SEQ"', $this->platform->getIdentitySequenceName('"mytable"', 'mycolumn'));
        self::assertSame('MYTABLE_SEQ', $this->platform->getIdentitySequenceName('mytable', '"mycolumn"'));
        self::assertSame('"mytable_SEQ"', $this->platform->getIdentitySequenceName('"mytable"', '"mycolumn"'));
    }

    /**
     * @dataProvider dataCreateSequenceWithCache
     * @group DBAL-139
     */
    public function testCreateSequenceWithCache(int $cacheSize, string $expectedSql) : void
    {
        $sequence = new Sequence('foo', 1, 1, $cacheSize);
        self::assertStringContainsString($expectedSql, $this->platform->getCreateSequenceSQL($sequence));
    }

    /**
     * @return mixed[][]
     */
    public static function dataCreateSequenceWithCache() : iterable
    {
        return [
            [1, 'NOCACHE'],
            [0, 'NOCACHE'],
            [3, 'CACHE 3'],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @group DBAL-234
     */
    protected function getAlterTableRenameIndexSQL() : array
    {
        return ['ALTER INDEX idx_foo RENAME TO idx_bar'];
    }

    /**
     * {@inheritDoc}
     *
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexSQL() : array
    {
        return [
            'ALTER INDEX "create" RENAME TO "select"',
            'ALTER INDEX "foo" RENAME TO "bar"',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableRenameColumnSQL() : array
    {
        return [
            'ALTER TABLE mytable RENAME COLUMN unquoted1 TO unquoted',
            'ALTER TABLE mytable RENAME COLUMN unquoted2 TO "where"',
            'ALTER TABLE mytable RENAME COLUMN unquoted3 TO "foo"',
            'ALTER TABLE mytable RENAME COLUMN "create" TO reserved_keyword',
            'ALTER TABLE mytable RENAME COLUMN "table" TO "from"',
            'ALTER TABLE mytable RENAME COLUMN "select" TO "bar"',
            'ALTER TABLE mytable RENAME COLUMN quoted1 TO quoted',
            'ALTER TABLE mytable RENAME COLUMN quoted2 TO "and"',
            'ALTER TABLE mytable RENAME COLUMN quoted3 TO "baz"',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableChangeColumnLengthSQL() : array
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    /**
     * {@inheritDoc}
     *
     * @group DBAL-807
     */
    protected function getAlterTableRenameIndexInSchemaSQL() : array
    {
        return ['ALTER INDEX myschema.idx_foo RENAME TO idx_bar'];
    }

    /**
     * {@inheritDoc}
     *
     * @group DBAL-807
     */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL() : array
    {
        return [
            'ALTER INDEX "schema"."create" RENAME TO "select"',
            'ALTER INDEX "schema"."foo" RENAME TO "bar"',
        ];
    }

    protected function getQuotesDropForeignKeySQL() : string
    {
        return 'ALTER TABLE "table" DROP CONSTRAINT "select"';
    }

    /**
     * @group DBAL-423
     */
    public function testReturnsGuidTypeDeclarationSQL() : void
    {
        self::assertSame('CHAR(36)', $this->platform->getGuidTypeDeclarationSQL([]));
    }

    /**
     * {@inheritdoc}
     */
    public function getAlterTableRenameColumnSQL() : array
    {
        return ['ALTER TABLE foo RENAME COLUMN bar TO baz'];
    }

    /**
     * @param string[] $expectedSql
     *
     * @dataProvider getReturnsDropAutoincrementSQL
     * @group DBAL-831
     */
    public function testReturnsDropAutoincrementSQL(string $table, array $expectedSql) : void
    {
        self::assertSame($expectedSql, $this->platform->getDropAutoincrementSql($table));
    }

    /**
     * @return mixed[][]
     */
    public static function getReturnsDropAutoincrementSQL() : iterable
    {
        return [
            [
                'myTable',
                [
                    'DROP TRIGGER MYTABLE_AI_PK',
                    'DROP SEQUENCE MYTABLE_SEQ',
                    'ALTER TABLE MYTABLE DROP CONSTRAINT MYTABLE_AI_PK',
                ],
            ],
            [
                '"myTable"',
                [
                    'DROP TRIGGER "myTable_AI_PK"',
                    'DROP SEQUENCE "myTable_SEQ"',
                    'ALTER TABLE "myTable" DROP CONSTRAINT "myTable_AI_PK"',
                ],
            ],
            [
                'table',
                [
                    'DROP TRIGGER TABLE_AI_PK',
                    'DROP SEQUENCE TABLE_SEQ',
                    'ALTER TABLE "TABLE" DROP CONSTRAINT TABLE_AI_PK',
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesTableIdentifiersInAlterTableSQL() : array
    {
        return [
            'ALTER TABLE "foo" DROP CONSTRAINT fk1',
            'ALTER TABLE "foo" DROP CONSTRAINT fk2',
            'ALTER TABLE "foo" ADD (bloo NUMBER(10) NOT NULL)',
            'ALTER TABLE "foo" MODIFY (bar NUMBER(10) DEFAULT NULL NULL)',
            'ALTER TABLE "foo" RENAME COLUMN id TO war',
            'ALTER TABLE "foo" DROP (baz)',
            'ALTER TABLE "foo" RENAME TO "table"',
            'ALTER TABLE "table" ADD CONSTRAINT fk_add FOREIGN KEY (fk3) REFERENCES fk_table (id)',
            'ALTER TABLE "table" ADD CONSTRAINT fk2 FOREIGN KEY (fk2) REFERENCES fk_table2 (id)',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommentOnColumnSQL() : array
    {
        return [
            'COMMENT ON COLUMN foo.bar IS \'comment\'',
            'COMMENT ON COLUMN "Foo"."BAR" IS \'comment\'',
            'COMMENT ON COLUMN "select"."from" IS \'comment\'',
        ];
    }

    /**
     * @group DBAL-1004
     */
    public function testAltersTableColumnCommentWithExplicitlyQuotedIdentifiers() : void
    {
        $table1 = new Table('"foo"', [new Column('"bar"', Type::getType('integer'))]);
        $table2 = new Table('"foo"', [new Column('"bar"', Type::getType('integer'), ['comment' => 'baz'])]);

        $comparator = new Comparator();

        $tableDiff = $comparator->diffTable($table1, $table2);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertSame(
            ['COMMENT ON COLUMN "foo"."bar" IS \'baz\''],
            $this->platform->getAlterTableSQL($tableDiff)
        );
    }

    public function testQuotedTableNames() : void
    {
        $table = new Table('"test"');
        $table->addColumn('"id"', 'integer', ['autoincrement' => true]);

        // assert tabel
        self::assertTrue($table->isQuoted());
        self::assertEquals('test', $table->getName());
        self::assertEquals('"test"', $table->getQuotedName($this->platform));

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals('CREATE TABLE "test" ("id" NUMBER(10) NOT NULL)', $sql[0]);
        self::assertEquals('CREATE SEQUENCE "test_SEQ" START WITH 1 MINVALUE 1 INCREMENT BY 1', $sql[2]);
        $createTriggerStatement = <<<EOD
CREATE TRIGGER "test_AI_PK"
   BEFORE INSERT
   ON "test"
   FOR EACH ROW
DECLARE
   last_Sequence NUMBER;
   last_InsertID NUMBER;
BEGIN
   SELECT "test_SEQ".NEXTVAL INTO :NEW."id" FROM DUAL;
   IF (:NEW."id" IS NULL OR :NEW."id" = 0) THEN
      SELECT "test_SEQ".NEXTVAL INTO :NEW."id" FROM DUAL;
   ELSE
      SELECT NVL(Last_Number, 0) INTO last_Sequence
        FROM User_Sequences
       WHERE Sequence_Name = 'test_SEQ';
      SELECT :NEW."id" INTO last_InsertID FROM DUAL;
      WHILE (last_InsertID > last_Sequence) LOOP
         SELECT "test_SEQ".NEXTVAL INTO last_Sequence FROM DUAL;
      END LOOP;
   END IF;
END;
EOD;

        self::assertEquals($createTriggerStatement, $sql[3]);
    }

    /**
     * @dataProvider getReturnsGetListTableColumnsSQL
     * @group DBAL-831
     */
    public function testReturnsGetListTableColumnsSQL(?string $database, string $expectedSql) : void
    {
        // note: this assertion is a bit strict, as it compares a full SQL string.
        // Should this break in future, then please try to reduce the matching to substring matching while reworking
        // the tests
        self::assertEquals($expectedSql, $this->platform->getListTableColumnsSQL('"test"', $database));
    }

    /**
     * @return mixed[][]
     */
    public static function getReturnsGetListTableColumnsSQL() : iterable
    {
        return [
            [
                null,
                <<<'SQL'
SELECT   c.*,
         (
             SELECT d.comments
             FROM   user_col_comments d
             WHERE  d.TABLE_NAME = c.TABLE_NAME
             AND    d.COLUMN_NAME = c.COLUMN_NAME
         ) AS comments
FROM     user_tab_columns c
WHERE    c.table_name = 'test'
ORDER BY c.column_id
SQL
                ,
            ],
            [
                '/',
                <<<'SQL'
SELECT   c.*,
         (
             SELECT d.comments
             FROM   user_col_comments d
             WHERE  d.TABLE_NAME = c.TABLE_NAME
             AND    d.COLUMN_NAME = c.COLUMN_NAME
         ) AS comments
FROM     user_tab_columns c
WHERE    c.table_name = 'test'
ORDER BY c.column_id
SQL
                ,
            ],
            [
                'scott',
                <<<'SQL'
SELECT   c.*,
         (
             SELECT d.comments
             FROM   all_col_comments d
             WHERE  d.TABLE_NAME = c.TABLE_NAME AND d.OWNER = c.OWNER
             AND    d.COLUMN_NAME = c.COLUMN_NAME
         ) AS comments
FROM     all_tab_columns c
WHERE    c.table_name = 'test' AND c.owner = 'SCOTT'
ORDER BY c.column_id
SQL
                ,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL() : string
    {
        return 'CONSTRAINT "select" UNIQUE (foo)';
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInIndexDeclarationSQL() : string
    {
        return 'INDEX "select" (foo)';
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInTruncateTableSQL() : string
    {
        return 'TRUNCATE TABLE "select"';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAlterStringToFixedStringSQL() : array
    {
        return ['ALTER TABLE mytable MODIFY (name CHAR(2) DEFAULT NULL)'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL() : array
    {
        return ['ALTER INDEX idx_foo RENAME TO idx_foo_renamed'];
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesDatabaseNameInListSequencesSQL() : void
    {
        self::assertStringContainsStringIgnoringCase(
            "'Foo''Bar\\'",
            $this->platform->getListSequencesSQL("Foo'Bar\\")
        );
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableIndexesSQL() : void
    {
        self::assertStringContainsStringIgnoringCase(
            "'Foo''Bar\\'",
            $this->platform->getListTableIndexesSQL("Foo'Bar\\")
        );
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableForeignKeysSQL() : void
    {
        self::assertStringContainsStringIgnoringCase(
            "'Foo''Bar\\'",
            $this->platform->getListTableForeignKeysSQL("Foo'Bar\\")
        );
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableConstraintsSQL() : void
    {
        self::assertStringContainsStringIgnoringCase(
            "'Foo''Bar\\'",
            $this->platform->getListTableConstraintsSQL("Foo'Bar\\")
        );
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableColumnsSQL() : void
    {
        self::assertStringContainsStringIgnoringCase(
            "'Foo''Bar\\'",
            $this->platform->getListTableColumnsSQL("Foo'Bar\\")
        );
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesDatabaseNameInListTableColumnsSQL() : void
    {
        self::assertStringContainsStringIgnoringCase(
            "'Foo''Bar\\'",
            $this->platform->getListTableColumnsSQL('foo_table', "Foo'Bar\\")
        );
    }
}
