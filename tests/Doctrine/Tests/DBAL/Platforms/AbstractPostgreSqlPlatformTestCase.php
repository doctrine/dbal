<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Type;

abstract class AbstractPostgreSqlPlatformTestCase extends AbstractPlatformTestCase
{
    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id SERIAL NOT NULL, test VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return array(
            'CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL)',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)'
        );
    }

    public function getGenerateAlterTableSql()
    {
        return array(
            'ALTER TABLE mytable ADD quota INT DEFAULT NULL',
            'ALTER TABLE mytable DROP foo',
            'ALTER TABLE mytable ALTER bar TYPE VARCHAR(255)',
            "ALTER TABLE mytable ALTER bar SET DEFAULT 'def'",
            'ALTER TABLE mytable ALTER bar SET NOT NULL',
            'ALTER TABLE mytable ALTER bloo TYPE BOOLEAN',
            "ALTER TABLE mytable ALTER bloo SET DEFAULT 'false'",
            'ALTER TABLE mytable ALTER bloo SET NOT NULL',
            'ALTER TABLE mytable RENAME TO userlist',
        );
    }

    public function getGenerateIndexSql()
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateForeignKeySql()
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id) NOT DEFERRABLE INITIALLY IMMEDIATE';
    }

    public function testGeneratesForeignKeySqlForNonStandardOptions()
    {
        $foreignKey = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
                array('foreign_id'), 'my_table', array('id'), 'my_fk', array('onDelete' => 'CASCADE')
        );
        self::assertEquals(
            "CONSTRAINT my_fk FOREIGN KEY (foreign_id) REFERENCES my_table (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE",
            $this->_platform->getForeignKeyDeclarationSQL($foreignKey)
        );

        $foreignKey = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
            array('foreign_id'), 'my_table', array('id'), 'my_fk', array('match' => 'full')
        );
        self::assertEquals(
            "CONSTRAINT my_fk FOREIGN KEY (foreign_id) REFERENCES my_table (id) MATCH full NOT DEFERRABLE INITIALLY IMMEDIATE",
            $this->_platform->getForeignKeyDeclarationSQL($foreignKey)
        );

        $foreignKey = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
            array('foreign_id'), 'my_table', array('id'), 'my_fk', array('deferrable' => true)
        );
        self::assertEquals(
            "CONSTRAINT my_fk FOREIGN KEY (foreign_id) REFERENCES my_table (id) DEFERRABLE INITIALLY IMMEDIATE",
            $this->_platform->getForeignKeyDeclarationSQL($foreignKey)
        );

        $foreignKey = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
            array('foreign_id'), 'my_table', array('id'), 'my_fk', array('deferred' => true)
        );
        self::assertEquals(
            "CONSTRAINT my_fk FOREIGN KEY (foreign_id) REFERENCES my_table (id) NOT DEFERRABLE INITIALLY DEFERRED",
            $this->_platform->getForeignKeyDeclarationSQL($foreignKey)
        );

        $foreignKey = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
            array('foreign_id'), 'my_table', array('id'), 'my_fk', array('feferred' => true)
        );
        self::assertEquals(
            "CONSTRAINT my_fk FOREIGN KEY (foreign_id) REFERENCES my_table (id) NOT DEFERRABLE INITIALLY DEFERRED",
            $this->_platform->getForeignKeyDeclarationSQL($foreignKey)
        );

        $foreignKey = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
            array('foreign_id'), 'my_table', array('id'), 'my_fk', array('deferrable' => true, 'deferred' => true, 'match' => 'full')
        );
        self::assertEquals(
            "CONSTRAINT my_fk FOREIGN KEY (foreign_id) REFERENCES my_table (id) MATCH full DEFERRABLE INITIALLY DEFERRED",
            $this->_platform->getForeignKeyDeclarationSQL($foreignKey)
        );
    }

    public function testGeneratesSqlSnippets()
    {
        self::assertEquals('SIMILAR TO', $this->_platform->getRegexpExpression(), 'Regular expression operator is not correct');
        self::assertEquals('"', $this->_platform->getIdentifierQuoteCharacter(), 'Identifier quote character is not correct');
        self::assertEquals('column1 || column2 || column3', $this->_platform->getConcatExpression('column1', 'column2', 'column3'), 'Concatenation expression is not correct');
        self::assertEquals('SUBSTRING(column FROM 5)', $this->_platform->getSubstringExpression('column', 5), 'Substring expression without length is not correct');
        self::assertEquals('SUBSTRING(column FROM 1 FOR 5)', $this->_platform->getSubstringExpression('column', 1, 5), 'Substring expression with length is not correct');
    }

    public function testGeneratesTransactionCommands()
    {
        self::assertEquals(
            'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL READ UNCOMMITTED',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_UNCOMMITTED)
        );
        self::assertEquals(
            'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL READ COMMITTED',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_COMMITTED)
        );
        self::assertEquals(
            'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL REPEATABLE READ',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::REPEATABLE_READ)
        );
        self::assertEquals(
            'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL SERIALIZABLE',
            $this->_platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::SERIALIZABLE)
        );
    }

    public function testGeneratesDDLSnippets()
    {
        self::assertEquals('CREATE DATABASE foobar', $this->_platform->getCreateDatabaseSQL('foobar'));
        self::assertEquals('DROP DATABASE foobar', $this->_platform->getDropDatabaseSQL('foobar'));
        self::assertEquals('DROP TABLE foobar', $this->_platform->getDropTableSQL('foobar'));
    }

    public function testGenerateTableWithAutoincrement()
    {
        $table = new \Doctrine\DBAL\Schema\Table('autoinc_table');
        $column = $table->addColumn('id', 'integer');
        $column->setAutoincrement(true);

        self::assertEquals(array('CREATE TABLE autoinc_table (id SERIAL NOT NULL)'), $this->_platform->getCreateTableSQL($table));
    }

    public static function serialTypes() : array
    {
        return [
            ['integer', 'SERIAL'],
            ['bigint', 'BIGSERIAL'],
        ];
    }

    /**
     * @dataProvider serialTypes
     * @group 2906
     */
    public function testGenerateTableWithAutoincrementDoesNotSetDefault(string $type, string $definition) : void
    {
        $table  = new \Doctrine\DBAL\Schema\Table('autoinc_table_notnull');
        $column = $table->addColumn('id', $type);
        $column->setAutoIncrement(true);
        $column->setNotNull(false);

        $sql = $this->_platform->getCreateTableSQL($table);

        self::assertEquals(["CREATE TABLE autoinc_table_notnull (id $definition)"], $sql);
    }

    /**
     * @dataProvider serialTypes
     * @group 2906
     */
    public function testCreateTableWithAutoincrementAndNotNullAddsConstraint(string $type, string $definition) : void
    {
        $table  = new \Doctrine\DBAL\Schema\Table('autoinc_table_notnull_enabled');
        $column = $table->addColumn('id', $type);
        $column->setAutoIncrement(true);
        $column->setNotNull(true);

        $sql = $this->_platform->getCreateTableSQL($table);

        self::assertEquals(["CREATE TABLE autoinc_table_notnull_enabled (id $definition NOT NULL)"], $sql);
    }

    /**
     * @dataProvider serialTypes
     * @group 2906
     */
    public function testGetDefaultValueDeclarationSQLIgnoresTheDefaultKeyWhenTheFieldIsSerial(string $type) : void
    {
        $sql = $this->_platform->getDefaultValueDeclarationSQL(
            [
                'autoincrement' => true,
                'type'          => Type::getType($type),
                'default'       => 1,
            ]
        );

        self::assertSame('', $sql);
    }

    public function testGeneratesTypeDeclarationForIntegers()
    {
        self::assertEquals(
            'INT',
            $this->_platform->getIntegerTypeDeclarationSQL(array())
        );
        self::assertEquals(
            'SERIAL',
            $this->_platform->getIntegerTypeDeclarationSQL(array('autoincrement' => true)
        ));
        self::assertEquals(
            'SERIAL',
            $this->_platform->getIntegerTypeDeclarationSQL(
                array('autoincrement' => true, 'primary' => true)
        ));
    }

    public function testGeneratesTypeDeclarationForStrings()
    {
        self::assertEquals(
            'CHAR(10)',
            $this->_platform->getVarcharTypeDeclarationSQL(
                array('length' => 10, 'fixed' => true))
        );
        self::assertEquals(
            'VARCHAR(50)',
            $this->_platform->getVarcharTypeDeclarationSQL(array('length' => 50)),
            'Variable string declaration is not correct'
        );
        self::assertEquals(
            'VARCHAR(255)',
            $this->_platform->getVarcharTypeDeclarationSQL(array()),
            'Long string declaration is not correct'
        );
    }

    public function getGenerateUniqueIndexSql()
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    public function testGeneratesSequenceSqlCommands()
    {
        $sequence = new \Doctrine\DBAL\Schema\Sequence('myseq', 20, 1);
        self::assertEquals(
            'CREATE SEQUENCE myseq INCREMENT BY 20 MINVALUE 1 START 1',
            $this->_platform->getCreateSequenceSQL($sequence)
        );
        self::assertEquals(
            'DROP SEQUENCE myseq CASCADE',
            $this->_platform->getDropSequenceSQL('myseq')
        );
        self::assertEquals(
            "SELECT NEXTVAL('myseq')",
            $this->_platform->getSequenceNextValSQL('myseq')
        );
    }

    public function testDoesNotPreferIdentityColumns()
    {
        self::assertFalse($this->_platform->prefersIdentityColumns());
    }

    public function testPrefersSequences()
    {
        self::assertTrue($this->_platform->prefersSequences());
    }

    public function testSupportsIdentityColumns()
    {
        self::assertTrue($this->_platform->supportsIdentityColumns());
    }

    public function testSupportsSavePoints()
    {
        self::assertTrue($this->_platform->supportsSavepoints());
    }

    public function testSupportsSequences()
    {
        self::assertTrue($this->_platform->supportsSequences());
    }

    /**
     * {@inheritdoc}
     */
    protected function supportsCommentOnStatement()
    {
        return true;
    }

    public function testModifyLimitQuery()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        self::assertEquals('SELECT * FROM user LIMIT 10', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10);
        self::assertEquals('SELECT * FROM user LIMIT 10', $sql);
    }

    public function getCreateTableColumnCommentsSQL()
    {
        return array(
            "CREATE TABLE test (id INT NOT NULL, PRIMARY KEY(id))",
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
            "CREATE TABLE test (id INT NOT NULL, data TEXT NOT NULL, PRIMARY KEY(id))",
            "COMMENT ON COLUMN test.data IS '(DC2Type:array)'"
        );
    }

    protected function getQuotedColumnInPrimaryKeySQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, PRIMARY KEY("create"))',
        );
    }

    protected function getQuotedColumnInIndexSQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB ON "quoted" ("create")',
        );
    }

    protected function getQuotedNameInIndexSQL()
    {
        return array(
            'CREATE TABLE test (column1 VARCHAR(255) NOT NULL)',
            'CREATE INDEX "key" ON test (column1)',
        );
    }

    protected function getQuotedColumnInForeignKeySQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, "bar" VARCHAR(255) NOT NULL)',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES "foreign" ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES foo ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar") REFERENCES "foo-bar" ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
    }

    /**
     * @group DBAL-457
     * @dataProvider pgBooleanProvider
     *
     * @param string $databaseValue
     * @param string $preparedStatementValue
     * @param int    $integerValue
     * @param bool   $booleanValue
     */
    public function testConvertBooleanAsLiteralStrings(
        $databaseValue,
        $preparedStatementValue,
        $integerValue,
        $booleanValue
    ) {
        $platform = $this->createPlatform();

        self::assertEquals($preparedStatementValue, $platform->convertBooleans($databaseValue));
    }

    /**
     * @group DBAL-457
     */
    public function testConvertBooleanAsLiteralIntegers()
    {
        $platform = $this->createPlatform();
        $platform->setUseBooleanTrueFalseStrings(false);

        self::assertEquals(1, $platform->convertBooleans(true));
        self::assertEquals(1, $platform->convertBooleans('1'));

        self::assertEquals(0, $platform->convertBooleans(false));
        self::assertEquals(0, $platform->convertBooleans('0'));
    }

    /**
     * @group DBAL-630
     * @dataProvider pgBooleanProvider
     *
     * @param string $databaseValue
     * @param string $preparedStatementValue
     * @param int    $integerValue
     * @param bool   $booleanValue
     */
    public function testConvertBooleanAsDatabaseValueStrings(
        $databaseValue,
        $preparedStatementValue,
        $integerValue,
        $booleanValue
    )
    {
        $platform = $this->createPlatform();

        self::assertSame($integerValue, $platform->convertBooleansToDatabaseValue($booleanValue));
    }

    /**
     * @group DBAL-630
     */
    public function testConvertBooleanAsDatabaseValueIntegers()
    {
        $platform = $this->createPlatform();
        $platform->setUseBooleanTrueFalseStrings(false);

        self::assertSame(1, $platform->convertBooleansToDatabaseValue(true));
        self::assertSame(0, $platform->convertBooleansToDatabaseValue(false));
    }

    /**
     * @dataProvider pgBooleanProvider
     *
     * @param string $databaseValue
     * @param string $prepareStatementValue
     * @param int    $integerValue
     * @param bool   $booleanValue
     */
    public function testConvertFromBoolean($databaseValue, $prepareStatementValue, $integerValue, $booleanValue)
    {
        $platform = $this->createPlatform();

        self::assertSame($booleanValue, $platform->convertFromBoolean($databaseValue));
    }

    /**
     * @expectedException        UnexpectedValueException
     * @expectedExceptionMessage Unrecognized boolean literal 'my-bool'
     */
    public function testThrowsExceptionWithInvalidBooleanLiteral()
    {
        $platform = $this->createPlatform()->convertBooleansToDatabaseValue("my-bool");
    }

    public function testGetCreateSchemaSQL()
    {
        $schemaName = 'schema';
        $sql = $this->_platform->getCreateSchemaSQL($schemaName);
        self::assertEquals('CREATE SCHEMA ' . $schemaName, $sql);
    }

    public function testAlterDecimalPrecisionScale()
    {

        $table = new Table('mytable');
        $table->addColumn('dfoo1', 'decimal');
        $table->addColumn('dfoo2', 'decimal', array('precision' => 10, 'scale' => 6));
        $table->addColumn('dfoo3', 'decimal', array('precision' => 10, 'scale' => 6));
        $table->addColumn('dfoo4', 'decimal', array('precision' => 10, 'scale' => 6));

        $tableDiff = new TableDiff('mytable');
        $tableDiff->fromTable = $table;

        $tableDiff->changedColumns['dloo1'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'dloo1', new \Doctrine\DBAL\Schema\Column(
                'dloo1', \Doctrine\DBAL\Types\Type::getType('decimal'), array('precision' => 16, 'scale' => 6)
            ),
            array('precision')
        );
        $tableDiff->changedColumns['dloo2'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'dloo2', new \Doctrine\DBAL\Schema\Column(
                'dloo2', \Doctrine\DBAL\Types\Type::getType('decimal'), array('precision' => 10, 'scale' => 4)
            ),
            array('scale')
        );
        $tableDiff->changedColumns['dloo3'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'dloo3', new \Doctrine\DBAL\Schema\Column(
                'dloo3', \Doctrine\DBAL\Types\Type::getType('decimal'), array('precision' => 10, 'scale' => 6)
            ),
            array()
        );
        $tableDiff->changedColumns['dloo4'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'dloo4', new \Doctrine\DBAL\Schema\Column(
                'dloo4', \Doctrine\DBAL\Types\Type::getType('decimal'), array('precision' => 16, 'scale' => 8)
            ),
            array('precision', 'scale')
        );

        $sql = $this->_platform->getAlterTableSQL($tableDiff);

        $expectedSql = array(
            'ALTER TABLE mytable ALTER dloo1 TYPE NUMERIC(16, 6)',
            'ALTER TABLE mytable ALTER dloo2 TYPE NUMERIC(10, 4)',
            'ALTER TABLE mytable ALTER dloo4 TYPE NUMERIC(16, 8)',
        );

        self::assertEquals($expectedSql, $sql);
    }

    /**
     * @group DBAL-365
     */
    public function testDroppingConstraintsBeforeColumns()
    {
        $newTable = new Table('mytable');
        $newTable->addColumn('id', 'integer');
        $newTable->setPrimaryKey(array('id'));

        $oldTable = clone $newTable;
        $oldTable->addColumn('parent_id', 'integer');
        $oldTable->addUnnamedForeignKeyConstraint('mytable', array('parent_id'), array('id'));

        $comparator = new \Doctrine\DBAL\Schema\Comparator();
        $tableDiff = $comparator->diffTable($oldTable, $newTable);

        $sql = $this->_platform->getAlterTableSQL($tableDiff);

        $expectedSql = array(
            'ALTER TABLE mytable DROP CONSTRAINT FK_6B2BD609727ACA70',
            'DROP INDEX IDX_6B2BD609727ACA70',
            'ALTER TABLE mytable DROP parent_id',
        );

        self::assertEquals($expectedSql, $sql);
    }

    /**
     * @group DBAL-563
     */
    public function testUsesSequenceEmulatedIdentityColumns()
    {
        self::assertTrue($this->_platform->usesSequenceEmulatedIdentityColumns());
    }

    /**
     * @group DBAL-563
     */
    public function testReturnsIdentitySequenceName()
    {
        self::assertSame('mytable_mycolumn_seq', $this->_platform->getIdentitySequenceName('mytable', 'mycolumn'));
    }

    /**
     * @dataProvider dataCreateSequenceWithCache
     * @group DBAL-139
     */
    public function testCreateSequenceWithCache($cacheSize, $expectedSql)
    {
        $sequence = new \Doctrine\DBAL\Schema\Sequence('foo', 1, 1, $cacheSize);
        self::assertContains($expectedSql, $this->_platform->getCreateSequenceSQL($sequence));
    }

    public function dataCreateSequenceWithCache()
    {
        return array(
            array(3, 'CACHE 3')
        );
    }

    protected function getBinaryDefaultLength()
    {
        return 0;
    }

    protected function getBinaryMaxLength()
    {
        return 0;
    }

    public function testReturnsBinaryTypeDeclarationSQL()
    {
        self::assertSame('BYTEA', $this->_platform->getBinaryTypeDeclarationSQL(array()));
        self::assertSame('BYTEA', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 0)));
        self::assertSame('BYTEA', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 9999999)));

        self::assertSame('BYTEA', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true)));
        self::assertSame('BYTEA', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 0)));
        self::assertSame('BYTEA', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 9999999)));
    }

    public function testDoesNotPropagateUnnecessaryTableAlterationOnBinaryType()
    {
        $table1 = new Table('mytable');
        $table1->addColumn('column_varbinary', 'binary');
        $table1->addColumn('column_binary', 'binary', array('fixed' => true));
        $table1->addColumn('column_blob', 'blob');

        $table2 = new Table('mytable');
        $table2->addColumn('column_varbinary', 'binary', array('fixed' => true));
        $table2->addColumn('column_binary', 'binary');
        $table2->addColumn('column_blob', 'binary');

        $comparator = new Comparator();

        // VARBINARY -> BINARY
        // BINARY    -> VARBINARY
        // BLOB      -> VARBINARY
        self::assertEmpty($this->_platform->getAlterTableSQL($comparator->diffTable($table1, $table2)));

        $table2 = new Table('mytable');
        $table2->addColumn('column_varbinary', 'binary', array('length' => 42));
        $table2->addColumn('column_binary', 'blob');
        $table2->addColumn('column_blob', 'binary', array('length' => 11, 'fixed' => true));

        // VARBINARY -> VARBINARY with changed length
        // BINARY    -> BLOB
        // BLOB      -> BINARY
        self::assertEmpty($this->_platform->getAlterTableSQL($comparator->diffTable($table1, $table2)));

        $table2 = new Table('mytable');
        $table2->addColumn('column_varbinary', 'blob');
        $table2->addColumn('column_binary', 'binary', array('length' => 42, 'fixed' => true));
        $table2->addColumn('column_blob', 'blob');

        // VARBINARY -> BLOB
        // BINARY    -> BINARY with changed length
        // BLOB      -> BLOB
        self::assertEmpty($this->_platform->getAlterTableSQL($comparator->diffTable($table1, $table2)));
    }

    /**
     * @group DBAL-234
     */
    protected function getAlterTableRenameIndexSQL()
    {
        return array(
            'ALTER INDEX idx_foo RENAME TO idx_bar',
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexSQL()
    {
        return array(
            'ALTER INDEX "create" RENAME TO "select"',
            'ALTER INDEX "foo" RENAME TO "bar"',
        );
    }

    /**
     * PostgreSQL boolean strings provider
     * @return array
     */
    public function pgBooleanProvider()
    {
        return array(
            // Database value, prepared statement value, boolean integer value, boolean value.
            array(true, 'true', 1, true),
            array('t', 'true', 1, true),
            array('true', 'true', 1, true),
            array('y', 'true', 1, true),
            array('yes', 'true', 1, true),
            array('on', 'true', 1, true),
            array('1', 'true', 1, true),

            array(false, 'false', 0, false),
            array('f', 'false', 0, false),
            array('false', 'false', 0, false),
            array( 'n', 'false', 0, false),
            array('no', 'false', 0, false),
            array('off', 'false', 0, false),
            array('0', 'false', 0, false),

            array(null, 'NULL', null, null)
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableRenameColumnSQL()
    {
        return array(
            'ALTER TABLE mytable RENAME COLUMN unquoted1 TO unquoted',
            'ALTER TABLE mytable RENAME COLUMN unquoted2 TO "where"',
            'ALTER TABLE mytable RENAME COLUMN unquoted3 TO "foo"',
            'ALTER TABLE mytable RENAME COLUMN "create" TO reserved_keyword',
            'ALTER TABLE mytable RENAME COLUMN "table" TO "from"',
            'ALTER TABLE mytable RENAME COLUMN "select" TO "bar"',
            'ALTER TABLE mytable RENAME COLUMN quoted1 TO quoted',
            'ALTER TABLE mytable RENAME COLUMN quoted2 TO "and"',
            'ALTER TABLE mytable RENAME COLUMN quoted3 TO "baz"',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableChangeColumnLengthSQL()
    {
        return array(
            'ALTER TABLE mytable ALTER unquoted1 TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER unquoted2 TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER unquoted3 TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER "create" TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER "table" TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER "select" TYPE VARCHAR(255)',
        );
    }

    /**
     * @group DBAL-807
     */
    protected function getAlterTableRenameIndexInSchemaSQL()
    {
        return array(
            'ALTER INDEX myschema.idx_foo RENAME TO idx_bar',
        );
    }

    /**
     * @group DBAL-807
     */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL()
    {
        return array(
            'ALTER INDEX "schema"."create" RENAME TO "select"',
            'ALTER INDEX "schema"."foo" RENAME TO "bar"',
        );
    }

    protected function getQuotesDropForeignKeySQL()
    {
        return 'ALTER TABLE "table" DROP CONSTRAINT "select"';
    }

    public function testGetNullCommentOnColumnSQL()
    {
        self::assertEquals(
            "COMMENT ON COLUMN mytable.id IS NULL",
            $this->_platform->getCommentOnColumnSQL('mytable', 'id', null)
        );
    }

    /**
     * @group DBAL-423
     */
    public function testReturnsGuidTypeDeclarationSQL()
    {
        self::assertSame('UUID', $this->_platform->getGuidTypeDeclarationSQL(array()));
    }

    /**
     * {@inheritdoc}
     */
    public function getAlterTableRenameColumnSQL()
    {
        return array(
            'ALTER TABLE foo RENAME COLUMN bar TO baz',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesTableIdentifiersInAlterTableSQL()
    {
        return array(
            'ALTER TABLE "foo" DROP CONSTRAINT fk1',
            'ALTER TABLE "foo" DROP CONSTRAINT fk2',
            'ALTER TABLE "foo" ADD bloo INT NOT NULL',
            'ALTER TABLE "foo" DROP baz',
            'ALTER TABLE "foo" ALTER bar DROP NOT NULL',
            'ALTER TABLE "foo" RENAME COLUMN id TO war',
            'ALTER TABLE "foo" RENAME TO "table"',
            'ALTER TABLE "table" ADD CONSTRAINT fk_add FOREIGN KEY (fk3) REFERENCES fk_table (id) NOT DEFERRABLE ' .
            'INITIALLY IMMEDIATE',
            'ALTER TABLE "table" ADD CONSTRAINT fk2 FOREIGN KEY (fk2) REFERENCES fk_table2 (id) NOT DEFERRABLE ' .
            'INITIALLY IMMEDIATE',
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
    protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL()
    {
        return 'CONSTRAINT "select" UNIQUE (foo)';
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInIndexDeclarationSQL()
    {
        return 'INDEX "select" (foo)';
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInTruncateTableSQL()
    {
        return 'TRUNCATE "select"';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAlterStringToFixedStringSQL()
    {
        return array(
            'ALTER TABLE mytable ALTER name TYPE CHAR(2)',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL()
    {
        return array(
            'ALTER INDEX idx_foo RENAME TO idx_foo_renamed',
        );
    }

    /**
     * @group DBAL-1142
     */
    public function testInitializesTsvectorTypeMapping()
    {
        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('tsvector'));
        self::assertEquals('text', $this->_platform->getDoctrineTypeMapping('tsvector'));
    }

    /**
     * @group DBAL-1220
     */
    public function testReturnsDisallowDatabaseConnectionsSQL()
    {
        self::assertSame(
            "UPDATE pg_database SET datallowconn = 'false' WHERE datname = 'foo'",
            $this->_platform->getDisallowDatabaseConnectionsSQL('foo')
        );
    }

    /**
     * @group DBAL-1220
     */
    public function testReturnsCloseActiveDatabaseConnectionsSQL()
    {
        self::assertSame(
            "SELECT pg_terminate_backend(procpid) FROM pg_stat_activity WHERE datname = 'foo'",
            $this->_platform->getCloseActiveDatabaseConnectionsSQL('foo')
        );
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableForeignKeysSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->_platform->getListTableForeignKeysSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesSchemaNameInListTableForeignKeysSQL()
    {
        self::assertContains(
            "'Foo''Bar\\\\'",
            $this->_platform->getListTableForeignKeysSQL("Foo'Bar\\.baz_table"),
            '',
            true
        );
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableConstraintsSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->_platform->getListTableConstraintsSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableIndexesSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->_platform->getListTableIndexesSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesSchemaNameInListTableIndexesSQL()
    {
        self::assertContains(
            "'Foo''Bar\\\\'",
            $this->_platform->getListTableIndexesSQL("Foo'Bar\\.baz_table"),
            '',
            true
        );
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableColumnsSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->_platform->getListTableColumnsSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesSchemaNameInListTableColumnsSQL()
    {
        self::assertContains(
            "'Foo''Bar\\\\'",
            $this->_platform->getListTableColumnsSQL("Foo'Bar\\.baz_table"),
            '',
            true
        );
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesDatabaseNameInCloseActiveDatabaseConnectionsSQL()
    {
        self::assertContains(
            "'Foo''Bar\\\\'",
            $this->_platform->getCloseActiveDatabaseConnectionsSQL("Foo'Bar\\"),
            '',
            true
        );
    }
}
