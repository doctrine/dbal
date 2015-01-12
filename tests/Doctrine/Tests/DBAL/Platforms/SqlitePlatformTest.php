<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;

require_once __DIR__ . '/../../TestInit.php';

class SqlitePlatformTest extends AbstractPlatformTestCase
{
    public function createPlatform()
    {
        return new SqlitePlatform;
    }

    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id INTEGER NOT NULL, test VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return array(
            'CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL)',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)',
        );
    }

    public function testGeneratesSqlSnippets()
    {
        $this->assertEquals('RLIKE', $this->_platform->getRegexpExpression(), 'Regular expression operator is not correct');
        $this->assertEquals('SUBSTR(column, 5, LENGTH(column))', $this->_platform->getSubstringExpression('column', 5), 'Substring expression without length is not correct');
        $this->assertEquals('SUBSTR(column, 0, 5)', $this->_platform->getSubstringExpression('column', 0, 5), 'Substring expression with length is not correct');
    }

    public function testGeneratesTransactionCommands()
    {
        $this->assertEquals(
            'PRAGMA read_uncommitted = 0',
            $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_READ_UNCOMMITTED)
        );
        $this->assertEquals(
            'PRAGMA read_uncommitted = 1',
            $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_READ_COMMITTED)
        );
        $this->assertEquals(
            'PRAGMA read_uncommitted = 1',
            $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_REPEATABLE_READ)
        );
        $this->assertEquals(
            'PRAGMA read_uncommitted = 1',
            $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_SERIALIZABLE)
        );
    }

    public function testPrefersIdentityColumns()
    {
        $this->assertTrue($this->_platform->prefersIdentityColumns());
    }

    public function testIgnoresUnsignedIntegerDeclarationForAutoIncrementalIntegers()
    {
        $this->assertSame(
            'INTEGER',
            $this->_platform->getIntegerTypeDeclarationSQL(array('autoincrement' => true, 'unsigned' => true))
        );
    }

    /**
     * @group DBAL-752
     * @group DBAL-924
     */
    public function testGeneratesTypeDeclarationForTinyIntegers()
    {
        $this->assertEquals(
            'TINYINT',
            $this->_platform->getTinyIntTypeDeclarationSQL(array())
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getTinyIntTypeDeclarationSQL(array('autoincrement' => true))
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getTinyIntTypeDeclarationSQL(
                array('autoincrement' => true, 'primary' => true))
        );
        $this->assertEquals(
            'TINYINT',
            $this->_platform->getTinyIntTypeDeclarationSQL(array('unsigned' => false))
        );
        $this->assertEquals(
            'TINYINT UNSIGNED',
            $this->_platform->getTinyIntTypeDeclarationSQL(array('unsigned' => true))
        );
    }

    /**
     * @group DBAL-752
     * @group DBAL-924
     */
    public function testGeneratesTypeDeclarationForSmallIntegers()
    {
        $this->assertEquals(
            'SMALLINT',
            $this->_platform->getSmallIntTypeDeclarationSQL(array())
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getSmallIntTypeDeclarationSQL(array('autoincrement' => true))
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getTinyIntTypeDeclarationSQL(array('autoincrement' => true, 'unsigned' => true))
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getSmallIntTypeDeclarationSQL(
                array('autoincrement' => true, 'primary' => true))
        );
        $this->assertEquals(
            'SMALLINT',
            $this->_platform->getSmallIntTypeDeclarationSQL(array('unsigned' => false))
        );
        $this->assertEquals(
            'SMALLINT UNSIGNED',
            $this->_platform->getSmallIntTypeDeclarationSQL(array('unsigned' => true))
        );
    }

    /**
     * @group DBAL-752
     * @group DBAL-924
     */
    public function testGeneratesTypeDeclarationForMediumIntegers()
    {
        $this->assertEquals(
            'MEDIUMINT',
            $this->_platform->getMediumIntTypeDeclarationSQL(array())
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getMediumIntTypeDeclarationSQL(array('autoincrement' => true))
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getMediumIntTypeDeclarationSQL(array('autoincrement' => true, 'unsigned' => true))
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getMediumIntTypeDeclarationSQL(
                array('autoincrement' => true, 'primary' => true))
        );
        $this->assertEquals(
            'MEDIUMINT',
            $this->_platform->getMediumIntTypeDeclarationSQL(array('unsigned' => false))
        );
        $this->assertEquals(
            'MEDIUMINT UNSIGNED',
            $this->_platform->getMediumIntTypeDeclarationSQL(array('unsigned' => true))
        );
    }

    public function testGeneratesTypeDeclarationForIntegers()
    {
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getIntegerTypeDeclarationSQL(array())
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getIntegerTypeDeclarationSQL(array('autoincrement' => true))
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getIntegerTypeDeclarationSQL(array('autoincrement' => true, 'unsigned' => true))
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getIntegerTypeDeclarationSQL(
                array('autoincrement' => true, 'primary' => true))
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getIntegerTypeDeclarationSQL(array('unsigned' => false))
        );
        $this->assertEquals(
            'INTEGER UNSIGNED',
            $this->_platform->getIntegerTypeDeclarationSQL(array('unsigned' => true))
        );
    }

    /**
     * @group DBAL-752
     * @group DBAL-924
     */
    public function testGeneratesTypeDeclarationForBigIntegers()
    {
        $this->assertEquals(
            'BIGINT',
            $this->_platform->getBigIntTypeDeclarationSQL(array())
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getBigIntTypeDeclarationSQL(array('autoincrement' => true))
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getBigIntTypeDeclarationSQL(array('autoincrement' => true, 'unsigned' => true))
        );
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getBigIntTypeDeclarationSQL(
                array('autoincrement' => true, 'primary' => true))
        );
        $this->assertEquals(
            'BIGINT',
            $this->_platform->getBigIntTypeDeclarationSQL(array('unsigned' => false))
        );
        $this->assertEquals(
            'BIGINT UNSIGNED',
            $this->_platform->getBigIntTypeDeclarationSQL(array('unsigned' => true))
        );
    }

    public function testGeneratesTypeDeclarationForStrings()
    {
        $this->assertEquals(
            'CHAR(10)',
            $this->_platform->getVarcharTypeDeclarationSQL(
                array('length' => 10, 'fixed' => true))
        );
        $this->assertEquals(
            'VARCHAR(50)',
            $this->_platform->getVarcharTypeDeclarationSQL(array('length' => 50)),
            'Variable string declaration is not correct'
        );
        $this->assertEquals(
            'VARCHAR(255)',
            $this->_platform->getVarcharTypeDeclarationSQL(array()),
            'Long string declaration is not correct'
        );
    }

    public function getGenerateIndexSql()
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateUniqueIndexSql()
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testGeneratesForeignKeyCreationSql()
    {
        parent::testGeneratesForeignKeyCreationSql();
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testGeneratesConstraintCreationSql()
    {
        parent::testGeneratesConstraintCreationSql();
    }

    public function getGenerateForeignKeySql()
    {
        return null;
    }

    public function testModifyLimitQuery()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        $this->assertEquals('SELECT * FROM user LIMIT 10 OFFSET 0', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10);
        $this->assertEquals('SELECT * FROM user LIMIT 10', $sql);
    }

    public function getGenerateAlterTableSql()
    {
        return array(
            "CREATE TEMPORARY TABLE __temp__mytable AS SELECT id, bar, bloo FROM mytable",
            "DROP TABLE mytable",
            "CREATE TABLE mytable (id INTEGER NOT NULL, baz VARCHAR(255) DEFAULT 'def' NOT NULL, bloo BOOLEAN DEFAULT '0' NOT NULL, quota INTEGER DEFAULT NULL, PRIMARY KEY(id))",
            "INSERT INTO mytable (id, baz, bloo) SELECT id, bar, bloo FROM __temp__mytable",
            "DROP TABLE __temp__mytable",
            "ALTER TABLE mytable RENAME TO userlist",
        );
    }

    /**
     * @group DDC-1845
     */
    public function testGenerateTableSqlShouldNotAutoQuotePrimaryKey()
    {
        $table = new \Doctrine\DBAL\Schema\Table('test');
        $table->addColumn('"like"', 'integer', array('notnull' => true, 'autoincrement' => true));
        $table->setPrimaryKey(array('"like"'));

        $createTableSQL = $this->_platform->getCreateTableSQL($table);
        $this->assertEquals(
            'CREATE TABLE test ("like" INTEGER NOT NULL, PRIMARY KEY("like"))',
            $createTableSQL[0]
        );
    }

    public function testAlterTableAddColumns()
    {
        $diff = new TableDiff('user');
        $diff->addedColumns['foo'] = new Column('foo', Type::getType('string'));
        $diff->addedColumns['count'] = new Column('count', Type::getType('integer'), array('notnull' => false, 'default' => 1));

        $expected = array(
            'ALTER TABLE user ADD COLUMN foo VARCHAR(255) NOT NULL',
            'ALTER TABLE user ADD COLUMN count INTEGER DEFAULT 1',
        );

        $this->assertEquals($expected, $this->_platform->getAlterTableSQL($diff));
    }

    public function testAlterTableAddComplexColumns()
    {
        $diff = new TableDiff('user');
        $diff->addedColumns['time'] = new Column('time', Type::getType('date'), array('default' => 'CURRENT_DATE'));

        try {
            $this->_platform->getAlterTableSQL($diff);
            $this->fail();
        } catch (DBALException $e) {
        }

        $diff = new TableDiff('user');
        $diff->addedColumns['id'] = new Column('id', Type::getType('integer'), array('autoincrement' => true));

        try {
            $this->_platform->getAlterTableSQL($diff);
            $this->fail();
        } catch (DBALException $e) {
        }
    }

    public function testCreateTableWithDeferredForeignKeys()
    {
        $table = new Table('user');
        $table->addColumn('id', 'integer');
        $table->addColumn('article', 'integer');
        $table->addColumn('post', 'integer');
        $table->addColumn('parent', 'integer');
        $table->setPrimaryKey(array('id'));
        $table->addForeignKeyConstraint('article', array('article'), array('id'), array('deferrable' => true));
        $table->addForeignKeyConstraint('post', array('post'), array('id'), array('deferred' => true));
        $table->addForeignKeyConstraint('user', array('parent'), array('id'), array('deferrable' => true, 'deferred' => true));

        $sql = array(
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
        );

        $this->assertEquals($sql, $this->_platform->getCreateTableSQL($table));
    }

    public function testAlterTable()
    {
        $table = new Table('user');
        $table->addColumn('id', 'integer');
        $table->addColumn('article', 'integer');
        $table->addColumn('post', 'integer');
        $table->addColumn('parent', 'integer');
        $table->setPrimaryKey(array('id'));
        $table->addForeignKeyConstraint('article', array('article'), array('id'), array('deferrable' => true));
        $table->addForeignKeyConstraint('post', array('post'), array('id'), array('deferred' => true));
        $table->addForeignKeyConstraint('user', array('parent'), array('id'), array('deferrable' => true, 'deferred' => true));
        $table->addIndex(array('article', 'post'), 'index1');

        $diff = new TableDiff('user');
        $diff->fromTable = $table;
        $diff->newName = 'client';
        $diff->renamedColumns['id'] = new \Doctrine\DBAL\Schema\Column('key', \Doctrine\DBAL\Types\Type::getType('integer'), array());
        $diff->renamedColumns['post'] = new \Doctrine\DBAL\Schema\Column('comment', \Doctrine\DBAL\Types\Type::getType('integer'), array());
        $diff->removedColumns['parent'] = new \Doctrine\DBAL\Schema\Column('comment', \Doctrine\DBAL\Types\Type::getType('integer'), array());
        $diff->removedIndexes['index1'] = $table->getIndex('index1');

        $sql = array(
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
        );

        $this->assertEquals($sql, $this->_platform->getAlterTableSQL($diff));
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
            'CREATE TABLE "quoted" (' .
            '"create" VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, "bar" VARCHAR(255) NOT NULL, ' .
            'CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES "foreign" ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE, ' .
            'CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES foo ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE, ' .
            'CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar") REFERENCES "foo-bar" ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE)',
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
        $this->assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(array()));
        $this->assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 0)));
        $this->assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 9999999)));

        $this->assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true)));
        $this->assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 0)));
        $this->assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 9999999)));
    }

    /**
     * @group DBAL-234
     */
    protected function getAlterTableRenameIndexSQL()
    {
        return array(
            'CREATE TEMPORARY TABLE __temp__mytable AS SELECT id FROM mytable',
            'DROP TABLE mytable',
            'CREATE TABLE mytable (id INTEGER NOT NULL, PRIMARY KEY(id))',
            'INSERT INTO mytable (id) SELECT id FROM __temp__mytable',
            'DROP TABLE __temp__mytable',
            'CREATE INDEX idx_bar ON mytable (id)',
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexSQL()
    {
        return array(
            'CREATE TEMPORARY TABLE __temp__table AS SELECT id FROM "table"',
            'DROP TABLE "table"',
            'CREATE TABLE "table" (id INTEGER NOT NULL, PRIMARY KEY(id))',
            'INSERT INTO "table" (id) SELECT id FROM __temp__table',
            'DROP TABLE __temp__table',
            'CREATE INDEX "select" ON "table" (id)',
            'CREATE INDEX "bar" ON "table" (id)',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableRenameColumnSQL()
    {
        return array(
            'CREATE TEMPORARY TABLE __temp__mytable AS SELECT unquoted1, unquoted2, unquoted3, "create", "table", "select", "quoted1", "quoted2", "quoted3" FROM mytable',
            'DROP TABLE mytable',
            'CREATE TABLE mytable (unquoted INTEGER NOT NULL, "where" INTEGER NOT NULL, "foo" INTEGER NOT NULL, reserved_keyword INTEGER NOT NULL, "from" INTEGER NOT NULL, "bar" INTEGER NOT NULL, quoted INTEGER NOT NULL, "and" INTEGER NOT NULL, "baz" INTEGER NOT NULL)',
            'INSERT INTO mytable (unquoted, "where", "foo", reserved_keyword, "from", "bar", quoted, "and", "baz") SELECT unquoted1, unquoted2, unquoted3, "create", "table", "select", "quoted1", "quoted2", "quoted3" FROM __temp__mytable',
            'DROP TABLE __temp__mytable',
        );
	}

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableChangeColumnLengthSQL()
    {
        return array(
            'CREATE TEMPORARY TABLE __temp__mytable AS SELECT unquoted1, unquoted2, unquoted3, "create", "table", "select" FROM mytable',
            'DROP TABLE mytable',
            'CREATE TABLE mytable (unquoted1 VARCHAR(255) NOT NULL, unquoted2 VARCHAR(255) NOT NULL, unquoted3 VARCHAR(255) NOT NULL, "create" VARCHAR(255) NOT NULL, "table" VARCHAR(255) NOT NULL, "select" VARCHAR(255) NOT NULL)',
            'INSERT INTO mytable (unquoted1, unquoted2, unquoted3, "create", "table", "select") SELECT unquoted1, unquoted2, unquoted3, "create", "table", "select" FROM __temp__mytable',
            'DROP TABLE __temp__mytable',
        );
    }

    /**
     * @group DBAL-807
     */
    public function testAlterTableRenameIndexInSchema()
    {
        $this->markTestIncomplete(
            'Test currently produces broken SQL due to SQLLitePlatform::getAlterTable being broken ' .
            'when used with schemas.'
        );
    }

    /**
     * @group DBAL-807
     */
    public function testQuotesAlterTableRenameIndexInSchema()
    {
        $this->markTestIncomplete(
            'Test currently produces broken SQL due to SQLLitePlatform::getAlterTable being broken ' .
            'when used with schemas.'
        );
    }

    /**
     * @group DBAL-423
     */
    public function testReturnsGuidTypeDeclarationSQL()
    {
        $this->assertSame('CHAR(36)', $this->_platform->getGuidTypeDeclarationSQL(array()));
    }

    /**
     * {@inheritdoc}
     */
    public function getAlterTableRenameColumnSQL()
    {
        return array(
            'CREATE TEMPORARY TABLE __temp__foo AS SELECT bar FROM foo',
            'DROP TABLE foo',
            'CREATE TABLE foo (baz INTEGER DEFAULT 666 NOT NULL)',
            'INSERT INTO foo (baz) SELECT bar FROM __temp__foo',
            'DROP TABLE __temp__foo',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesTableIdentifiersInAlterTableSQL()
    {
        return array(
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
    protected function getAlterStringToFixedStringSQL()
    {
        return array(
            'CREATE TEMPORARY TABLE __temp__mytable AS SELECT name FROM mytable',
            'DROP TABLE mytable',
            'CREATE TABLE mytable (name CHAR(2) NOT NULL)',
            'INSERT INTO mytable (name) SELECT name FROM __temp__mytable',
            'DROP TABLE __temp__mytable',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL()
    {
        return array(
            'DROP INDEX idx_foo',
            'DROP INDEX idx_bar',
            'CREATE TEMPORARY TABLE __temp__mytable AS SELECT foo, bar, baz FROM mytable',
            'DROP TABLE mytable',
            'CREATE TABLE mytable (foo INTEGER NOT NULL, bar INTEGER NOT NULL, baz INTEGER NOT NULL, CONSTRAINT fk_foo FOREIGN KEY (foo) REFERENCES foreign_table (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT fk_bar FOREIGN KEY (bar) REFERENCES foreign_table (id) NOT DEFERRABLE INITIALLY IMMEDIATE)',
            'INSERT INTO mytable (foo, bar, baz) SELECT foo, bar, baz FROM __temp__mytable',
            'DROP TABLE __temp__mytable',
            'CREATE INDEX idx_bar ON mytable (bar)',
            'CREATE INDEX idx_foo_renamed ON mytable (foo)',
        );
    }
}
