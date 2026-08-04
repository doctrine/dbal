<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLite;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\Deferrability;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;

use function count;
use function implode;

/** @extends AbstractPlatformTestCase<SQLitePlatform> */
class SQLitePlatformTest extends AbstractPlatformTestCase
{
    use VerifyDeprecations;

    public function createPlatform(): AbstractPlatform
    {
        return new SQLitePlatform();
    }

    protected function createComparator(): Comparator
    {
        return new SQLite\Comparator($this->platform, new ComparatorConfig());
    }

    public function getGenerateTableSql(): string
    {
        return 'CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, test VARCHAR(255) DEFAULT NULL)';
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

    public function testGeneratesSqlSnippets(): void
    {
        self::assertEquals('REGEXP', $this->platform->getRegexpExpression());
        self::assertEquals('SUBSTR(column, 5)', $this->platform->getSubstringExpression('column', '5'));
        self::assertEquals('SUBSTR(column, 0, 5)', $this->platform->getSubstringExpression('column', '0', '5'));
    }

    public function testGeneratesTransactionCommands(): void
    {
        self::assertEquals(
            'PRAGMA read_uncommitted = 0',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_UNCOMMITTED),
        );
        self::assertEquals(
            'PRAGMA read_uncommitted = 1',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_COMMITTED),
        );
        self::assertEquals(
            'PRAGMA read_uncommitted = 1',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::REPEATABLE_READ),
        );
        self::assertEquals(
            'PRAGMA read_uncommitted = 1',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::SERIALIZABLE),
        );
    }

    public function testIgnoresUnsignedIntegerDeclarationForAutoIncrementalIntegers(): void
    {
        self::assertSame(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getIntegerTypeDeclarationSQL(['autoincrement' => true, 'unsigned' => true]),
        );
    }

    public function testGeneratesTypeDeclarationForSmallIntegers(): void
    {
        self::assertEquals(
            'SMALLINT',
            $this->platform->getSmallIntTypeDeclarationSQL([]),
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getSmallIntTypeDeclarationSQL(['autoincrement' => true]),
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getSmallIntTypeDeclarationSQL(
                ['autoincrement' => true, 'primary' => true],
            ),
        );
        self::assertEquals(
            'SMALLINT',
            $this->platform->getSmallIntTypeDeclarationSQL(['unsigned' => false]),
        );
        self::assertEquals(
            'SMALLINT UNSIGNED',
            $this->platform->getSmallIntTypeDeclarationSQL(['unsigned' => true]),
        );
    }

    public function testGeneratesTypeDeclarationForIntegers(): void
    {
        self::assertEquals(
            'INTEGER',
            $this->platform->getIntegerTypeDeclarationSQL([]),
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getIntegerTypeDeclarationSQL(['autoincrement' => true]),
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getIntegerTypeDeclarationSQL(['autoincrement' => true, 'unsigned' => true]),
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getIntegerTypeDeclarationSQL(
                ['autoincrement' => true, 'primary' => true],
            ),
        );
        self::assertEquals(
            'INTEGER',
            $this->platform->getIntegerTypeDeclarationSQL(['unsigned' => false]),
        );
        self::assertEquals(
            'INTEGER UNSIGNED',
            $this->platform->getIntegerTypeDeclarationSQL(['unsigned' => true]),
        );
    }

    public function testGeneratesTypeDeclarationForBigIntegers(): void
    {
        self::assertEquals(
            'BIGINT',
            $this->platform->getBigIntTypeDeclarationSQL([]),
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getBigIntTypeDeclarationSQL(['autoincrement' => true]),
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getBigIntTypeDeclarationSQL(['autoincrement' => true, 'unsigned' => true]),
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getBigIntTypeDeclarationSQL(
                ['autoincrement' => true, 'primary' => true],
            ),
        );
        self::assertEquals(
            'BIGINT',
            $this->platform->getBigIntTypeDeclarationSQL(['unsigned' => false]),
        );
        self::assertEquals(
            'BIGINT UNSIGNED',
            $this->platform->getBigIntTypeDeclarationSQL(['unsigned' => true]),
        );
    }

    public function getGenerateIndexSql(): string
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateUniqueIndexSql(): string
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    public function testGeneratesIndexCreationSqlWithSchema(): void
    {
        $indexDef = new Index('i', ['a', 'b']);

        self::assertSame(
            'CREATE INDEX main.i ON mytable (a, b)',
            $this->platform->getCreateIndexSQL($indexDef, 'main.mytable'),
        );
    }

    public function testGeneratesPrimaryIndexCreationSqlWithSchema(): void
    {
        $primaryIndexDef = new Index('i2', ['a', 'b'], false, true);

        self::assertSame(
            'TEST: main.mytable, i2 - a, b',
            (new class () extends SqlitePlatform {
                public function getCreatePrimaryKeySQL(Index $index, string $table): string
                {
                    return 'TEST: ' . $table . ', ' . $index->getObjectName()->toString()
                        . ' - ' . implode(', ', $index->getColumns());
                }
            })->getCreateIndexSQL($primaryIndexDef, 'main.mytable'),
        );
    }

    public function testGeneratesForeignKeyCreationSql(): void
    {
        $this->expectException(Exception::class);

        parent::testGeneratesForeignKeyCreationSql();
    }

    protected function getGenerateForeignKeySql(): string
    {
        self::fail('Foreign key constraints are not yet supported for SQLite.');
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

    public function testModifyLimitQueryWithOffsetAndEmptyLimit(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', null, 10);
        self::assertEquals('SELECT * FROM user LIMIT -1 OFFSET 10', $sql);
    }

    public function testGenerateTableSqlShouldNotAutoQuotePrimaryKey(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setQuotedName('like')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setQuotedColumnNames('like')
                    ->create(),
            )
            ->create();

        $createTableSQL = $this->platform->getCreateTableSQL($table);
        self::assertEquals(
            'CREATE TABLE test ("like" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL)',
            $createTableSQL[0],
        );
    }

    public function testRenameNonExistingColumn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $tableDiff = new TableDiff($table, changedColumns: [
            'value' => new ColumnDiff(
                Column::editor()
                    ->setUnquotedName('data')
                    ->setTypeName(Types::STRING)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('value')
                    ->setTypeName(Types::STRING)
                    ->create(),
            ),
        ]);

        $this->expectException(Exception::class);
        $this->platform->getAlterTableSQL($tableDiff);
    }

    public function testCreateTableWithDeferredForeignKeys(): void
    {
        $table = Table::editor()
            ->setUnquotedName('user')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('article')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('post')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('parent')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('article')
                    ->setUnquotedReferencedTableName('article')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setDeferrability(Deferrability::DEFERRABLE)
                    ->create(),
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('post')
                    ->setUnquotedReferencedTableName('post')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setDeferrability(Deferrability::DEFERRED)
                    ->create(),
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('parent')
                    ->setUnquotedReferencedTableName('user')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setDeferrability(Deferrability::DEFERRED)
                    ->create(),
            )
            ->create();

        $sql = [
            'CREATE TABLE user ('
                . 'id INTEGER NOT NULL, article INTEGER NOT NULL, post INTEGER NOT NULL, parent INTEGER NOT NULL'
                . ', PRIMARY KEY (id)'
                . ', FOREIGN KEY (article)'
                . ' REFERENCES article (id) DEFERRABLE INITIALLY IMMEDIATE'
                . ', FOREIGN KEY (post)'
                . ' REFERENCES post (id) DEFERRABLE INITIALLY DEFERRED'
                . ', FOREIGN KEY (parent)'
                . ' REFERENCES user (id) DEFERRABLE INITIALLY DEFERRED'
                . ')',
            'CREATE INDEX IDX_8D93D64923A0E66 ON user (article)',
            'CREATE INDEX IDX_8D93D6495A8A6C8D ON user (post)',
            'CREATE INDEX IDX_8D93D6493D8E604F ON user (parent)',
        ];

        self::assertEquals($sql, $this->platform->getCreateTableSQL($table));
    }

    public function testAlterTable(): void
    {
        $table = Table::editor()
            ->setUnquotedName('user')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('article')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('post')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('parent')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('index1')
                    ->setUnquotedColumnNames('article', 'post')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('article')
                    ->setUnquotedReferencedTableName('article')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setDeferrability(Deferrability::DEFERRABLE)
                    ->create(),
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('post')
                    ->setUnquotedReferencedTableName('post')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setDeferrability(Deferrability::DEFERRED)
                    ->create(),
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('parent')
                    ->setUnquotedReferencedTableName('user')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setDeferrability(Deferrability::DEFERRED)
                    ->create(),
            )
            ->create();

        $diff = new TableDiff(
            $table,
            changedColumns: [
                'id' => new ColumnDiff(
                    $table->getColumn('id'),
                    Column::editor()
                        ->setUnquotedName('key')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                ),
                'post' => new ColumnDiff(
                    $table->getColumn('post'),
                    Column::editor()
                        ->setUnquotedName('comment')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                ),
            ],
            droppedColumns: [
                Column::editor()
                    ->setUnquotedName('parent')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            ],
            droppedIndexes: [
                $table->getIndex('index1'),
            ],
        );

        $sql = [
            'CREATE TEMPORARY TABLE __temp__user AS SELECT id, article, post FROM user',
            'DROP TABLE user',
            'CREATE TABLE user ('
                . '"key" INTEGER NOT NULL, article INTEGER NOT NULL, comment INTEGER NOT NULL'
                . ', PRIMARY KEY ("key")'
                . ', FOREIGN KEY (article)'
                . ' REFERENCES article (id) DEFERRABLE INITIALLY IMMEDIATE'
                . ', FOREIGN KEY (comment)'
                . ' REFERENCES post (id) DEFERRABLE INITIALLY DEFERRED'
                . ')',
            'INSERT INTO user ("key", article, comment) SELECT id, article, post FROM __temp__user',
            'DROP TABLE __temp__user',
            'CREATE INDEX IDX_8D93D64923A0E66 ON user (article)',
            'CREATE INDEX IDX_8D93D6495A8A6C8D ON user (comment)',
        ];

        self::assertEquals($sql, $this->platform->getAlterTableSQL($diff));
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedColumnInPrimaryKeySQL(): array
    {
        return ['CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, PRIMARY KEY ("create"))'];
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
            'CREATE TABLE "quoted" (' .
            '"create" VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, "bar" VARCHAR(255) NOT NULL, ' .
            'CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") ' .
            'REFERENCES "foreign" ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE, ' .
            'CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") ' .
            'REFERENCES foo ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE, ' .
            'CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar") ' .
            'REFERENCES "foo-bar" ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE)',
            'CREATE INDEX IDX_22660D028FD6E0FB8C7365216D704F76 ON "quoted" ("create", foo, "bar")',
        ];
    }

    public function getExpectedFixedLengthBinaryTypeDeclarationSQLNoLength(): string
    {
        return 'BLOB';
    }

    public function getExpectedFixedLengthBinaryTypeDeclarationSQLWithLength(): string
    {
        return 'BLOB';
    }

    public function getExpectedVariableLengthBinaryTypeDeclarationSQLNoLength(): string
    {
        return 'BLOB';
    }

    public function getExpectedVariableLengthBinaryTypeDeclarationSQLWithLength(): string
    {
        return 'BLOB';
    }

    /**
     * {@inheritDoc}
     */
    protected function getAlterTableRenameIndexSQL(): array
    {
        return [
            'CREATE TEMPORARY TABLE __temp__mytable AS SELECT id FROM mytable',
            'DROP TABLE mytable',
            'CREATE TABLE mytable (id INTEGER NOT NULL, PRIMARY KEY (id))',
            'INSERT INTO mytable (id) SELECT id FROM __temp__mytable',
            'DROP TABLE __temp__mytable',
            'CREATE INDEX idx_bar ON mytable (id)',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedAlterTableRenameIndexSQL(): array
    {
        return [
            'CREATE TEMPORARY TABLE __temp__table AS SELECT id FROM "table"',
            'DROP TABLE "table"',
            'CREATE TABLE "table" (id INTEGER NOT NULL, PRIMARY KEY (id))',
            'INSERT INTO "table" (id) SELECT id FROM __temp__table',
            'DROP TABLE __temp__table',
            'CREATE INDEX "select" ON "table" (id)',
            'CREATE INDEX "bar" ON "table" (id)',
        ];
    }

    public function testAlterTableRenameIndexInSchema(): void
    {
        self::markTestIncomplete(
            'Test currently produces broken SQL due to SQLitePlatform::getAlterTable() being broken ' .
            'when used with schemas.',
        );
    }

    public function testQuotesAlterTableRenameIndexInSchema(): void
    {
        self::markTestIncomplete(
            'Test currently produces broken SQL due to SQLitePlatform::getAlterTable() being broken ' .
            'when used with schemas.',
        );
    }

    public function testReturnsGuidTypeDeclarationSQL(): void
    {
        self::assertSame('CHAR(36)', $this->platform->getGuidTypeDeclarationSQL([]));
    }

    public function testGeneratesAlterTableRenameColumnSQLWithSchema(): void
    {
        $table = Table::editor()
            ->setUnquotedName('t', 'main')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('a')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $tableDiff = new TableDiff($table, changedColumns: [
            'a' => new ColumnDiff(
                Column::editor()
                    ->setUnquotedName('a')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('b')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            ),
        ]);

        self::assertSame([
            'CREATE TEMPORARY TABLE __temp__t AS SELECT a FROM main.t',
            'DROP TABLE main.t',
            'CREATE TABLE main.t (b INTEGER NOT NULL)',
            'INSERT INTO main.t (b) SELECT a FROM __temp__t',
            'DROP TABLE __temp__t',
        ], $this->platform->getAlterTableSQL($tableDiff));
    }

    #[DataProvider('addedColumnWithCurrentDefaultProvider')]
    public function testAddedDateTimeColumnWithCurrentDefaultTriggersTableRebuild(
        string $typeName,
        string $default,
    ): void {
        // Rendering the legacy string CURRENT_* default is deprecated.
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7195');

        $table = Table::editor()
            ->setUnquotedName('t')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $tableDiff = new TableDiff($table, addedColumns: [
            Column::editor()
                ->setUnquotedName('c')
                ->setTypeName($typeName)
                ->setDefaultValue($default)
                ->create(),
        ]);

        // A CURRENT_* default forces the table-rebuild strategy on SQLite, because
        // ALTER TABLE ... ADD COLUMN cannot use a non-constant default. The rebuild emits
        // several statements, whereas a simple column addition would be a single one.
        self::assertGreaterThan(1, count($this->platform->getAlterTableSQL($tableDiff)));
    }

    /**
     * Covers the mutable types and, as a regression guard, their immutable siblings, which share
     * the same database representation and must be treated identically here.
     *
     * @return array<string, array{string, string}>
     */
    public static function addedColumnWithCurrentDefaultProvider(): iterable
    {
        $platform = new SQLitePlatform();

        return [
            'datetime' => [Types::DATETIME_MUTABLE, $platform->getCurrentTimestampSQL()],
            'datetime_immutable' => [Types::DATETIME_IMMUTABLE, $platform->getCurrentTimestampSQL()],
            'date' => [Types::DATE_MUTABLE, $platform->getCurrentDateSQL()],
            'date_immutable' => [Types::DATE_IMMUTABLE, $platform->getCurrentDateSQL()],
            'time' => [Types::TIME_MUTABLE, $platform->getCurrentTimeSQL()],
            'time_immutable' => [Types::TIME_IMMUTABLE, $platform->getCurrentTimeSQL()],
        ];
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

    protected static function getInlineColumnCommentDelimiter(): string
    {
        return "\n";
    }

    protected static function getInlineColumnRegularCommentSQL(): string
    {
        return "--Regular comment\n";
    }

    protected static function getInlineColumnCommentRequiringEscapingSQL(): string
    {
        return "--Using inline comment delimiter \n-- works\n";
    }

    protected static function getInlineColumnEmptyCommentSQL(): string
    {
        return '';
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
        return 'DELETE FROM "select"';
    }

    /**
     * {@inheritDoc}
     */
    protected function getAlterStringToFixedStringSQL(): array
    {
        return [
            'CREATE TEMPORARY TABLE __temp__mytable AS SELECT name FROM mytable',
            'DROP TABLE mytable',
            'CREATE TABLE mytable (name CHAR(2) NOT NULL)',
            'INSERT INTO mytable (name) SELECT name FROM __temp__mytable',
            'DROP TABLE __temp__mytable',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(): array
    {
        return [
            'CREATE TEMPORARY TABLE __temp__mytable AS SELECT foo, bar, baz FROM mytable',
            'DROP TABLE mytable',
            'CREATE TABLE mytable (foo INTEGER NOT NULL, bar INTEGER NOT NULL, baz INTEGER NOT NULL, '
                . 'CONSTRAINT fk_foo FOREIGN KEY (foo) REFERENCES foreign_table (id)'
                . ' NOT DEFERRABLE INITIALLY IMMEDIATE, '
                . 'CONSTRAINT fk_bar FOREIGN KEY (bar) REFERENCES foreign_table (id)'
                . ' NOT DEFERRABLE INITIALLY IMMEDIATE)',
            'INSERT INTO mytable (foo, bar, baz) SELECT foo, bar, baz FROM __temp__mytable',
            'DROP TABLE __temp__mytable',
            'CREATE INDEX idx_bar ON mytable (bar)',
            'CREATE INDEX idx_foo_renamed ON mytable (foo)',
        ];
    }

    public function testQuotesDropForeignKeySQL(): void
    {
        self::markTestSkipped('SQLite does not support altering foreign key constraints.');
    }

    public function testDateAddStaticNumberOfDays(): void
    {
        self::assertSame(
            "DATETIME(rentalBeginsOn,'+' || 12 || ' DAY')",
            $this->platform->getDateAddDaysExpression('rentalBeginsOn', '12'),
        );
    }

    public function testDateAddNumberOfDaysFromColumn(): void
    {
        self::assertSame(
            "DATETIME(rentalBeginsOn,'+' || duration || ' DAY')",
            $this->platform->getDateAddDaysExpression('rentalBeginsOn', 'duration'),
        );
    }

    public function testSupportsColumnCollation(): void
    {
        self::assertTrue($this->platform->supportsColumnCollation());
    }

    public function testGetCreateTableSQLWithColumnCollation(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('no_collation')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('column_collation')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setCollation('NOCASE')
                    ->create(),
            )
            ->create();

        self::assertSame(
            [
                'CREATE TABLE foo (no_collation VARCHAR(255) NOT NULL, '
                    . 'column_collation VARCHAR(255) NOT NULL COLLATE "NOCASE")',
            ],
            $this->platform->getCreateTableSQL($table),
        );
    }

    public function testCreateTableWithNonPrimaryKeyAutoIncrementColumn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_autoincrement')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->create();

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6849');
        $this->platform->getCreateTableSQL($table);
    }

    public function testCreateTableWithCompositePrimaryKeyAutoIncrementColumn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_autoincrement')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id1')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('id2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id1', 'id2')
                    ->create(),
            )
            ->create();

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6849');
        $this->platform->getCreateTableSQL($table);
    }
}
