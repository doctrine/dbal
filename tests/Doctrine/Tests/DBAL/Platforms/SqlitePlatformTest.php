<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\DBALException;

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
            $this->_platform->getIntegerTypeDeclarationSQL(
                array('autoincrement' => true, 'primary' => true))
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
}
