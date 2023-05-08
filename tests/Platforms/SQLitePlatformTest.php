<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLite;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

/** @extends AbstractPlatformTestCase<SQLitePlatform> */
class SQLitePlatformTest extends AbstractPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new SQLitePlatform();
    }

    protected function createComparator(): Comparator
    {
        return new SQLite\Comparator($this->platform);
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
        $table = new Table('test');
        $table->addColumn('"like"', Types::INTEGER, ['notnull' => true, 'autoincrement' => true]);
        $table->setPrimaryKey(['"like"']);

        $createTableSQL = $this->platform->getCreateTableSQL($table);
        self::assertEquals(
            'CREATE TABLE test ("like" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL)',
            $createTableSQL[0],
        );
    }

    public function testAlterTableAddColumns(): void
    {
        $table = new Table('user');

        $diff = new TableDiff($table, [
            new Column('foo', Type::getType(Types::STRING)),
            new Column('count', Type::getType(Types::INTEGER), [
                'notnull' => false,
                'default' => 1,
            ]),
        ], [], [], [], [], [], [], [], [], [], []);

        $expected = [
            'ALTER TABLE user ADD COLUMN foo VARCHAR NOT NULL',
            'ALTER TABLE user ADD COLUMN count INTEGER DEFAULT 1',
        ];

        self::assertEquals($expected, $this->platform->getAlterTableSQL($diff));
    }

    public function testRenameNonExistingColumn(): void
    {
        $table = new Table('test');
        $table->addColumn('id', Types::INTEGER);

        $tableDiff = new TableDiff($table, [], [], [], [
            'value' => new Column('data', Type::getType(Types::STRING)),
        ], [], [], [], [], [], [], []);

        $this->expectException(Exception::class);
        $this->platform->getAlterTableSQL($tableDiff);
    }

    public function testCreateTableWithDeferredForeignKeys(): void
    {
        $table = new Table('user');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('article', Types::INTEGER);
        $table->addColumn('post', Types::INTEGER);
        $table->addColumn('parent', Types::INTEGER);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint('article', ['article'], ['id'], ['deferrable' => true]);
        $table->addForeignKeyConstraint('post', ['post'], ['id'], ['deferred' => true]);
        $table->addForeignKeyConstraint('user', ['parent'], ['id'], ['deferrable' => true, 'deferred' => true]);

        $sql = [
            'CREATE TABLE user ('
                . 'id INTEGER NOT NULL, article INTEGER NOT NULL, post INTEGER NOT NULL, parent INTEGER NOT NULL'
                . ', PRIMARY KEY(id)'
                . ', CONSTRAINT FK_8D93D64923A0E66 FOREIGN KEY (article)'
                . ' REFERENCES article (id) DEFERRABLE INITIALLY IMMEDIATE'
                . ', CONSTRAINT FK_8D93D6495A8A6C8D FOREIGN KEY (post)'
                . ' REFERENCES post (id) NOT DEFERRABLE INITIALLY DEFERRED'
                . ', CONSTRAINT FK_8D93D6493D8E604F FOREIGN KEY (parent)'
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
        $table = new Table('user');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('article', Types::INTEGER);
        $table->addColumn('post', Types::INTEGER);
        $table->addColumn('parent', Types::INTEGER);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint('article', ['article'], ['id'], ['deferrable' => true]);
        $table->addForeignKeyConstraint('post', ['post'], ['id'], ['deferred' => true]);
        $table->addForeignKeyConstraint('user', ['parent'], ['id'], ['deferrable' => true, 'deferred' => true]);
        $table->addIndex(['article', 'post'], 'index1');

        $diff = new TableDiff($table, [], [], [
            new Column('parent', Type::getType(Types::INTEGER), []),
        ], [
            'id' => new Column('key', Type::getType(Types::INTEGER), []),
            'post' => new Column('comment', Type::getType(Types::INTEGER), []),
        ], [], [], [
            $table->getIndex('index1'),
        ], [], [], [], []);

        $sql = [
            'CREATE TEMPORARY TABLE __temp__user AS SELECT id, article, post FROM user',
            'DROP TABLE user',
            'CREATE TABLE user ('
                . '"key" INTEGER NOT NULL, article INTEGER NOT NULL, comment INTEGER NOT NULL'
                . ', PRIMARY KEY("key")'
                . ', CONSTRAINT FK_8D93D64923A0E66 FOREIGN KEY (article)'
                . ' REFERENCES article (id) DEFERRABLE INITIALLY IMMEDIATE'
                . ', CONSTRAINT FK_8D93D6495A8A6C8D FOREIGN KEY (comment)'
                . ' REFERENCES post (id) NOT DEFERRABLE INITIALLY DEFERRED'
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
            'CREATE TABLE "quoted" (' .
            '"create" VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, "bar" VARCHAR(255) NOT NULL, ' .
            'CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") ' .
            'REFERENCES "foreign" ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE, ' .
            'CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") ' .
            'REFERENCES foo ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE, ' .
            'CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar") ' .
            'REFERENCES "foo-bar" ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE)',
            'CREATE INDEX IDX_22660D028FD6E0FB8C736521D79164E3 ON "quoted" ("create", foo, "bar")',
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
            'CREATE TABLE mytable (id INTEGER NOT NULL, PRIMARY KEY(id))',
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
            'CREATE TABLE "table" (id INTEGER NOT NULL, PRIMARY KEY(id))',
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
        $table = new Table('foo');
        $table->addColumn('no_collation', Types::STRING, ['length' => 255]);
        $table->addColumn(
            'column_collation',
            Types::STRING,
            ['length' => 255],
        )->setPlatformOption('collation', 'NOCASE');

        self::assertSame(
            [
                'CREATE TABLE foo (no_collation VARCHAR(255) NOT NULL, '
                    . 'column_collation VARCHAR(255) NOT NULL COLLATE "NOCASE")',
            ],
            $this->platform->getCreateTableSQL($table),
        );
    }
}
