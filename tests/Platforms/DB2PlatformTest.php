<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Exception\InvalidColumnDeclaration;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;

/** @extends AbstractPlatformTestCase<DB2Platform> */
class DB2PlatformTest extends AbstractPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new DB2Platform();
    }

    protected function getGenerateForeignKeySql(): string
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
    }

    public function getGenerateIndexSql(): string
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateTableSql(): string
    {
        return 'CREATE TABLE test (id INTEGER GENERATED BY DEFAULT AS IDENTITY NOT NULL, '
            . 'test VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))';
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

    public function getGenerateUniqueIndexSql(): string
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedColumnInForeignKeySQL(): array
    {
        return [
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, '
                . '"bar" VARCHAR(255) NOT NULL)',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar")'
                . ' REFERENCES "foreign" ("create", bar, "foo-bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar")'
                . ' REFERENCES foo ("create", bar, "foo-bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar")'
                . ' REFERENCES "foo-bar" ("create", bar, "foo-bar")',
            'CREATE INDEX IDX_22660D028FD6E0FB8C736521D79164E3 ON "quoted" ("create", foo, "bar")',
        ];
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
    protected function getQuotedColumnInPrimaryKeySQL(): array
    {
        return ['CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, PRIMARY KEY("create"))'];
    }

    protected function getBitAndComparisonExpressionSql(string $value1, string $value2): string
    {
        return 'BITAND(' . $value1 . ', ' . $value2 . ')';
    }

    protected function getBitOrComparisonExpressionSql(string $value1, string $value2): string
    {
        return 'BITOR(' . $value1 . ', ' . $value2 . ')';
    }

    public function testGeneratesCreateTableSQLWithCommonIndexes(): void
    {
        $table = new Table('test');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('name', Types::STRING, ['length' => 50]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['name']);
        $table->addIndex(['id', 'name'], 'composite_idx');

        self::assertEquals(
            [
                'CREATE TABLE test (id INTEGER NOT NULL, name VARCHAR(50) NOT NULL, PRIMARY KEY(id))',
                'CREATE INDEX IDX_D87F7E0C5E237E06 ON test (name)',
                'CREATE INDEX composite_idx ON test (id, name)',
            ],
            $this->platform->getCreateTableSQL($table),
        );
    }

    public function testGeneratesCreateTableSQLWithForeignKeyConstraints(): void
    {
        $table = new Table('test');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('fk_1', Types::INTEGER);
        $table->addColumn('fk_2', Types::INTEGER);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint('foreign_table', ['fk_1', 'fk_2'], ['pk_1', 'pk_2']);
        $table->addForeignKeyConstraint(
            'foreign_table2',
            ['fk_1', 'fk_2'],
            ['pk_1', 'pk_2'],
            [],
            'named_fk',
        );

        self::assertEquals(
            [
                'CREATE TABLE test (id INTEGER NOT NULL, fk_1 INTEGER NOT NULL, fk_2 INTEGER NOT NULL'
                    . ', PRIMARY KEY(id))',
                'ALTER TABLE test ADD CONSTRAINT FK_D87F7E0C177612A38E7F4319 FOREIGN KEY (fk_1, fk_2)'
                    . ' REFERENCES foreign_table (pk_1, pk_2)',
                'ALTER TABLE test ADD CONSTRAINT named_fk FOREIGN KEY (fk_1, fk_2)'
                    . ' REFERENCES foreign_table2 (pk_1, pk_2)',
                'CREATE INDEX IDX_D87F7E0C177612A38E7F4319 ON test (fk_1, fk_2)',
            ],
            $this->platform->getCreateTableSQL($table),
        );
    }

    public function testGeneratesCreateTableSQLWithCheckConstraints(): void
    {
        $table = new Table('test');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('check_max', Types::INTEGER, ['platformOptions' => ['max' => 10]]);
        $table->addColumn('check_min', Types::INTEGER, ['platformOptions' => ['min' => 10]]);
        $table->setPrimaryKey(['id']);

        self::assertEquals(
            [
                'CREATE TABLE test (id INTEGER NOT NULL, check_max INTEGER NOT NULL, '
                    . 'check_min INTEGER NOT NULL, PRIMARY KEY(id), CHECK (check_max <= 10), CHECK (check_min >= 10))',
            ],
            $this->platform->getCreateTableSQL($table),
        );
    }

    public function testGeneratesColumnTypesDeclarationSQL(): void
    {
        $fullColumnDef = [
            'length' => 10,
            'fixed' => true,
            'unsigned' => true,
            'autoincrement' => true,
        ];

        self::assertEquals('SMALLINT', $this->platform->getSmallIntTypeDeclarationSQL([]));
        self::assertEquals('SMALLINT', $this->platform->getSmallIntTypeDeclarationSQL(['unsigned' => true]));

        self::assertEquals(
            'SMALLINT GENERATED BY DEFAULT AS IDENTITY',
            $this->platform->getSmallIntTypeDeclarationSQL($fullColumnDef),
        );

        self::assertEquals('INTEGER', $this->platform->getIntegerTypeDeclarationSQL([]));
        self::assertEquals('INTEGER', $this->platform->getIntegerTypeDeclarationSQL(['unsigned' => true]));

        self::assertEquals(
            'INTEGER GENERATED BY DEFAULT AS IDENTITY',
            $this->platform->getIntegerTypeDeclarationSQL($fullColumnDef),
        );

        self::assertEquals('BIGINT', $this->platform->getBigIntTypeDeclarationSQL([]));
        self::assertEquals('BIGINT', $this->platform->getBigIntTypeDeclarationSQL(['unsigned' => true]));

        self::assertEquals(
            'BIGINT GENERATED BY DEFAULT AS IDENTITY',
            $this->platform->getBigIntTypeDeclarationSQL($fullColumnDef),
        );

        self::assertEquals('BLOB(1M)', $this->platform->getBlobTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('SMALLINT', $this->platform->getBooleanTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('CLOB(1M)', $this->platform->getClobTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('DATE', $this->platform->getDateTypeDeclarationSQL($fullColumnDef));

        self::assertEquals(
            'TIMESTAMP(0) WITH DEFAULT',
            $this->platform->getDateTimeTypeDeclarationSQL(['version' => true]),
        );

        self::assertEquals('TIMESTAMP(0)', $this->platform->getDateTimeTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('TIME', $this->platform->getTimeTypeDeclarationSQL($fullColumnDef));
    }

    public function testGeneratesDDLSnippets(): void
    {
        self::assertEquals('DECLARE GLOBAL TEMPORARY TABLE', $this->platform->getCreateTemporaryTableSnippetSQL());
        self::assertEquals('TRUNCATE foobar IMMEDIATE', $this->platform->getTruncateTableSQL('foobar'));

        $viewSql = 'SELECT * FROM footable';

        self::assertEquals(
            'CREATE VIEW fooview AS ' . $viewSql,
            $this->platform->getCreateViewSQL('fooview', $viewSql),
        );

        self::assertEquals('DROP VIEW fooview', $this->platform->getDropViewSQL('fooview'));
    }

    public function testGeneratesCreateUnnamedPrimaryKeySQL(): void
    {
        self::assertEquals(
            'ALTER TABLE foo ADD PRIMARY KEY (a, b)',
            $this->platform->getCreatePrimaryKeySQL(
                new Index('any_pk_name', ['a', 'b'], true, true),
                'foo',
            ),
        );
    }

    public function testGeneratesSQLSnippets(): void
    {
        self::assertEquals('CURRENT DATE', $this->platform->getCurrentDateSQL());
        self::assertEquals('CURRENT TIME', $this->platform->getCurrentTimeSQL());
        self::assertEquals('CURRENT TIMESTAMP', $this->platform->getCurrentTimestampSQL());
        self::assertEquals("'1987/05/02' + 4 DAY", $this->platform->getDateAddDaysExpression("'1987/05/02'", '4'));
        self::assertEquals("'1987/05/02' + 12 HOUR", $this->platform->getDateAddHourExpression("'1987/05/02'", '12'));

        self::assertEquals(
            "'1987/05/02' + 2 MINUTE",
            $this->platform->getDateAddMinutesExpression("'1987/05/02'", '2'),
        );

        self::assertEquals(
            "'1987/05/02' + 102 MONTH",
            $this->platform->getDateAddMonthExpression("'1987/05/02'", '102'),
        );

        self::assertEquals(
            "'1987/05/02' + (5 * 3) MONTH",
            $this->platform->getDateAddQuartersExpression("'1987/05/02'", '5'),
        );

        self::assertEquals(
            "'1987/05/02' + 1 SECOND",
            $this->platform->getDateAddSecondsExpression("'1987/05/02'", '1'),
        );

        self::assertEquals(
            "'1987/05/02' + (3 * 7) DAY",
            $this->platform->getDateAddWeeksExpression("'1987/05/02'", '3'),
        );

        self::assertEquals("'1987/05/02' + 10 YEAR", $this->platform->getDateAddYearsExpression("'1987/05/02'", '10'));

        self::assertEquals(
            "DAYS('1987/05/02') - DAYS('1987/04/01')",
            $this->platform->getDateDiffExpression("'1987/05/02'", "'1987/04/01'"),
        );

        self::assertEquals("'1987/05/02' - 4 DAY", $this->platform->getDateSubDaysExpression("'1987/05/02'", '4'));
        self::assertEquals("'1987/05/02' - 12 HOUR", $this->platform->getDateSubHourExpression("'1987/05/02'", '12'));

        self::assertEquals(
            "'1987/05/02' - 2 MINUTE",
            $this->platform->getDateSubMinutesExpression("'1987/05/02'", '2'),
        );

        self::assertEquals(
            "'1987/05/02' - 102 MONTH",
            $this->platform->getDateSubMonthExpression("'1987/05/02'", '102'),
        );

        self::assertEquals(
            "'1987/05/02' - (5 * 3) MONTH",
            $this->platform->getDateSubQuartersExpression("'1987/05/02'", '5'),
        );

        self::assertEquals(
            "'1987/05/02' - 1 SECOND",
            $this->platform->getDateSubSecondsExpression("'1987/05/02'", '1'),
        );

        self::assertEquals(
            "'1987/05/02' - (3 * 7) DAY",
            $this->platform->getDateSubWeeksExpression("'1987/05/02'", '3'),
        );

        self::assertEquals("'1987/05/02' - 10 YEAR", $this->platform->getDateSubYearsExpression("'1987/05/02'", '10'));

        self::assertEquals(
            'LOCATE(substring_column, string_column)',
            $this->platform->getLocateExpression('string_column', 'substring_column'),
        );

        self::assertEquals(
            'LOCATE(substring_column, string_column, 1)',
            $this->platform->getLocateExpression('string_column', 'substring_column', '1'),
        );

        self::assertEquals('SUBSTR(column, 5)', $this->platform->getSubstringExpression('column', '5'));
        self::assertEquals('SUBSTR(column, 5, 2)', $this->platform->getSubstringExpression('column', '5', '2'));
    }

    public function testSupportsIdentityColumns(): void
    {
        self::assertTrue($this->platform->supportsIdentityColumns());
    }

    public function testDoesNotSupportSavePoints(): void
    {
        self::assertFalse($this->platform->supportsSavepoints());
    }

    public function testDoesNotSupportReleasePoints(): void
    {
        self::assertFalse($this->platform->supportsReleaseSavepoints());
    }

    public function testGetVariableLengthStringTypeDeclarationSQLNoLength(): void
    {
        $this->expectException(InvalidColumnDeclaration::class);

        parent::testGetVariableLengthStringTypeDeclarationSQLNoLength();
    }

    public function getExpectedFixedLengthBinaryTypeDeclarationSQLNoLength(): string
    {
        return 'CHAR FOR BIT DATA';
    }

    public function getExpectedFixedLengthBinaryTypeDeclarationSQLWithLength(): string
    {
        return 'CHAR(16) FOR BIT DATA';
    }

    public function getExpectedVariableLengthBinaryTypeDeclarationSQLNoLength(): string
    {
        return 'CHAR(16) FOR BIT DATA';
    }

    public function testGetVariableLengthBinaryTypeDeclarationSQLNoLength(): void
    {
        $this->expectException(InvalidColumnDeclaration::class);

        parent::testGetVariableLengthBinaryTypeDeclarationSQLNoLength();
    }

    public function getExpectedVariableLengthBinaryTypeDeclarationSQLWithLength(): string
    {
        return 'VARCHAR(16) FOR BIT DATA';
    }

    /**
     * {@inheritDoc}
     */
    protected function getAlterTableRenameIndexSQL(): array
    {
        return ['RENAME INDEX idx_foo TO idx_bar'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedAlterTableRenameIndexSQL(): array
    {
        return [
            'RENAME INDEX "create" TO "select"',
            'RENAME INDEX "foo" TO "bar"',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getAlterTableRenameIndexInSchemaSQL(): array
    {
        return ['RENAME INDEX myschema.idx_foo TO idx_bar'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL(): array
    {
        return [
            'RENAME INDEX "schema"."create" TO "select"',
            'RENAME INDEX "schema"."foo" TO "bar"',
        ];
    }

    public function testReturnsGuidTypeDeclarationSQL(): void
    {
        self::assertSame('CHAR(36)', $this->platform->getGuidTypeDeclarationSQL([]));
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

    #[DataProvider('getGeneratesAlterColumnSQL')]
    public function testGeneratesAlterColumnSQL(
        Column $oldColumn,
        Column $newColumn,
        ?string $expectedSQLClause,
        bool $shouldReorg = true,
    ): void {
        $tableDiff = new TableDiff(new Table('foo'), changedColumns: [
            $oldColumn->getName() => new ColumnDiff($oldColumn, $newColumn),
        ]);

        $expectedSQL = [];

        if ($expectedSQLClause !== null) {
            $expectedSQL[] = 'ALTER TABLE foo ALTER COLUMN bar ' . $expectedSQLClause;
        }

        if ($shouldReorg) {
            $expectedSQL[] = "CALL SYSPROC.ADMIN_CMD ('REORG TABLE foo')";
        }

        self::assertSame($expectedSQL, $this->platform->getAlterTableSQL($tableDiff));
    }

    #[DataProvider('db2TemporaryProvider')]
    public function testGenerateTemporaryTable(
        string|null $temporary,
        string $table,
        string $expectedSQL,
    ): void {
        $table = new Table($table);
        if ($temporary !== null) {
            $table->addOption('temporary', $temporary);
        }

        if ($onCommit !== null) {
            $table->addOption('on_commit', $onCommit);
        }

        $table->addColumn('foo', Types::INTEGER);

        self::assertEquals(
            [$expectedSQL],
            $this->platform->getCreateTableSQL($table),
        );
    }

    public static function db2TemporaryProvider(): Generator
    {
        yield 'null temporary' => [null, 'mytable', 'CREATE TABLE mytable (foo INTEGER NOT NULL)'];
        yield 'empty temporary' => ['', 'mytable', 'CREATE TABLE mytable (foo INTEGER NOT NULL)'];

        yield 'created temporary' =>
        ['created', 'mytable', 'CREATE GLOBAL TEMPORARY TABLE mytable (foo INTEGER NOT NULL)'];

        yield 'declared temporary' =>
        ['declared', 'mytable', 'DECLARE GLOBAL TEMPORARY TABLE mytable (foo INTEGER NOT NULL)'];
    }

    #[DataProvider('db2InvalidTemporaryProvider')]
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

        $table->addColumn('foo', Types::INTEGER);

        $this->platform->getCreateTableSQL($table);
    }

    public static function db2InvalidTemporaryProvider(): Generator
    {
        yield 'invalid temporary specification' =>
        ['mytable', 'invalid', '', InvalidArgumentException::class, 'invalid temporary specification for table mytable'];
	}

    /** @return mixed[][] */
    public static function getGeneratesAlterColumnSQL(): iterable
    {
        return [
            [
                new Column('bar', Type::getType(Types::DECIMAL), ['columnDefinition' => 'MONEY NULL']),
                new Column('bar', Type::getType(Types::DECIMAL), ['columnDefinition' => 'MONEY NOT NULL']),
                'MONEY NOT NULL',
            ],
            [
                new Column('bar', Type::getType(Types::STRING)),
                new Column('bar', Type::getType(Types::INTEGER)),
                'SET DATA TYPE INTEGER',
            ],
            [
                new Column('bar', Type::getType(Types::STRING), ['length' => 50]),
                new Column('bar', Type::getType(Types::STRING), ['length' => 100]),
                'SET DATA TYPE VARCHAR(100)',
            ],
            [
                new Column('bar', Type::getType(Types::DECIMAL), ['precision' => 8, 'scale' => 2]),
                new Column('bar', Type::getType(Types::DECIMAL), ['precision' => 10, 'scale' => 2]),
                'SET DATA TYPE NUMERIC(10, 2)',
            ],
            [
                new Column('bar', Type::getType(Types::DECIMAL), ['precision' => 5, 'scale' => 3]),
                new Column('bar', Type::getType(Types::DECIMAL), ['precision' => 5, 'scale' => 4]),
                'SET DATA TYPE NUMERIC(5, 4)',
            ],
            [
                new Column('bar', Type::getType(Types::STRING), ['length' => 10, 'fixed' => true]),
                new Column('bar', Type::getType(Types::STRING), ['length' => 20, 'fixed' => true]),
                'SET DATA TYPE CHAR(20)',
            ],
            [
                new Column('bar', Type::getType(Types::STRING), ['notnull' => false]),
                new Column('bar', Type::getType(Types::STRING), ['notnull' => true]),
                'SET NOT NULL',
            ],
            [
                new Column('bar', Type::getType(Types::STRING), ['notnull' => true]),
                new Column('bar', Type::getType(Types::STRING), ['notnull' => false]),
                'DROP NOT NULL',
            ],
            [
                new Column('bar', Type::getType(Types::STRING)),
                new Column('bar', Type::getType(Types::STRING), ['default' => 'foo']),
                "SET DEFAULT 'foo'",
            ],
            [
                new Column('bar', Type::getType(Types::INTEGER)),
                new Column('bar', Type::getType(Types::INTEGER), ['autoincrement' => true, 'default' => 666]),
                null,
                false,
            ],
            [
                new Column('bar', Type::getType(Types::STRING), ['default' => 'foo']),
                new Column('bar', Type::getType(Types::STRING)),
                'DROP DEFAULT',
            ],
        ];
    }

    protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL(): string
    {
        return 'CONSTRAINT "select" UNIQUE (foo)';
    }

    protected function getQuotesReservedKeywordInIndexDeclarationSQL(): string
    {
        return ''; // not supported by this platform
    }

    protected function getQuotesReservedKeywordInTruncateTableSQL(): string
    {
        return 'TRUNCATE "select" IMMEDIATE';
    }

    protected function supportsInlineIndexDeclaration(): bool
    {
        return false;
    }

    protected function supportsCommentOnStatement(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function getAlterStringToFixedStringSQL(): array
    {
        return [
            'ALTER TABLE mytable ALTER COLUMN name SET DATA TYPE CHAR(2)',
            'CALL SYSPROC.ADMIN_CMD (\'REORG TABLE mytable\')',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(): array
    {
        return ['RENAME INDEX idx_foo TO idx_foo_renamed'];
    }
}
