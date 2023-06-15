<?php

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use UnexpectedValueException;

use function sprintf;

/** @extends AbstractPlatformTestCase<PostgreSQLPlatform> */
class PostgreSQLPlatformTest extends AbstractPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new PostgreSQLPlatform();
    }

    public function getGenerateTableSql(): string
    {
        return 'CREATE TABLE test (id SERIAL NOT NULL, test VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))';
    }

    /**
     * {@inheritDoc}
     */
    public function getGenerateTableWithMultiColumnUniqueIndexSql(): array
    {
        return [
            'CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL)',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)',
        ];
    }

    public function getGenerateIndexSql(): string
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    protected function getGenerateForeignKeySql(): string
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id)'
            . ' REFERENCES other_table (id) NOT DEFERRABLE INITIALLY IMMEDIATE';
    }

    public function testGeneratesForeignKeySqlForNonStandardOptions(): void
    {
        $foreignKey = new ForeignKeyConstraint(
            ['foreign_id'],
            'my_table',
            ['id'],
            'my_fk',
            ['onDelete' => 'CASCADE'],
        );
        self::assertEquals(
            'CONSTRAINT my_fk FOREIGN KEY (foreign_id)'
                . ' REFERENCES my_table (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
            $this->platform->getForeignKeyDeclarationSQL($foreignKey),
        );

        $foreignKey = new ForeignKeyConstraint(
            ['foreign_id'],
            'my_table',
            ['id'],
            'my_fk',
            ['match' => 'full'],
        );
        self::assertEquals(
            'CONSTRAINT my_fk FOREIGN KEY (foreign_id)'
                . ' REFERENCES my_table (id) MATCH full NOT DEFERRABLE INITIALLY IMMEDIATE',
            $this->platform->getForeignKeyDeclarationSQL($foreignKey),
        );

        $foreignKey = new ForeignKeyConstraint(
            ['foreign_id'],
            'my_table',
            ['id'],
            'my_fk',
            ['deferrable' => true],
        );
        self::assertEquals(
            'CONSTRAINT my_fk FOREIGN KEY (foreign_id)'
                . ' REFERENCES my_table (id) DEFERRABLE INITIALLY IMMEDIATE',
            $this->platform->getForeignKeyDeclarationSQL($foreignKey),
        );

        $foreignKey = new ForeignKeyConstraint(
            ['foreign_id'],
            'my_table',
            ['id'],
            'my_fk',
            ['deferred' => true],
        );
        self::assertEquals(
            'CONSTRAINT my_fk FOREIGN KEY (foreign_id)'
                . ' REFERENCES my_table (id) NOT DEFERRABLE INITIALLY DEFERRED',
            $this->platform->getForeignKeyDeclarationSQL($foreignKey),
        );

        $foreignKey = new ForeignKeyConstraint(
            ['foreign_id'],
            'my_table',
            ['id'],
            'my_fk',
            ['feferred' => true],
        );
        self::assertEquals(
            'CONSTRAINT my_fk FOREIGN KEY (foreign_id)'
                . ' REFERENCES my_table (id) NOT DEFERRABLE INITIALLY DEFERRED',
            $this->platform->getForeignKeyDeclarationSQL($foreignKey),
        );

        $foreignKey = new ForeignKeyConstraint(
            ['foreign_id'],
            'my_table',
            ['id'],
            'my_fk',
            ['deferrable' => true, 'deferred' => true, 'match' => 'full'],
        );
        self::assertEquals(
            'CONSTRAINT my_fk FOREIGN KEY (foreign_id)'
                . ' REFERENCES my_table (id) MATCH full DEFERRABLE INITIALLY DEFERRED',
            $this->platform->getForeignKeyDeclarationSQL($foreignKey),
        );
    }

    public function testGeneratesSqlSnippets(): void
    {
        self::assertEquals('SIMILAR TO', $this->platform->getRegexpExpression());
        self::assertEquals('"', $this->platform->getIdentifierQuoteCharacter());

        self::assertEquals(
            'column1 || column2 || column3',
            $this->platform->getConcatExpression('column1', 'column2', 'column3'),
        );

        self::assertEquals('SUBSTRING(column FROM 5)', $this->platform->getSubstringExpression('column', 5));
        self::assertEquals('SUBSTRING(column FROM 1 FOR 5)', $this->platform->getSubstringExpression('column', 1, 5));
    }

    public function testGeneratesTransactionCommands(): void
    {
        self::assertEquals(
            'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL READ UNCOMMITTED',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_UNCOMMITTED),
        );
        self::assertEquals(
            'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL READ COMMITTED',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_COMMITTED),
        );
        self::assertEquals(
            'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL REPEATABLE READ',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::REPEATABLE_READ),
        );
        self::assertEquals(
            'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL SERIALIZABLE',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::SERIALIZABLE),
        );
    }

    public function testGeneratesDDLSnippets(): void
    {
        self::assertEquals('CREATE DATABASE foobar', $this->platform->getCreateDatabaseSQL('foobar'));
        self::assertEquals('DROP DATABASE foobar', $this->platform->getDropDatabaseSQL('foobar'));
        self::assertEquals('DROP TABLE foobar', $this->platform->getDropTableSQL('foobar'));
    }

    public function testGenerateTableWithAutoincrement(): void
    {
        $table  = new Table('autoinc_table');
        $column = $table->addColumn('id', Types::INTEGER);
        $column->setAutoincrement(true);

        self::assertEquals(
            ['CREATE TABLE autoinc_table (id SERIAL NOT NULL)'],
            $this->platform->getCreateTableSQL($table),
        );
    }

    public function testGenerateUnloggedTable(): void
    {
        $table = new Table('mytable');
        $table->addOption('unlogged', true);
        $table->addColumn('foo', 'string');

        self::assertEquals(
            ['CREATE UNLOGGED TABLE mytable (foo VARCHAR(255) NOT NULL)'],
            $this->platform->getCreateTableSQL($table),
        );
    }

    /** @return mixed[][] */
    public static function serialTypes(): iterable
    {
        return [
            ['integer', 'SERIAL'],
            ['bigint', 'BIGSERIAL'],
        ];
    }

    /** @dataProvider serialTypes */
    public function testGenerateTableWithAutoincrementDoesNotSetDefault(string $type, string $definition): void
    {
        $table  = new Table('autoinc_table_notnull');
        $column = $table->addColumn('id', $type);
        $column->setAutoincrement(true);
        $column->setNotnull(false);

        $sql = $this->platform->getCreateTableSQL($table);

        self::assertEquals([sprintf('CREATE TABLE autoinc_table_notnull (id %s)', $definition)], $sql);
    }

    /** @dataProvider serialTypes */
    public function testCreateTableWithAutoincrementAndNotNullAddsConstraint(string $type, string $definition): void
    {
        $table  = new Table('autoinc_table_notnull_enabled');
        $column = $table->addColumn('id', $type);
        $column->setAutoincrement(true);
        $column->setNotnull(true);

        $sql = $this->platform->getCreateTableSQL($table);

        self::assertEquals([sprintf('CREATE TABLE autoinc_table_notnull_enabled (id %s NOT NULL)', $definition)], $sql);
    }

    /** @dataProvider serialTypes */
    public function testGetDefaultValueDeclarationSQLIgnoresTheDefaultKeyWhenTheFieldIsSerial(string $type): void
    {
        $sql = $this->platform->getDefaultValueDeclarationSQL(
            [
                'autoincrement' => true,
                'type'          => Type::getType($type),
                'default'       => 1,
            ],
        );

        self::assertSame('', $sql);
    }

    public function testGeneratesTypeDeclarationForIntegers(): void
    {
        self::assertEquals(
            'INT',
            $this->platform->getIntegerTypeDeclarationSQL([]),
        );
        self::assertEquals(
            'SERIAL',
            $this->platform->getIntegerTypeDeclarationSQL(['autoincrement' => true]),
        );
        self::assertEquals(
            'SERIAL',
            $this->platform->getIntegerTypeDeclarationSQL(
                ['autoincrement' => true, 'primary' => true],
            ),
        );
    }

    public function testGeneratesTypeDeclarationForStrings(): void
    {
        self::assertEquals(
            'CHAR(10)',
            $this->platform->getStringTypeDeclarationSQL(
                ['length' => 10, 'fixed' => true],
            ),
        );
        self::assertEquals(
            'VARCHAR(50)',
            $this->platform->getStringTypeDeclarationSQL(['length' => 50]),
        );
        self::assertEquals(
            'VARCHAR(255)',
            $this->platform->getStringTypeDeclarationSQL([]),
        );
    }

    public function getGenerateUniqueIndexSql(): string
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    public function testGeneratesSequenceSqlCommands(): void
    {
        $sequence = new Sequence('myseq', 20, 1);
        self::assertEquals(
            'CREATE SEQUENCE myseq INCREMENT BY 20 MINVALUE 1 START 1',
            $this->platform->getCreateSequenceSQL($sequence),
        );
        self::assertEquals(
            'DROP SEQUENCE myseq CASCADE',
            $this->platform->getDropSequenceSQL('myseq'),
        );
        self::assertEquals(
            "SELECT NEXTVAL('myseq')",
            $this->platform->getSequenceNextValSQL('myseq'),
        );
    }

    public function testDoesNotPreferIdentityColumns(): void
    {
        self::assertFalse($this->platform->prefersIdentityColumns());
    }

    public function testSupportsIdentityColumns(): void
    {
        self::assertTrue($this->platform->supportsIdentityColumns());
    }

    public function testSupportsSavePoints(): void
    {
        self::assertTrue($this->platform->supportsSavepoints());
    }

    public function testSupportsSequences(): void
    {
        self::assertTrue($this->platform->supportsSequences());
    }

    protected function supportsCommentOnStatement(): bool
    {
        return true;
    }

    public function testModifyLimitQuery(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        self::assertEquals('SELECT * FROM user LIMIT 10', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10);
        self::assertEquals('SELECT * FROM user LIMIT 10', $sql);
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTableColumnCommentsSQL(): array
    {
        return [
            'CREATE TABLE test (id INT NOT NULL, PRIMARY KEY(id))',
            "COMMENT ON COLUMN test.id IS 'This is a comment'",
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableColumnCommentsSQL(): array
    {
        return [
            'ALTER TABLE mytable ADD quota INT NOT NULL',
            "COMMENT ON COLUMN mytable.quota IS 'A comment'",
            'COMMENT ON COLUMN mytable.foo IS NULL',
            "COMMENT ON COLUMN mytable.baz IS 'B comment'",
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTableColumnTypeCommentsSQL(): array
    {
        return [
            'CREATE TABLE test (id INT NOT NULL, data TEXT NOT NULL, PRIMARY KEY(id))',
            "COMMENT ON COLUMN test.data IS '(DC2Type:array)'",
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedColumnInPrimaryKeySQL(): array
    {
        return ['CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, PRIMARY KEY("create"))'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedColumnInIndexSQL(): array
    {
        return [
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB ON "quoted" ("create")',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedNameInIndexSQL(): array
    {
        return [
            'CREATE TABLE test (column1 VARCHAR(255) NOT NULL)',
            'CREATE INDEX "key" ON test (column1)',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedColumnInForeignKeySQL(): array
    {
        return [
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, '
            . 'foo VARCHAR(255) NOT NULL, "bar" VARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB8C736521D79164E3 ON "quoted" ("create", foo, "bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar")'
                . ' REFERENCES "foreign" ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar")'
                . ' REFERENCES foo ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar")'
                . ' REFERENCES "foo-bar" ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE',
        ];
    }

    /**
     * @param string|bool $databaseValue
     *
     * @dataProvider pgBooleanProvider
     */
    public function testConvertBooleanAsLiteralStrings(
        $databaseValue,
        string $preparedStatementValue
    ): void {
        $platform = $this->createPlatform();

        self::assertEquals($preparedStatementValue, $platform->convertBooleans($databaseValue));
    }

    public function testConvertBooleanAsLiteralIntegers(): void
    {
        $platform = $this->createPlatform();
        $platform->setUseBooleanTrueFalseStrings(false);

        self::assertEquals(1, $platform->convertBooleans(true));
        self::assertEquals(1, $platform->convertBooleans('1'));

        self::assertEquals(0, $platform->convertBooleans(false));
        self::assertEquals(0, $platform->convertBooleans('0'));
    }

    /**
     * @param string|bool $databaseValue
     *
     * @dataProvider pgBooleanProvider
     */
    public function testConvertBooleanAsDatabaseValueStrings(
        $databaseValue,
        string $preparedStatementValue,
        ?int $integerValue,
        ?bool $booleanValue
    ): void {
        $platform = $this->createPlatform();

        self::assertSame($integerValue, $platform->convertBooleansToDatabaseValue($booleanValue));
    }

    public function testConvertBooleanAsDatabaseValueIntegers(): void
    {
        $platform = $this->createPlatform();
        $platform->setUseBooleanTrueFalseStrings(false);

        self::assertSame(1, $platform->convertBooleansToDatabaseValue(true));
        self::assertSame(0, $platform->convertBooleansToDatabaseValue(false));
    }

    /**
     * @param string|bool $databaseValue
     *
     * @dataProvider pgBooleanProvider
     */
    public function testConvertFromBoolean(
        $databaseValue,
        string $prepareStatementValue,
        ?int $integerValue,
        ?bool $booleanValue
    ): void {
        $platform = $this->createPlatform();

        self::assertSame($booleanValue, $platform->convertFromBoolean($databaseValue));
    }

    public function testThrowsExceptionWithInvalidBooleanLiteral(): void
    {
        $platform = $this->createPlatform();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Unrecognized boolean literal 'my-bool'");

        $platform->convertBooleansToDatabaseValue('my-bool');
    }

    public function testGetCreateSchemaSQL(): void
    {
        $schemaName = 'schema';
        $sql        = $this->platform->getCreateSchemaSQL($schemaName);
        self::assertEquals('CREATE SCHEMA ' . $schemaName, $sql);
    }

    public function testAlterDecimalPrecisionScale(): void
    {
        $table = new Table('mytable');
        $table->addColumn('dfoo1', Types::DECIMAL);
        $table->addColumn('dfoo2', Types::DECIMAL, ['precision' => 10, 'scale' => 6]);
        $table->addColumn('dfoo3', Types::DECIMAL, ['precision' => 10, 'scale' => 6]);
        $table->addColumn('dfoo4', Types::DECIMAL, ['precision' => 10, 'scale' => 6]);

        $tableDiff            = new TableDiff('mytable');
        $tableDiff->fromTable = $table;

        $tableDiff->changedColumns['dloo1'] = new ColumnDiff(
            'dloo1',
            new Column(
                'dloo1',
                Type::getType(Types::DECIMAL),
                ['precision' => 16, 'scale' => 6],
            ),
            ['precision'],
        );
        $tableDiff->changedColumns['dloo2'] = new ColumnDiff(
            'dloo2',
            new Column(
                'dloo2',
                Type::getType(Types::DECIMAL),
                ['precision' => 10, 'scale' => 4],
            ),
            ['scale'],
        );
        $tableDiff->changedColumns['dloo3'] = new ColumnDiff(
            'dloo3',
            new Column(
                'dloo3',
                Type::getType(Types::DECIMAL),
                ['precision' => 10, 'scale' => 6],
            ),
            [],
        );
        $tableDiff->changedColumns['dloo4'] = new ColumnDiff(
            'dloo4',
            new Column(
                'dloo4',
                Type::getType(Types::DECIMAL),
                ['precision' => 16, 'scale' => 8],
            ),
            ['precision', 'scale'],
        );

        $sql = $this->platform->getAlterTableSQL($tableDiff);

        $expectedSql = [
            'ALTER TABLE mytable ALTER dloo1 TYPE NUMERIC(16, 6)',
            'ALTER TABLE mytable ALTER dloo2 TYPE NUMERIC(10, 4)',
            'ALTER TABLE mytable ALTER dloo4 TYPE NUMERIC(16, 8)',
        ];

        self::assertEquals($expectedSql, $sql);
    }

    public function testDroppingConstraintsBeforeColumns(): void
    {
        $newTable = new Table('mytable');
        $newTable->addColumn('id', Types::INTEGER);
        $newTable->setPrimaryKey(['id']);

        $oldTable = clone $newTable;
        $oldTable->addColumn('parent_id', Types::INTEGER);
        $oldTable->addForeignKeyConstraint('mytable', ['parent_id'], ['id']);

        $diff = (new Comparator())->diffTable($oldTable, $newTable);
        self::assertNotFalse($diff);

        $sql = $this->platform->getAlterTableSQL($diff);

        $expectedSql = [
            'ALTER TABLE mytable DROP CONSTRAINT FK_6B2BD609727ACA70',
            'DROP INDEX IDX_6B2BD609727ACA70',
            'ALTER TABLE mytable DROP parent_id',
        ];

        self::assertEquals($expectedSql, $sql);
    }

    public function testDroppingPrimaryKey(): void
    {
        $oldTable = new Table('mytable');
        $oldTable->addColumn('id', 'integer');
        $oldTable->setPrimaryKey(['id']);

        $newTable = clone $oldTable;
        $newTable->dropPrimaryKey();

        $diff = (new Comparator())->compareTables($oldTable, $newTable);

        $sql = $this->platform->getAlterTableSQL($diff);

        $expectedSql = ['ALTER TABLE mytable DROP CONSTRAINT mytable_pkey'];

        self::assertEquals($expectedSql, $sql);
    }

    public function testDroppingPrimaryKeyWithUserDefinedName(): void
    {
        self::markTestSkipped('Edge case not covered yet');

        $oldTable = new Table('mytable');
        $oldTable->addColumn('id', 'integer');
        $oldTable->setPrimaryKey(['id'], 'a_user_name');

        $newTable = clone $oldTable;
        $newTable->dropPrimaryKey();

        $diff = (new Comparator())->compareTables($oldTable, $newTable);

        $sql = $this->platform->getAlterTableSQL($diff);

        $expectedSql = ['ALTER TABLE mytable DROP CONSTRAINT a_user_name'];

        self::assertEquals($expectedSql, $sql);
    }

    public function testUsesSequenceEmulatedIdentityColumns(): void
    {
        self::assertTrue($this->platform->usesSequenceEmulatedIdentityColumns());
    }

    public function testReturnsIdentitySequenceName(): void
    {
        self::assertSame('mytable_mycolumn_seq', $this->platform->getIdentitySequenceName('mytable', 'mycolumn'));
    }

    /** @dataProvider dataCreateSequenceWithCache */
    public function testCreateSequenceWithCache(int $cacheSize, string $expectedSql): void
    {
        $sequence = new Sequence('foo', 1, 1, $cacheSize);
        self::assertStringContainsString($expectedSql, $this->platform->getCreateSequenceSQL($sequence));
    }

    /** @return mixed[][] */
    public static function dataCreateSequenceWithCache(): iterable
    {
        return [
            [3, 'CACHE 3'],
        ];
    }

    protected function getBinaryDefaultLength(): int
    {
        return 0;
    }

    protected function getBinaryMaxLength(): int
    {
        return 0;
    }

    public function testReturnsBinaryTypeDeclarationSQL(): void
    {
        self::assertSame('BYTEA', $this->platform->getBinaryTypeDeclarationSQL([]));
        self::assertSame('BYTEA', $this->platform->getBinaryTypeDeclarationSQL(['length' => 0]));
        self::assertSame('BYTEA', $this->platform->getBinaryTypeDeclarationSQL(['length' => 9999999]));

        self::assertSame('BYTEA', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true]));
        self::assertSame('BYTEA', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 0]));
        self::assertSame('BYTEA', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 9999999]));
    }

    public function testDoesNotPropagateUnnecessaryTableAlterationOnBinaryType(): void
    {
        $table1 = new Table('mytable');
        $table1->addColumn('column_varbinary', Types::BINARY);
        $table1->addColumn('column_binary', Types::BINARY, ['fixed' => true]);
        $table1->addColumn('column_blob', Types::BLOB);

        $table2 = new Table('mytable');
        $table2->addColumn('column_varbinary', Types::BINARY, ['fixed' => true]);
        $table2->addColumn('column_binary', Types::BINARY);
        $table2->addColumn('column_blob', Types::BINARY);

        $comparator = new Comparator();

        // VARBINARY -> BINARY
        // BINARY    -> VARBINARY
        // BLOB      -> VARBINARY
        $diff = $comparator->diffTable($table1, $table2);
        self::assertNotFalse($diff);
        self::assertEmpty($this->platform->getAlterTableSQL($diff));

        $table2 = new Table('mytable');
        $table2->addColumn('column_varbinary', Types::BINARY, ['length' => 42]);
        $table2->addColumn('column_binary', Types::BLOB);
        $table2->addColumn('column_blob', Types::BINARY, ['length' => 11, 'fixed' => true]);

        // VARBINARY -> VARBINARY with changed length
        // BINARY    -> BLOB
        // BLOB      -> BINARY
        $diff = $comparator->diffTable($table1, $table2);
        self::assertNotFalse($diff);
        self::assertEmpty($this->platform->getAlterTableSQL($diff));

        $table2 = new Table('mytable');
        $table2->addColumn('column_varbinary', Types::BLOB);
        $table2->addColumn('column_binary', Types::BINARY, ['length' => 42, 'fixed' => true]);
        $table2->addColumn('column_blob', Types::BLOB);

        // VARBINARY -> BLOB
        // BINARY    -> BINARY with changed length
        // BLOB      -> BLOB
        $diff = $comparator->diffTable($table1, $table2);
        self::assertNotFalse($diff);
        self::assertEmpty($this->platform->getAlterTableSQL($diff));
    }

    /**
     * {@inheritDoc}
     */
    protected function getAlterTableRenameIndexSQL(): array
    {
        return ['ALTER INDEX idx_foo RENAME TO idx_bar'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedAlterTableRenameIndexSQL(): array
    {
        return [
            'ALTER INDEX "create" RENAME TO "select"',
            'ALTER INDEX "foo" RENAME TO "bar"',
        ];
    }

    /**
     * PostgreSQL boolean strings provider
     *
     * @return mixed[][]
     */
    public static function pgBooleanProvider(): iterable
    {
        return [
            // Database value, prepared statement value, boolean integer value, boolean value.
            [true, 'true', 1, true],
            ['t', 'true', 1, true],
            ['true', 'true', 1, true],
            ['y', 'true', 1, true],
            ['yes', 'true', 1, true],
            ['on', 'true', 1, true],
            ['1', 'true', 1, true],

            [false, 'false', 0, false],
            ['f', 'false', 0, false],
            ['false', 'false', 0, false],
            [ 'n', 'false', 0, false],
            ['no', 'false', 0, false],
            ['off', 'false', 0, false],
            ['0', 'false', 0, false],

            [null, 'NULL', null, null],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedAlterTableRenameColumnSQL(): array
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
     * {@inheritDoc}
     */
    protected function getQuotedAlterTableChangeColumnLengthSQL(): array
    {
        return [
            'ALTER TABLE mytable ALTER unquoted1 TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER unquoted2 TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER unquoted3 TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER "create" TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER "table" TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER "select" TYPE VARCHAR(255)',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getAlterTableRenameIndexInSchemaSQL(): array
    {
        return ['ALTER INDEX myschema.idx_foo RENAME TO idx_bar'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL(): array
    {
        return [
            'ALTER INDEX "schema"."create" RENAME TO "select"',
            'ALTER INDEX "schema"."foo" RENAME TO "bar"',
        ];
    }

    protected function getQuotesDropForeignKeySQL(): string
    {
        return 'ALTER TABLE "table" DROP CONSTRAINT "select"';
    }

    public function testGetNullCommentOnColumnSQL(): void
    {
        self::assertEquals(
            'COMMENT ON COLUMN mytable.id IS NULL',
            $this->platform->getCommentOnColumnSQL('mytable', 'id', null),
        );
    }

    public function testReturnsGuidTypeDeclarationSQL(): void
    {
        self::assertSame('UUID', $this->platform->getGuidTypeDeclarationSQL([]));
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableRenameColumnSQL(): array
    {
        return ['ALTER TABLE foo RENAME COLUMN bar TO baz'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getCommentOnColumnSQL(): array
    {
        return [
            'COMMENT ON COLUMN foo.bar IS \'comment\'',
            'COMMENT ON COLUMN "Foo"."BAR" IS \'comment\'',
            'COMMENT ON COLUMN "select"."from" IS \'comment\'',
        ];
    }

    public function testAltersTableColumnCommentWithExplicitlyQuotedIdentifiers(): void
    {
        $table1 = new Table('"foo"', [new Column('"bar"', Type::getType(Types::INTEGER))]);
        $table2 = new Table('"foo"', [new Column('"bar"', Type::getType(Types::INTEGER), ['comment' => 'baz'])]);

        $comparator = new Comparator();

        $tableDiff = $comparator->diffTable($table1, $table2);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertSame(
            ['COMMENT ON COLUMN "foo"."bar" IS \'baz\''],
            $this->platform->getAlterTableSQL($tableDiff),
        );
    }

    public function testAltersTableColumnCommentIfRequiredByType(): void
    {
        $table1 = new Table('"foo"', [new Column('"bar"', Type::getType(Types::DATETIME_MUTABLE))]);
        $table2 = new Table('"foo"', [new Column('"bar"', Type::getType(Types::DATETIME_IMMUTABLE))]);

        $comparator = new Comparator();

        $tableDiff = $comparator->diffTable($table1, $table2);

        self::assertNotFalse($tableDiff);
        self::assertSame(
            [
                'ALTER TABLE "foo" ALTER "bar" TYPE TIMESTAMP(0) WITHOUT TIME ZONE',
                'COMMENT ON COLUMN "foo"."bar" IS \'(DC2Type:datetime_immutable)\'',
            ],
            $this->platform->getAlterTableSQL($tableDiff),
        );
    }

    protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL(): string
    {
        return 'CONSTRAINT "select" UNIQUE (foo)';
    }

    protected function getQuotesReservedKeywordInIndexDeclarationSQL(): string
    {
        return 'INDEX "select" (foo)';
    }

    protected function getQuotesReservedKeywordInTruncateTableSQL(): string
    {
        return 'TRUNCATE "select"';
    }

    /**
     * {@inheritDoc}
     */
    protected function getAlterStringToFixedStringSQL(): array
    {
        return ['ALTER TABLE mytable ALTER name TYPE CHAR(2)'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(): array
    {
        return ['ALTER INDEX idx_foo RENAME TO idx_foo_renamed'];
    }

    public function testInitializesTsvectorTypeMapping(): void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('tsvector'));
        self::assertEquals(Types::TEXT, $this->platform->getDoctrineTypeMapping('tsvector'));
    }

    public function testQuotesTableNameInListTableForeignKeysSQL(): void
    {
        self::assertStringContainsStringIgnoringCase(
            "'Foo''Bar\\'",
            $this->platform->getListTableForeignKeysSQL("Foo'Bar\\"),
        );
    }

    public function testQuotesSchemaNameInListTableForeignKeysSQL(): void
    {
        self::assertStringContainsStringIgnoringCase(
            "'Foo''Bar\\'",
            $this->platform->getListTableForeignKeysSQL("Foo'Bar\\.baz_table"),
        );
    }

    public function testQuotesTableNameInListTableConstraintsSQL(): void
    {
        self::assertStringContainsStringIgnoringCase(
            "'Foo''Bar\\'",
            $this->platform->getListTableConstraintsSQL("Foo'Bar\\"),
        );
    }

    public function testQuotesTableNameInListTableIndexesSQL(): void
    {
        self::assertStringContainsStringIgnoringCase(
            "'Foo''Bar\\'",
            $this->platform->getListTableIndexesSQL("Foo'Bar\\"),
        );
    }

    public function testQuotesSchemaNameInListTableIndexesSQL(): void
    {
        self::assertStringContainsStringIgnoringCase(
            "'Foo''Bar\\'",
            $this->platform->getListTableIndexesSQL("Foo'Bar\\.baz_table"),
        );
    }

    public function testQuotesTableNameInListTableColumnsSQL(): void
    {
        self::assertStringContainsStringIgnoringCase(
            "'Foo''Bar\\'",
            $this->platform->getListTableColumnsSQL("Foo'Bar\\"),
        );
    }

    public function testQuotesSchemaNameInListTableColumnsSQL(): void
    {
        self::assertStringContainsStringIgnoringCase(
            "'Foo''Bar\\'",
            $this->platform->getListTableColumnsSQL("Foo'Bar\\.baz_table"),
        );
    }

    public function testSupportsPartialIndexes(): void
    {
        self::assertTrue($this->platform->supportsPartialIndexes());
    }

    public function testGetCreateTableSQLWithUniqueConstraints(): void
    {
        $table = new Table('foo');
        $table->addColumn('id', Types::STRING);
        $table->addUniqueConstraint(['id'], 'test_unique_constraint');
        self::assertSame(
            [
                'CREATE TABLE foo (id VARCHAR(255) NOT NULL)',
                'ALTER TABLE foo ADD CONSTRAINT test_unique_constraint UNIQUE (id)',
            ],
            $this->platform->getCreateTableSQL($table),
            'Unique constraints are added to table.',
        );
    }

    public function testGetCreateTableSQLWithColumnCollation(): void
    {
        $table = new Table('foo');
        $table->addColumn('id', Types::STRING);
        $table->addOption('comment', 'foo');
        self::assertSame(
            [
                'CREATE TABLE foo (id VARCHAR(255) NOT NULL)',
                "COMMENT ON TABLE foo IS 'foo'",
            ],
            $this->platform->getCreateTableSQL($table),
            'Comments are added to table.',
        );
    }

    public function testColumnCollationDeclarationSQL(): void
    {
        self::assertEquals(
            'COLLATE "en_US.UTF-8"',
            $this->platform->getColumnCollationDeclarationSQL('en_US.UTF-8'),
        );
    }

    public function testHasNativeJsonType(): void
    {
        self::assertTrue($this->platform->hasNativeJsonType());
    }

    public function testReturnsJsonTypeDeclarationSQL(): void
    {
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL([]));
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL(['jsonb' => false]));
        self::assertSame('JSONB', $this->platform->getJsonTypeDeclarationSQL(['jsonb' => true]));
    }

    public function testReturnsSmallIntTypeDeclarationSQL(): void
    {
        self::assertSame(
            'SMALLSERIAL',
            $this->platform->getSmallIntTypeDeclarationSQL(['autoincrement' => true]),
        );

        self::assertSame(
            'SMALLINT',
            $this->platform->getSmallIntTypeDeclarationSQL(['autoincrement' => false]),
        );

        self::assertSame(
            'SMALLINT',
            $this->platform->getSmallIntTypeDeclarationSQL([]),
        );
    }

    public function testInitializesJsonTypeMapping(): void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('json'));
        self::assertEquals(Types::JSON, $this->platform->getDoctrineTypeMapping('json'));
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('jsonb'));
        self::assertEquals(Types::JSON, $this->platform->getDoctrineTypeMapping('jsonb'));
    }
}
