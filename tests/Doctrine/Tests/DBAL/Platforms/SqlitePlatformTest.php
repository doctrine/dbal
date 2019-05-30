<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Type;

class SqlitePlatformTest extends AbstractPlatformTestCase
{
    public function createPlatform() : AbstractPlatform
    {
        return new SqlitePlatform();
    }

    public function getGenerateTableSql() : string
    {
        return 'CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, test VARCHAR(255) DEFAULT NULL)';
    }

    /**
     * {@inheritDoc}
     */
    public function getGenerateTableWithMultiColumnUniqueIndexSql() : array
    {
        return [
            'CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL)',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)',
        ];
    }

    public function testGeneratesSqlSnippets() : void
    {
        self::assertEquals('REGEXP', $this->platform->getRegexpExpression(), 'Regular expression operator is not correct');
        self::assertEquals('SUBSTR(column, 5, LENGTH(column))', $this->platform->getSubstringExpression('column', 5), 'Substring expression without length is not correct');
        self::assertEquals('SUBSTR(column, 0, 5)', $this->platform->getSubstringExpression('column', 0, 5), 'Substring expression with length is not correct');
    }

    public function testGeneratesTransactionCommands() : void
    {
        self::assertEquals(
            'PRAGMA read_uncommitted = 0',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_UNCOMMITTED)
        );
        self::assertEquals(
            'PRAGMA read_uncommitted = 1',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_COMMITTED)
        );
        self::assertEquals(
            'PRAGMA read_uncommitted = 1',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::REPEATABLE_READ)
        );
        self::assertEquals(
            'PRAGMA read_uncommitted = 1',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::SERIALIZABLE)
        );
    }

    public function testPrefersIdentityColumns() : void
    {
        self::assertTrue($this->platform->prefersIdentityColumns());
    }

    public function testIgnoresUnsignedIntegerDeclarationForAutoIncrementalIntegers() : void
    {
        self::assertSame(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getIntegerTypeDeclarationSQL(['autoincrement' => true, 'unsigned' => true])
        );
    }

    /**
     * @group DBAL-752
     * @group DBAL-924
     */
    public function testGeneratesTypeDeclarationForTinyIntegers() : void
    {
        self::assertEquals(
            'TINYINT',
            $this->platform->getTinyIntTypeDeclarationSQL([])
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getTinyIntTypeDeclarationSQL(['autoincrement' => true])
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getTinyIntTypeDeclarationSQL(
                ['autoincrement' => true, 'primary' => true]
            )
        );
        self::assertEquals(
            'TINYINT',
            $this->platform->getTinyIntTypeDeclarationSQL(['unsigned' => false])
        );
        self::assertEquals(
            'TINYINT UNSIGNED',
            $this->platform->getTinyIntTypeDeclarationSQL(['unsigned' => true])
        );
    }

    /**
     * @group DBAL-752
     * @group DBAL-924
     */
    public function testGeneratesTypeDeclarationForSmallIntegers() : void
    {
        self::assertEquals(
            'SMALLINT',
            $this->platform->getSmallIntTypeDeclarationSQL([])
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getSmallIntTypeDeclarationSQL(['autoincrement' => true])
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getTinyIntTypeDeclarationSQL(['autoincrement' => true, 'unsigned' => true])
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getSmallIntTypeDeclarationSQL(
                ['autoincrement' => true, 'primary' => true]
            )
        );
        self::assertEquals(
            'SMALLINT',
            $this->platform->getSmallIntTypeDeclarationSQL(['unsigned' => false])
        );
        self::assertEquals(
            'SMALLINT UNSIGNED',
            $this->platform->getSmallIntTypeDeclarationSQL(['unsigned' => true])
        );
    }

    /**
     * @group DBAL-752
     * @group DBAL-924
     */
    public function testGeneratesTypeDeclarationForMediumIntegers() : void
    {
        self::assertEquals(
            'MEDIUMINT',
            $this->platform->getMediumIntTypeDeclarationSQL([])
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getMediumIntTypeDeclarationSQL(['autoincrement' => true])
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getMediumIntTypeDeclarationSQL(['autoincrement' => true, 'unsigned' => true])
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getMediumIntTypeDeclarationSQL(
                ['autoincrement' => true, 'primary' => true]
            )
        );
        self::assertEquals(
            'MEDIUMINT',
            $this->platform->getMediumIntTypeDeclarationSQL(['unsigned' => false])
        );
        self::assertEquals(
            'MEDIUMINT UNSIGNED',
            $this->platform->getMediumIntTypeDeclarationSQL(['unsigned' => true])
        );
    }

    public function testGeneratesTypeDeclarationForIntegers() : void
    {
        self::assertEquals(
            'INTEGER',
            $this->platform->getIntegerTypeDeclarationSQL([])
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getIntegerTypeDeclarationSQL(['autoincrement' => true])
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getIntegerTypeDeclarationSQL(['autoincrement' => true, 'unsigned' => true])
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getIntegerTypeDeclarationSQL(
                ['autoincrement' => true, 'primary' => true]
            )
        );
        self::assertEquals(
            'INTEGER',
            $this->platform->getIntegerTypeDeclarationSQL(['unsigned' => false])
        );
        self::assertEquals(
            'INTEGER UNSIGNED',
            $this->platform->getIntegerTypeDeclarationSQL(['unsigned' => true])
        );
    }

    /**
     * @group DBAL-752
     * @group DBAL-924
     */
    public function testGeneratesTypeDeclarationForBigIntegers() : void
    {
        self::assertEquals(
            'BIGINT',
            $this->platform->getBigIntTypeDeclarationSQL([])
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getBigIntTypeDeclarationSQL(['autoincrement' => true])
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getBigIntTypeDeclarationSQL(['autoincrement' => true, 'unsigned' => true])
        );
        self::assertEquals(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $this->platform->getBigIntTypeDeclarationSQL(
                ['autoincrement' => true, 'primary' => true]
            )
        );
        self::assertEquals(
            'BIGINT',
            $this->platform->getBigIntTypeDeclarationSQL(['unsigned' => false])
        );
        self::assertEquals(
            'BIGINT UNSIGNED',
            $this->platform->getBigIntTypeDeclarationSQL(['unsigned' => true])
        );
    }

    public function testGeneratesTypeDeclarationForStrings() : void
    {
        self::assertEquals(
            'CHAR(10)',
            $this->platform->getVarcharTypeDeclarationSQL(
                ['length' => 10, 'fixed' => true]
            )
        );
        self::assertEquals(
            'VARCHAR(50)',
            $this->platform->getVarcharTypeDeclarationSQL(['length' => 50]),
            'Variable string declaration is not correct'
        );
        self::assertEquals(
            'VARCHAR(255)',
            $this->platform->getVarcharTypeDeclarationSQL([]),
            'Long string declaration is not correct'
        );
    }

    public function getGenerateIndexSql() : string
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateUniqueIndexSql() : string
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    public function testGeneratesForeignKeyCreationSql() : void
    {
        $this->expectException(DBALException::class);

        parent::testGeneratesForeignKeyCreationSql();
    }

    public function testGeneratesConstraintCreationSql() : void
    {
        $this->expectException(DBALException::class);

        parent::testGeneratesConstraintCreationSql();
    }

    public function getGenerateForeignKeySql() : string
    {
        return null;
    }

    public function testModifyLimitQuery() : void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        self::assertEquals('SELECT * FROM user LIMIT 10', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset() : void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10);
        self::assertEquals('SELECT * FROM user LIMIT 10', $sql);
    }

    public function testModifyLimitQueryWithOffsetAndEmptyLimit() : void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', null, 10);
        self::assertEquals('SELECT * FROM user LIMIT -1 OFFSET 10', $sql);
    }

    /**
     * {@inheritDoc}
     */
    public function getGenerateAlterTableSql() : array
    {
        return [
            'CREATE TEMPORARY TABLE __temp__mytable AS SELECT id, bar, bloo FROM mytable',
            'DROP TABLE mytable',
            "CREATE TABLE mytable (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, baz VARCHAR(255) DEFAULT 'def' NOT NULL, bloo BOOLEAN DEFAULT '0' NOT NULL, quota INTEGER DEFAULT NULL)",
            'INSERT INTO mytable (id, baz, bloo) SELECT id, bar, bloo FROM __temp__mytable',
            'DROP TABLE __temp__mytable',
            'ALTER TABLE mytable RENAME TO userlist',
        ];
    }

    /**
     * @group DDC-1845
     */
    public function testGenerateTableSqlShouldNotAutoQuotePrimaryKey() : void
    {
        $table = new Table('test');
        $table->addColumn('"like"', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->setPrimaryKey(['"like"']);

        $createTableSQL = $this->platform->getCreateTableSQL($table);
        self::assertEquals(
            'CREATE TABLE test ("like" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL)',
            $createTableSQL[0]
        );
    }

    public function testAlterTableAddColumns() : void
    {
        $diff                        = new TableDiff('user');
        $diff->addedColumns['foo']   = new Column('foo', Type::getType('string'));
        $diff->addedColumns['count'] = new Column('count', Type::getType('integer'), ['notnull' => false, 'default' => 1]);

        $expected = [
            'ALTER TABLE user ADD COLUMN foo VARCHAR(255) NOT NULL',
            'ALTER TABLE user ADD COLUMN count INTEGER DEFAULT 1',
        ];

        self::assertEquals($expected, $this->platform->getAlterTableSQL($diff));
    }

    /**
     * @dataProvider complexDiffProvider
     */
    public function testAlterTableAddComplexColumns(TableDiff $diff) : void
    {
        $this->expectException(DBALException::class);

        $this->platform->getAlterTableSQL($diff);
    }

    /**
     * @return mixed[][]
     */
    public static function complexDiffProvider() : iterable
    {
        $date                       = new TableDiff('user');
        $date->addedColumns['time'] = new Column('time', Type::getType('date'), ['default' => 'CURRENT_DATE']);

        $id                     = new TableDiff('user');
        $id->addedColumns['id'] = new Column('id', Type::getType('integer'), ['autoincrement' => true]);

        return [
            'date column with default value' => [$date],
            'id column with auto increment'  => [$id],
        ];
    }

    public function testCreateTableWithDeferredForeignKeys() : void
    {
        $table = new Table('user');
        $table->addColumn('id', 'integer');
        $table->addColumn('article', 'integer');
        $table->addColumn('post', 'integer');
        $table->addColumn('parent', 'integer');
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint('article', ['article'], ['id'], ['deferrable' => true]);
        $table->addForeignKeyConstraint('post', ['post'], ['id'], ['deferred' => true]);
        $table->addForeignKeyConstraint('user', ['parent'], ['id'], ['deferrable' => true, 'deferred' => true]);

        $sql = [
            'CREATE TABLE user ('
                . 'id INTEGER NOT NULL, article INTEGER NOT NULL, post INTEGER NOT NULL, parent INTEGER NOT NULL'
                . ', PRIMARY KEY(id)'
                . ', CONSTRAINT FK_8D93D64923A0E66 FOREIGN KEY (article) REFERENCES article (id) DEFERRABLE INITIALLY IMMEDIATE'
                . ', CONSTRAINT FK_8D93D6495A8A6C8D FOREIGN KEY (post) REFERENCES post (id) NOT DEFERRABLE INITIALLY DEFERRED'
                . ', CONSTRAINT FK_8D93D6493D8E604F FOREIGN KEY (parent) REFERENCES user (id) DEFERRABLE INITIALLY DEFERRED'
                . ')',
            'CREATE INDEX IDX_8D93D64923A0E66 ON user (article)',
            'CREATE INDEX IDX_8D93D6495A8A6C8D ON user (post)',
            'CREATE INDEX IDX_8D93D6493D8E604F ON user (parent)',
        ];

        self::assertEquals($sql, $this->platform->getCreateTableSQL($table));
    }

    public function testAlterTable() : void
    {
        $table = new Table('user');
        $table->addColumn('id', 'integer');
        $table->addColumn('article', 'integer');
        $table->addColumn('post', 'integer');
        $table->addColumn('parent', 'integer');
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint('article', ['article'], ['id'], ['deferrable' => true]);
        $table->addForeignKeyConstraint('post', ['post'], ['id'], ['deferred' => true]);
        $table->addForeignKeyConstraint('user', ['parent'], ['id'], ['deferrable' => true, 'deferred' => true]);
        $table->addIndex(['article', 'post'], 'index1');

        $diff                           = new TableDiff('user');
        $diff->fromTable                = $table;
        $diff->newName                  = 'client';
        $diff->renamedColumns['id']     = new Column('key', Type::getType('integer'), []);
        $diff->renamedColumns['post']   = new Column('comment', Type::getType('integer'), []);
        $diff->removedColumns['parent'] = new Column('comment', Type::getType('integer'), []);
        $diff->removedIndexes['index1'] = $table->getIndex('index1');

        $sql = [
            'DROP INDEX IDX_8D93D64923A0E66',
            'DROP INDEX IDX_8D93D6495A8A6C8D',
            'DROP INDEX IDX_8D93D6493D8E604F',
            'DROP INDEX index1',
            'CREATE TEMPORARY TABLE __temp__user AS SELECT id, article, post FROM user',
            'DROP TABLE user',
            'CREATE TABLE user ('
                . '"key" INTEGER NOT NULL, article INTEGER NOT NULL, comment INTEGER NOT NULL'
                . ', PRIMARY KEY("key")'
                . ', CONSTRAINT FK_8D93D64923A0E66 FOREIGN KEY (article) REFERENCES article (id) DEFERRABLE INITIALLY IMMEDIATE'
                . ', CONSTRAINT FK_8D93D6495A8A6C8D FOREIGN KEY (comment) REFERENCES post (id) NOT DEFERRABLE INITIALLY DEFERRED'
                . ')',
            'INSERT INTO user ("key", article, comment) SELECT id, article, post FROM __temp__user',
            'DROP TABLE __temp__user',
            'ALTER TABLE user RENAME TO client',
            'CREATE INDEX IDX_8D93D64923A0E66 ON client (article)',
            'CREATE INDEX IDX_8D93D6495A8A6C8D ON client (comment)',
        ];

        self::assertEquals($sql, $this->platform->getAlterTableSQL($diff));
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedColumnInPrimaryKeySQL() : array
    {
        return ['CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, PRIMARY KEY("create"))'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedColumnInIndexSQL() : array
    {
        return [
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB ON "quoted" ("create")',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedNameInIndexSQL() : array
    {
        return [
            'CREATE TABLE test (column1 VARCHAR(255) NOT NULL)',
            'CREATE INDEX "key" ON test (column1)',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedColumnInForeignKeySQL() : array
    {
        return [
            'CREATE TABLE "quoted" (' .
            '"create" VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, "bar" VARCHAR(255) NOT NULL, ' .
            'CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES "foreign" ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE, ' .
            'CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES foo ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE, ' .
            'CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar") REFERENCES "foo-bar" ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE)',
        ];
    }

    protected function getBinaryDefaultLength() : int
    {
        return 0;
    }

    protected function getBinaryMaxLength() : int
    {
        return 0;
    }

    public function testReturnsBinaryTypeDeclarationSQL() : void
    {
        self::assertSame('BLOB', $this->platform->getBinaryTypeDeclarationSQL([]));
        self::assertSame('BLOB', $this->platform->getBinaryTypeDeclarationSQL(['length' => 0]));
        self::assertSame('BLOB', $this->platform->getBinaryTypeDeclarationSQL(['length' => 9999999]));

        self::assertSame('BLOB', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true]));
        self::assertSame('BLOB', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 0]));
        self::assertSame('BLOB', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 9999999]));
    }

    /**
     * {@inheritDoc}
     *
     * @group DBAL-234
     */
    protected function getAlterTableRenameIndexSQL() : array
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
     *
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexSQL() : array
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

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableRenameColumnSQL() : array
    {
        return [
            'CREATE TEMPORARY TABLE __temp__mytable AS SELECT unquoted1, unquoted2, unquoted3, "create", "table", "select", "quoted1", "quoted2", "quoted3" FROM mytable',
            'DROP TABLE mytable',
            'CREATE TABLE mytable (unquoted INTEGER NOT NULL --Unquoted 1
, "where" INTEGER NOT NULL --Unquoted 2
, "foo" INTEGER NOT NULL --Unquoted 3
, reserved_keyword INTEGER NOT NULL --Reserved keyword 1
, "from" INTEGER NOT NULL --Reserved keyword 2
, "bar" INTEGER NOT NULL --Reserved keyword 3
, quoted INTEGER NOT NULL --Quoted 1
, "and" INTEGER NOT NULL --Quoted 2
, "baz" INTEGER NOT NULL --Quoted 3
)',
            'INSERT INTO mytable (unquoted, "where", "foo", reserved_keyword, "from", "bar", quoted, "and", "baz") SELECT unquoted1, unquoted2, unquoted3, "create", "table", "select", "quoted1", "quoted2", "quoted3" FROM __temp__mytable',
            'DROP TABLE __temp__mytable',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableChangeColumnLengthSQL() : array
    {
        return [
            'CREATE TEMPORARY TABLE __temp__mytable AS SELECT unquoted1, unquoted2, unquoted3, "create", "table", "select" FROM mytable',
            'DROP TABLE mytable',
            'CREATE TABLE mytable (unquoted1 VARCHAR(255) NOT NULL --Unquoted 1
, unquoted2 VARCHAR(255) NOT NULL --Unquoted 2
, unquoted3 VARCHAR(255) NOT NULL --Unquoted 3
, "create" VARCHAR(255) NOT NULL --Reserved keyword 1
, "table" VARCHAR(255) NOT NULL --Reserved keyword 2
, "select" VARCHAR(255) NOT NULL --Reserved keyword 3
)',
            'INSERT INTO mytable (unquoted1, unquoted2, unquoted3, "create", "table", "select") SELECT unquoted1, unquoted2, unquoted3, "create", "table", "select" FROM __temp__mytable',
            'DROP TABLE __temp__mytable',
        ];
    }

    /**
     * @group DBAL-807
     */
    public function testAlterTableRenameIndexInSchema() : void
    {
        $this->markTestIncomplete(
            'Test currently produces broken SQL due to SQLLitePlatform::getAlterTable being broken ' .
            'when used with schemas.'
        );
    }

    /**
     * @group DBAL-807
     */
    public function testQuotesAlterTableRenameIndexInSchema() : void
    {
        $this->markTestIncomplete(
            'Test currently produces broken SQL due to SQLLitePlatform::getAlterTable being broken ' .
            'when used with schemas.'
        );
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
        return [
            'CREATE TEMPORARY TABLE __temp__foo AS SELECT bar FROM foo',
            'DROP TABLE foo',
            'CREATE TABLE foo (baz INTEGER DEFAULT 666 NOT NULL --rename test
)',
            'INSERT INTO foo (baz) SELECT bar FROM __temp__foo',
            'DROP TABLE __temp__foo',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesTableIdentifiersInAlterTableSQL() : array
    {
        return [
            'DROP INDEX IDX_8C736521A81E660E',
            'DROP INDEX IDX_8C736521FDC58D6C',
            'CREATE TEMPORARY TABLE __temp__foo AS SELECT fk, fk2, id, fk3, bar FROM "foo"',
            'DROP TABLE "foo"',
            'CREATE TABLE "foo" (fk2 INTEGER NOT NULL, fk3 INTEGER NOT NULL, fk INTEGER NOT NULL, war INTEGER NOT NULL, ' .
            'bar INTEGER DEFAULT NULL, bloo INTEGER NOT NULL, ' .
            'CONSTRAINT fk2 FOREIGN KEY (fk2) REFERENCES fk_table2 (id) NOT DEFERRABLE INITIALLY IMMEDIATE, ' .
            'CONSTRAINT fk_add FOREIGN KEY (fk3) REFERENCES fk_table (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
            'INSERT INTO "foo" (fk, fk2, war, fk3, bar) SELECT fk, fk2, id, fk3, bar FROM __temp__foo',
            'DROP TABLE __temp__foo',
            'ALTER TABLE "foo" RENAME TO "table"',
            'CREATE INDEX IDX_8C736521A81E660E ON "table" (fk)',
            'CREATE INDEX IDX_8C736521FDC58D6C ON "table" (fk2)',
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

    protected static function getInlineColumnCommentDelimiter() : string
    {
        return "\n";
    }

    protected static function getInlineColumnRegularCommentSQL() : string
    {
        return "--Regular comment\n";
    }

    protected static function getInlineColumnCommentRequiringEscapingSQL() : string
    {
        return "--Using inline comment delimiter \n-- works\n";
    }

    protected static function getInlineColumnEmptyCommentSQL() : string
    {
        return "--\n";
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
        return 'DELETE FROM "select"';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAlterStringToFixedStringSQL() : array
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
     * {@inheritdoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL() : array
    {
        return [
            'DROP INDEX idx_foo',
            'DROP INDEX idx_bar',
            'CREATE TEMPORARY TABLE __temp__mytable AS SELECT foo, bar, baz FROM mytable',
            'DROP TABLE mytable',
            'CREATE TABLE mytable (foo INTEGER NOT NULL, bar INTEGER NOT NULL, baz INTEGER NOT NULL, CONSTRAINT fk_foo FOREIGN KEY (foo) REFERENCES foreign_table (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT fk_bar FOREIGN KEY (bar) REFERENCES foreign_table (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
            'INSERT INTO mytable (foo, bar, baz) SELECT foo, bar, baz FROM __temp__mytable',
            'DROP TABLE __temp__mytable',
            'CREATE INDEX idx_bar ON mytable (bar)',
            'CREATE INDEX idx_foo_renamed ON mytable (foo)',
        ];
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

    public function testDateAddStaticNumberOfDays() : void
    {
        self::assertSame("DATE(rentalBeginsOn,'+12 DAY')", $this->platform->getDateAddDaysExpression('rentalBeginsOn', 12));
    }

    public function testDateAddNumberOfDaysFromColumn() : void
    {
        self::assertSame("DATE(rentalBeginsOn,'+' || duration || ' DAY')", $this->platform->getDateAddDaysExpression('rentalBeginsOn', 'duration'));
    }

    public function testSupportsColumnCollation() : void
    {
        self::assertTrue($this->platform->supportsColumnCollation());
    }

    public function testColumnCollationDeclarationSQL() : void
    {
        self::assertSame(
            'COLLATE NOCASE',
            $this->platform->getColumnCollationDeclarationSQL('NOCASE')
        );
    }

    public function testGetCreateTableSQLWithColumnCollation() : void
    {
        $table = new Table('foo');
        $table->addColumn('no_collation', 'string');
        $table->addColumn('column_collation', 'string')->setPlatformOption('collation', 'NOCASE');

        self::assertSame(
            ['CREATE TABLE foo (no_collation VARCHAR(255) NOT NULL, column_collation VARCHAR(255) NOT NULL COLLATE NOCASE)'],
            $this->platform->getCreateTableSQL($table),
            'Column "no_collation" will use the default collation (BINARY) and "column_collation" overwrites the collation on this column'
        );
    }
}
