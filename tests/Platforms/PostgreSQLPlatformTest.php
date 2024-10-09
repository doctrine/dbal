<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
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
        return 'CREATE TABLE test (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, test VARCHAR(255) DEFAULT NULL'
            . ', PRIMARY KEY(id))';
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

        self::assertEquals(
            'column1 || column2 || column3',
            $this->platform->getConcatExpression('column1', 'column2', 'column3'),
        );

        self::assertEquals('SUBSTRING(column FROM 5)', $this->platform->getSubstringExpression('column', '5'));

        self::assertEquals(
            'SUBSTRING(column FROM 1 FOR 5)',
            $this->platform->getSubstringExpression('column', '1', '5'),
        );
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
            ['CREATE TABLE autoinc_table (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL)'],
            $this->platform->getCreateTableSQL($table),
        );
    }

    #[DataProvider('pgTemporaryProvider')]
    public function testGenerateTemporaryTable(
        bool $temporary,
        string|null $onCommit,
        string $expectedSQL,
    ): void {
        $table = new Table('mytable');
        $table->addOption('temporary', $temporary);
        if ($onCommit !== null) {
            $table->addOption('on_commit', $onCommit);
        }

        $table->addColumn('foo', 'string');

        self::assertEquals(
            [$expectedSQL],
            $this->platform->getCreateTableSQL($table),
        );
    }

    public static function pgTemporaryProvider(): Generator
    {
        yield 'temporary, no on commit option' => [true, null, 'CREATE TEMPORARY TABLE mytable (foo VARCHAR NOT NULL)'];

        yield 'temporary, empty on commit option' =>
        [true, '', 'CREATE TEMPORARY TABLE mytable (foo VARCHAR NOT NULL)'];

        yield 'non temporary' => [false, null, 'CREATE TABLE mytable (foo VARCHAR NOT NULL)'];

        yield 'temporary, preserve rows on commit' =>
        [true, 'preserve', 'CREATE TEMPORARY TABLE mytable (foo VARCHAR NOT NULL) ON COMMIT PRESERVE ROWS'];

        yield 'non temporary, preserve rows on commit omitted' =>
        [false, 'preserve', 'CREATE TABLE mytable (foo VARCHAR NOT NULL)'];

        yield 'temporary, delete rows on commit' =>
        [true, 'delete', 'CREATE TEMPORARY TABLE mytable (foo VARCHAR NOT NULL) ON COMMIT DELETE ROWS'];

        yield 'non temporary, delete rows on commit omitted' =>
        [false, 'delete', 'CREATE TABLE mytable (foo VARCHAR NOT NULL)'];

        yield 'temporary, drop on commit' =>
        [true, 'drop', 'CREATE TEMPORARY TABLE mytable (foo VARCHAR NOT NULL) ON COMMIT DROP'];

        yield 'non temporary, drop on commit omitted' =>
        [false, 'drop', 'CREATE TABLE mytable (foo VARCHAR NOT NULL)'];
    }

    #[DataProvider('pgInvalidTemporaryProvider')]
    public function testInvalidTemporaryTableOptions(
        string $table,
        mixed $temporary,
        string|null $onCommit,
        string $expectedException,
        string $expectedMessage,
    ): void {
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedMessage);

        $table = new Table($table);
        $table->addOption('temporary', $temporary);
        if ($onCommit !== null) {
            $table->addOption('on_commit', $onCommit);
        }

        $table->addColumn('foo', 'string');

        $this->platform->getCreateTableSQL($table);
    }

    public static function pgInvalidTemporaryProvider(): Generator
    {
        yield 'valid temporary specification, invalid on commit option' =>
        ['mytable', true, 'invalid', InvalidArgumentException::class, 'invalid on commit clause on table mytable'];

        yield 'invalid temporary specification' =>
        ['mytable', 'invalid', '', InvalidArgumentException::class, 'invalid temporary specification for table mytable'];
    }

    public function testGenerateUnloggedTable(): void
    {
        $table = new Table('mytable');
        $table->addOption('unlogged', true);
        $table->addColumn('foo', 'string');

        self::assertEquals(
            ['CREATE UNLOGGED TABLE mytable (foo VARCHAR NOT NULL)'],
            $this->platform->getCreateTableSQL($table),
        );
    }

    /** @return mixed[][] */
    public static function serialTypes(): iterable
    {
        return [
            ['integer', 'INT GENERATED BY DEFAULT AS IDENTITY'],
            ['bigint', 'BIGINT GENERATED BY DEFAULT AS IDENTITY'],
        ];
    }

    #[DataProvider('serialTypes')]
    public function testGenerateTableWithAutoincrementDoesNotSetDefault(string $type, string $definition): void
    {
        $table  = new Table('autoinc_table_notnull');
        $column = $table->addColumn('id', $type);
        $column->setAutoincrement(true);
        $column->setNotnull(false);

        $sql = $this->platform->getCreateTableSQL($table);

        self::assertEquals([sprintf('CREATE TABLE autoinc_table_notnull (id %s)', $definition)], $sql);
    }

    #[DataProvider('serialTypes')]
    public function testCreateTableWithAutoincrementAndNotNullAddsConstraint(string $type, string $definition): void
    {
        $table  = new Table('autoinc_table_notnull_enabled');
        $column = $table->addColumn('id', $type);
        $column->setAutoincrement(true);
        $column->setNotnull(true);

        $sql = $this->platform->getCreateTableSQL($table);

        self::assertEquals([sprintf('CREATE TABLE autoinc_table_notnull_enabled (id %s NOT NULL)', $definition)], $sql);
    }

    #[DataProvider('serialTypes')]
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
            'INT GENERATED BY DEFAULT AS IDENTITY',
            $this->platform->getIntegerTypeDeclarationSQL(['autoincrement' => true]),
        );
        self::assertEquals(
            'INT GENERATED BY DEFAULT AS IDENTITY',
            $this->platform->getIntegerTypeDeclarationSQL(
                ['autoincrement' => true, 'primary' => true],
            ),
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

    #[DataProvider('pgBooleanProvider')]
    public function testConvertBooleanAsLiteralStrings(
        string $databaseValue,
        string $preparedStatementValue,
    ): void {
        self::assertEquals($preparedStatementValue, $this->platform->convertBooleans($databaseValue));
    }

    public function testConvertBooleanAsLiteralIntegers(): void
    {
        $this->platform->setUseBooleanTrueFalseStrings(false);

        self::assertEquals(1, $this->platform->convertBooleans(true));
        self::assertEquals(1, $this->platform->convertBooleans('1'));

        self::assertEquals(0, $this->platform->convertBooleans(false));
        self::assertEquals(0, $this->platform->convertBooleans('0'));
    }

    #[DataProvider('pgBooleanProvider')]
    public function testConvertBooleanAsDatabaseValueStrings(
        string $databaseValue,
        string $preparedStatementValue,
        int $integerValue,
        bool $booleanValue,
    ): void {
        self::assertSame($integerValue, $this->platform->convertBooleansToDatabaseValue($booleanValue));
    }

    public function testConvertBooleanAsDatabaseValueIntegers(): void
    {
        $this->platform->setUseBooleanTrueFalseStrings(false);

        self::assertSame(1, $this->platform->convertBooleansToDatabaseValue(true));
        self::assertSame(0, $this->platform->convertBooleansToDatabaseValue(false));
    }

    #[DataProvider('pgBooleanProvider')]
    public function testConvertFromBoolean(
        string $databaseValue,
        string $prepareStatementValue,
        int $integerValue,
        bool $booleanValue,
    ): void {
        self::assertSame($booleanValue, $this->platform->convertFromBoolean($databaseValue));
    }

    public function testThrowsExceptionWithInvalidBooleanLiteral(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Unrecognized boolean literal, my-bool given.');

        $this->platform->convertBooleansToDatabaseValue('my-bool');
    }

    public function testGetCreateSchemaSQL(): void
    {
        $schemaName = 'schema';
        $sql        = $this->platform->getCreateSchemaSQL($schemaName);
        self::assertEquals('CREATE SCHEMA ' . $schemaName, $sql);
    }

    public function testDroppingConstraintsBeforeColumns(): void
    {
        $newTable = new Table('mytable');
        $newTable->addColumn('id', Types::INTEGER);
        $newTable->setPrimaryKey(['id']);

        $oldTable = clone $newTable;
        $oldTable->addColumn('parent_id', Types::INTEGER);
        $oldTable->addForeignKeyConstraint('mytable', ['parent_id'], ['id']);

        $diff = $this->createComparator()
            ->compareTables($oldTable, $newTable);

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

        $diff = $this->createComparator()
            ->compareTables($oldTable, $newTable);

        $sql = $this->platform->getAlterTableSQL($diff);

        $expectedSql = ['ALTER TABLE mytable DROP CONSTRAINT mytable_pkey'];

        self::assertEquals($expectedSql, $sql);
    }

    #[DataProvider('dataCreateSequenceWithCache')]
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

    public function getExpectedFixedLengthBinaryTypeDeclarationSQLNoLength(): string
    {
        return 'BYTEA';
    }

    public function getExpectedFixedLengthBinaryTypeDeclarationSQLWithLength(): string
    {
        return 'BYTEA';
    }

    public function getExpectedVariableLengthBinaryTypeDeclarationSQLNoLength(): string
    {
        return 'BYTEA';
    }

    public function getExpectedVariableLengthBinaryTypeDeclarationSQLWithLength(): string
    {
        return 'BYTEA';
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

        $comparator = $this->createComparator();

        // VARBINARY -> BINARY
        // BINARY    -> VARBINARY
        // BLOB      -> VARBINARY
        self::assertTrue(
            $comparator->compareTables($table1, $table2)
                ->isEmpty(),
        );

        $table2 = new Table('mytable');
        $table2->addColumn('column_varbinary', Types::BINARY, ['length' => 42]);
        $table2->addColumn('column_binary', Types::BLOB);
        $table2->addColumn('column_blob', Types::BINARY, ['length' => 11, 'fixed' => true]);

        // VARBINARY -> VARBINARY with changed length
        // BINARY    -> BLOB
        // BLOB      -> BINARY
        self::assertTrue(
            $comparator->compareTables($table1, $table2)
                ->isEmpty(),
        );

        $table2 = new Table('mytable');
        $table2->addColumn('column_varbinary', Types::BLOB);
        $table2->addColumn('column_binary', Types::BINARY, ['length' => 42, 'fixed' => true]);
        $table2->addColumn('column_blob', Types::BLOB);

        // VARBINARY -> BLOB
        // BINARY    -> BINARY with changed length
        // BLOB      -> BLOB
        self::assertTrue(
            $comparator->compareTables($table1, $table2)
                ->isEmpty(),
        );
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
            ['t', 'true', 1, true],
            ['true', 'true', 1, true],
            ['y', 'true', 1, true],
            ['yes', 'true', 1, true],
            ['on', 'true', 1, true],
            ['1', 'true', 1, true],

            ['f', 'false', 0, false],
            ['false', 'false', 0, false],
            [ 'n', 'false', 0, false],
            ['no', 'false', 0, false],
            ['off', 'false', 0, false],
            ['0', 'false', 0, false],
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

    public function testReturnsGuidTypeDeclarationSQL(): void
    {
        self::assertSame('UUID', $this->platform->getGuidTypeDeclarationSQL([]));
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

        $tableDiff = $this->createComparator()
            ->compareTables($table1, $table2);

        self::assertSame(
            ['COMMENT ON COLUMN "foo"."bar" IS \'baz\''],
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
                'CREATE TABLE foo (id VARCHAR NOT NULL)',
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
                'CREATE TABLE foo (id VARCHAR NOT NULL)',
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

    public function testReturnsJsonTypeDeclarationSQL(): void
    {
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL([]));
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL(['jsonb' => false]));
        self::assertSame('JSONB', $this->platform->getJsonTypeDeclarationSQL(['jsonb' => true]));
    }

    public function testReturnsSmallIntTypeDeclarationSQL(): void
    {
        self::assertSame(
            'SMALLINT GENERATED BY DEFAULT AS IDENTITY',
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

    public function testGetListSequencesSQL(): void
    {
        self::assertSame(
            "SELECT sequence_name AS relname,
                       sequence_schema AS schemaname,
                       minimum_value AS min_value,
                       increment AS increment_by
                FROM   information_schema.sequences
                WHERE  sequence_catalog = 'test_db'
                AND    sequence_schema NOT LIKE 'pg\_%'
                AND    sequence_schema != 'information_schema'",
            $this->platform->getListSequencesSQL('test_db'),
        );
    }
}
