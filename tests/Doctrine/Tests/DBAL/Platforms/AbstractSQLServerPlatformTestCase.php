<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;

abstract class AbstractSQLServerPlatformTestCase extends AbstractPlatformTestCase
{
    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id INT IDENTITY NOT NULL, test NVARCHAR(255), PRIMARY KEY (id))';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return array(
            'CREATE TABLE test (foo NVARCHAR(255), bar NVARCHAR(255))',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar) WHERE foo IS NOT NULL AND bar IS NOT NULL'
        );
    }

    public function getGenerateAlterTableSql()
    {
        return array(
            'ALTER TABLE mytable ADD quota INT',
            'ALTER TABLE mytable DROP COLUMN foo',
            'ALTER TABLE mytable ALTER COLUMN baz NVARCHAR(255) NOT NULL',
            "ALTER TABLE mytable ADD CONSTRAINT DF_6B2BD609_78240498 DEFAULT 'def' FOR baz",
            'ALTER TABLE mytable ALTER COLUMN bloo BIT NOT NULL',
            "ALTER TABLE mytable ADD CONSTRAINT DF_6B2BD609_CECED971 DEFAULT '0' FOR bloo",
            "sp_RENAME 'mytable', 'userlist'",
            "DECLARE @sql NVARCHAR(MAX) = N''; " .
            "SELECT @sql += N'EXEC sp_rename N''' + dc.name + ''', N''' " .
            "+ REPLACE(dc.name, '6B2BD609', 'E2B58069') + ''', ''OBJECT'';' " .
            "FROM sys.default_constraints dc " .
            "JOIN sys.tables tbl ON dc.parent_object_id = tbl.object_id " .
            "WHERE tbl.name = 'userlist';" .
            "EXEC sp_executesql @sql"
        );
    }

    /**
     * @expectedException Doctrine\DBAL\DBALException
     */
    public function testDoesNotSupportRegexp()
    {
        $this->_platform->getRegexpExpression();
    }

    public function testGeneratesSqlSnippets()
    {
        $this->assertEquals('"', $this->_platform->getIdentifierQuoteCharacter(), 'Identifier quote character is not correct');
        $this->assertEquals('(column1 + column2 + column3)', $this->_platform->getConcatExpression('column1', 'column2', 'column3'), 'Concatenation expression is not correct');
    }

    public function testGeneratesTransactionsCommands()
    {
        $this->assertEquals(
                'SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED',
                $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_READ_UNCOMMITTED)
        );
        $this->assertEquals(
                'SET TRANSACTION ISOLATION LEVEL READ COMMITTED',
                $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_READ_COMMITTED)
        );
        $this->assertEquals(
                'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
                $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_REPEATABLE_READ)
        );
        $this->assertEquals(
                'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE',
                $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_SERIALIZABLE)
        );
    }

    public function testGeneratesDDLSnippets()
    {
        $dropDatabaseExpectation = 'DROP DATABASE foobar';

        $this->assertEquals('SELECT * FROM SYS.DATABASES', $this->_platform->getListDatabasesSQL());
        $this->assertEquals('CREATE DATABASE foobar', $this->_platform->getCreateDatabaseSQL('foobar'));
        $this->assertEquals($dropDatabaseExpectation, $this->_platform->getDropDatabaseSQL('foobar'));
        $this->assertEquals('DROP TABLE foobar', $this->_platform->getDropTableSQL('foobar'));
    }

    public function testGeneratesTypeDeclarationForIntegers()
    {
        $this->assertEquals(
                'INT',
                $this->_platform->getIntegerTypeDeclarationSQL(array())
        );
        $this->assertEquals(
                'INT IDENTITY',
                $this->_platform->getIntegerTypeDeclarationSQL(array('autoincrement' => true)
        ));
        $this->assertEquals(
                'INT IDENTITY',
                $this->_platform->getIntegerTypeDeclarationSQL(
                        array('autoincrement' => true, 'primary' => true)
        ));
    }

    public function testGeneratesTypeDeclarationsForStrings()
    {
        $this->assertEquals(
                'NCHAR(10)',
                $this->_platform->getVarcharTypeDeclarationSQL(
                        array('length' => 10, 'fixed' => true)
        ));
        $this->assertEquals(
                'NVARCHAR(50)',
                $this->_platform->getVarcharTypeDeclarationSQL(array('length' => 50)),
                'Variable string declaration is not correct'
        );
        $this->assertEquals(
                'NVARCHAR(255)',
                $this->_platform->getVarcharTypeDeclarationSQL(array()),
                'Long string declaration is not correct'
        );
        $this->assertSame('VARCHAR(MAX)', $this->_platform->getClobTypeDeclarationSQL(array()));
        $this->assertSame(
            'VARCHAR(MAX)',
            $this->_platform->getClobTypeDeclarationSQL(array('length' => 5, 'fixed' => true))
        );
    }

    public function testPrefersIdentityColumns()
    {
        $this->assertTrue($this->_platform->prefersIdentityColumns());
    }

    public function testSupportsIdentityColumns()
    {
        $this->assertTrue($this->_platform->supportsIdentityColumns());
    }

    public function testSupportsSchemas()
    {
        $this->assertTrue($this->_platform->supportsSchemas());
    }

    public function testDoesNotSupportSavePoints()
    {
        $this->assertTrue($this->_platform->supportsSavepoints());
    }

    public function getGenerateIndexSql()
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateUniqueIndexSql()
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2) WHERE test IS NOT NULL AND test2 IS NOT NULL';
    }

    public function getGenerateForeignKeySql()
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
    }

    public function testModifyLimitQuery()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum FROM user) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum FROM user) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    public function testModifyLimitQueryWithOffset()
    {
        if ( ! $this->_platform->supportsLimitOffset()) {
            $this->markTestSkipped(sprintf('Platform "%s" does not support offsets in result limiting.', $this->_platform->getName()));
        }

        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10, 5);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY username DESC) AS doctrine_rownum FROM user) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 6 AND 15', $sql);
    }

    public function testModifyLimitQueryWithAscOrderBy()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username ASC', 10);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY username ASC) AS doctrine_rownum FROM user) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    public function testModifyLimitQueryWithDescOrderBy()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY username DESC) AS doctrine_rownum FROM user) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    public function testModifyLimitQueryWithMultipleOrderBy()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC, usereamil ASC', 10);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY username DESC, usereamil ASC) AS doctrine_rownum FROM user) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    public function testModifyLimitQueryWithSubSelect()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id as uid, u.name as uname) dctrn_result', 10);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum FROM (SELECT u.id as uid, u.name as uname) dctrn_result) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    public function testModifyLimitQueryWithSubSelectAndOrder()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id as uid, u.name as uname ORDER BY u.name DESC) dctrn_result', 10);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY u.name DESC) AS doctrine_rownum FROM (SELECT u.id as uid, u.name as uname) dctrn_result) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);

        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id, u.name ORDER BY u.name DESC) dctrn_result', 10);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY name DESC) AS doctrine_rownum FROM (SELECT u.id, u.name) dctrn_result) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    public function testModifyLimitQueryWithSubSelectAndMultipleOrder()
    {
        if ( ! $this->_platform->supportsLimitOffset()) {
            $this->markTestSkipped(sprintf('Platform "%s" does not support offsets in result limiting.', $this->_platform->getName()));
        }

        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id as uid, u.name as uname ORDER BY u.name DESC, id ASC) dctrn_result', 10, 5);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY u.name DESC, id ASC) AS doctrine_rownum FROM (SELECT u.id as uid, u.name as uname) dctrn_result) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 6 AND 15', $sql);

        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id uid, u.name uname ORDER BY u.name DESC, id ASC) dctrn_result', 10, 5);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY u.name DESC, id ASC) AS doctrine_rownum FROM (SELECT u.id uid, u.name uname) dctrn_result) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 6 AND 15', $sql);

        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id, u.name ORDER BY u.name DESC, id ASC) dctrn_result', 10, 5);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY name DESC, id ASC) AS doctrine_rownum FROM (SELECT u.id, u.name) dctrn_result) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 6 AND 15', $sql);
    }

    public function testModifyLimitQueryWithFromColumnNames()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT a.fromFoo, fromBar FROM foo', 10);
        $this->assertEquals('SELECT * FROM (SELECT a.fromFoo, fromBar, ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum FROM foo) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    /**
     * @group DDC-2470
     */
    public function testModifyLimitQueryWithOrderByClause()
    {
        if ( ! $this->_platform->supportsLimitOffset()) {
            $this->markTestSkipped(sprintf('Platform "%s" does not support offsets in result limiting.', $this->_platform->getName()));
        }

        $sql      = 'SELECT m0_.NOMBRE AS NOMBRE0, m0_.FECHAINICIO AS FECHAINICIO1, m0_.FECHAFIN AS FECHAFIN2 FROM MEDICION m0_ WITH (NOLOCK) INNER JOIN ESTUDIO e1_ ON m0_.ESTUDIO_ID = e1_.ID INNER JOIN CLIENTE c2_ ON e1_.CLIENTE_ID = c2_.ID INNER JOIN USUARIO u3_ ON c2_.ID = u3_.CLIENTE_ID WHERE u3_.ID = ? ORDER BY m0_.FECHAINICIO DESC';
        $expected = 'SELECT * FROM (SELECT m0_.NOMBRE AS NOMBRE0, m0_.FECHAINICIO AS FECHAINICIO1, m0_.FECHAFIN AS FECHAFIN2, ROW_NUMBER() OVER (ORDER BY m0_.FECHAINICIO DESC) AS doctrine_rownum FROM MEDICION m0_ WITH (NOLOCK) INNER JOIN ESTUDIO e1_ ON m0_.ESTUDIO_ID = e1_.ID INNER JOIN CLIENTE c2_ ON e1_.CLIENTE_ID = c2_.ID INNER JOIN USUARIO u3_ ON c2_.ID = u3_.CLIENTE_ID WHERE u3_.ID = ?) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 6 AND 15';
        $actual   = $this->_platform->modifyLimitQuery($sql, 10, 5);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @group DBAL-713
     */
    public function testModifyLimitQueryWithSubSelectInSelectList()
    {
        $sql = $this->_platform->modifyLimitQuery(
            "SELECT " .
            "u.id, " .
            "(u.foo/2) foodiv, " .
            "CONCAT(u.bar, u.baz) barbaz, " .
            "(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) FROM profile p WHERE p.user_id = u.id) login_count " .
            "FROM user u " .
            "WHERE u.status = 'disabled'",
            10
        );

        $this->assertEquals(
            "SELECT * FROM (" .
            "SELECT " .
            "u.id, " .
            "(u.foo/2) foodiv, " .
            "CONCAT(u.bar, u.baz) barbaz, " .
            "(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) FROM profile p WHERE p.user_id = u.id) login_count, " .
            "ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum " .
            "FROM user u " .
            "WHERE u.status = 'disabled'" .
            ") AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10",
            $sql
        );
    }

    /**
     * @group DBAL-713
     */
    public function testModifyLimitQueryWithSubSelectInSelectListAndOrderByClause()
    {
        if ( ! $this->_platform->supportsLimitOffset()) {
            $this->markTestSkipped(sprintf('Platform "%s" does not support offsets in result limiting.', $this->_platform->getName()));
        }

        $sql = $this->_platform->modifyLimitQuery(
            "SELECT " .
            "u.id, " .
            "(u.foo/2) foodiv, " .
            "CONCAT(u.bar, u.baz) barbaz, " .
            "(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) FROM profile p WHERE p.user_id = u.id) login_count " .
            "FROM user u " .
            "WHERE u.status = 'disabled' " .
            "ORDER BY u.username DESC",
            10,
            5
        );

        $this->assertEquals(
            "SELECT * FROM (" .
            "SELECT " .
            "u.id, " .
            "(u.foo/2) foodiv, " .
            "CONCAT(u.bar, u.baz) barbaz, " .
            "(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) FROM profile p WHERE p.user_id = u.id) login_count, " .
            "ROW_NUMBER() OVER (ORDER BY username DESC) AS doctrine_rownum " .
            "FROM user u " .
            "WHERE u.status = 'disabled'" .
            ") AS doctrine_tbl WHERE doctrine_rownum BETWEEN 6 AND 15",
            $sql
        );
    }

    /**
     * @group DDC-1360
     */
    public function testQuoteIdentifier()
    {
        $this->assertEquals('[fo][o]', $this->_platform->quoteIdentifier('fo]o'));
        $this->assertEquals('[test]', $this->_platform->quoteIdentifier('test'));
        $this->assertEquals('[test].[test]', $this->_platform->quoteIdentifier('test.test'));
    }

    /**
     * @group DDC-1360
     */
    public function testQuoteSingleIdentifier()
    {
        $this->assertEquals('[fo][o]', $this->_platform->quoteSingleIdentifier('fo]o'));
        $this->assertEquals('[test]', $this->_platform->quoteSingleIdentifier('test'));
        $this->assertEquals('[test.test]', $this->_platform->quoteSingleIdentifier('test.test'));
    }

    /**
     * @group DBAL-220
     */
    public function testCreateClusteredIndex()
    {
        $idx = new \Doctrine\DBAL\Schema\Index('idx', array('id'));
        $idx->addFlag('clustered');
        $this->assertEquals('CREATE CLUSTERED INDEX idx ON tbl (id)', $this->_platform->getCreateIndexSQL($idx, 'tbl'));
    }

    /**
     * @group DBAL-220
     */
    public function testCreateNonClusteredPrimaryKeyInTable()
    {
        $table = new \Doctrine\DBAL\Schema\Table("tbl");
        $table->addColumn("id", "integer");
        $table->setPrimaryKey(Array("id"));
        $table->getIndex('primary')->addFlag('nonclustered');

        $this->assertEquals(array('CREATE TABLE tbl (id INT NOT NULL, PRIMARY KEY NONCLUSTERED (id))'), $this->_platform->getCreateTableSQL($table));
    }

    /**
     * @group DBAL-220
     */
    public function testCreateNonClusteredPrimaryKey()
    {
        $idx = new \Doctrine\DBAL\Schema\Index('idx', array('id'), false, true);
        $idx->addFlag('nonclustered');
        $this->assertEquals('ALTER TABLE tbl ADD PRIMARY KEY NONCLUSTERED (id)', $this->_platform->getCreatePrimaryKeySQL($idx, 'tbl'));
    }

    public function testAlterAddPrimaryKey()
    {
        $idx = new \Doctrine\DBAL\Schema\Index('idx', array('id'), false, true);
        $this->assertEquals('ALTER TABLE tbl ADD PRIMARY KEY (id)', $this->_platform->getCreateIndexSQL($idx, 'tbl'));
    }

    protected function getQuotedColumnInPrimaryKeySQL()
    {
        return array(
            'CREATE TABLE [quoted] ([create] NVARCHAR(255) NOT NULL, PRIMARY KEY ([create]))',
        );
    }

    protected function getQuotedColumnInIndexSQL()
    {
        return array(
            'CREATE TABLE [quoted] ([create] NVARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB ON [quoted] ([create])',
        );
    }

    protected function getQuotedColumnInForeignKeySQL()
    {
        return array(
            'CREATE TABLE [quoted] ([create] NVARCHAR(255) NOT NULL, foo NVARCHAR(255) NOT NULL, [bar] NVARCHAR(255) NOT NULL)',
            'ALTER TABLE [quoted] ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ([create], foo, [bar]) REFERENCES [foreign] ([create], bar, [foo-bar])',
            'ALTER TABLE [quoted] ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ([create], foo, [bar]) REFERENCES foo ([create], bar, [foo-bar])',
            'ALTER TABLE [quoted] ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ([create], foo, [bar]) REFERENCES [foo-bar] ([create], bar, [foo-bar])',
        );
    }

    public function testGetCreateSchemaSQL()
    {
        $schemaName = 'schema';
        $sql = $this->_platform->getCreateSchemaSQL($schemaName);
        $this->assertEquals('CREATE SCHEMA ' . $schemaName, $sql);
    }

    public function testSchemaNeedsCreation()
    {
        $schemaNames = array(
            'dbo' => false,
            'schema' => true,
        );
        foreach ($schemaNames as $name => $expected) {
            $actual = $this->_platform->schemaNeedsCreation($name);
            $this->assertEquals($expected, $actual);
        }
    }

    /**
     * @group DBAL-543
     */
    public function getCreateTableColumnCommentsSQL()
    {
        return array(
            "CREATE TABLE test (id INT NOT NULL, PRIMARY KEY (id))",
            "EXEC sp_addextendedproperty N'MS_Description', N'This is a comment', N'SCHEMA', dbo, N'TABLE', test, N'COLUMN', id",
        );
    }

    /**
     * @group DBAL-543
     */
    public function getAlterTableColumnCommentsSQL()
    {
        return array(
            "ALTER TABLE mytable ADD quota INT NOT NULL",
            "EXEC sp_addextendedproperty N'MS_Description', N'A comment', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', quota",
            // todo
            //"EXEC sp_addextendedproperty N'MS_Description', N'B comment', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', baz",
        );
    }

    /**
     * @group DBAL-543
     */
    public function getCreateTableColumnTypeCommentsSQL()
    {
        return array(
            "CREATE TABLE test (id INT NOT NULL, data VARCHAR(MAX) NOT NULL, PRIMARY KEY (id))",
            "EXEC sp_addextendedproperty N'MS_Description', N'(DC2Type:array)', N'SCHEMA', dbo, N'TABLE', test, N'COLUMN', data",
        );
    }

    /**
     * @group DBAL-543
     */
    public function testGeneratesCreateTableSQLWithColumnComments()
    {
        $table = new Table('mytable');
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('comment_null', 'integer', array('comment' => null));
        $table->addColumn('comment_false', 'integer', array('comment' => false));
        $table->addColumn('comment_empty_string', 'integer', array('comment' => ''));
        $table->addColumn('comment_integer_0', 'integer', array('comment' => 0));
        $table->addColumn('comment_float_0', 'integer', array('comment' => 0.0));
        $table->addColumn('comment_string_0', 'integer', array('comment' => '0'));
        $table->addColumn('comment', 'integer', array('comment' => 'Doctrine 0wnz you!'));
        $table->addColumn('`comment_quoted`', 'integer', array('comment' => 'Doctrine 0wnz comments for explicitely quoted columns!'));
        $table->addColumn('create', 'integer', array('comment' => 'Doctrine 0wnz comments for reserved keyword columns!'));
        $table->addColumn('commented_type', 'object');
        $table->addColumn('commented_type_with_comment', 'array', array('comment' => 'Doctrine array type.'));
        $table->setPrimaryKey(array('id'));

        $this->assertEquals(
            array(
                "CREATE TABLE mytable (id INT IDENTITY NOT NULL, comment_null INT NOT NULL, comment_false INT NOT NULL, comment_empty_string INT NOT NULL, comment_integer_0 INT NOT NULL, comment_float_0 INT NOT NULL, comment_string_0 INT NOT NULL, comment INT NOT NULL, [comment_quoted] INT NOT NULL, [create] INT NOT NULL, commented_type VARCHAR(MAX) NOT NULL, commented_type_with_comment VARCHAR(MAX) NOT NULL, PRIMARY KEY (id))",
                "EXEC sp_addextendedproperty N'MS_Description', N'0', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment_integer_0",
                "EXEC sp_addextendedproperty N'MS_Description', N'0', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment_float_0",
                "EXEC sp_addextendedproperty N'MS_Description', N'0', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment_string_0",
                "EXEC sp_addextendedproperty N'MS_Description', N'Doctrine 0wnz you!', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment",
                "EXEC sp_addextendedproperty N'MS_Description', N'Doctrine 0wnz comments for explicitely quoted columns!', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', [comment_quoted]",
                "EXEC sp_addextendedproperty N'MS_Description', N'Doctrine 0wnz comments for reserved keyword columns!', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', [create]",
                "EXEC sp_addextendedproperty N'MS_Description', N'(DC2Type:object)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', commented_type",
                "EXEC sp_addextendedproperty N'MS_Description', N'Doctrine array type.(DC2Type:array)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', commented_type_with_comment",
            ),
            $this->_platform->getCreateTableSQL($table)
        );
    }

    /**
     * @group DBAL-543
     */
    public function testGeneratesAlterTableSQLWithColumnComments()
    {
        $table = new Table('mytable');
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('comment_null', 'integer', array('comment' => null));
        $table->addColumn('comment_false', 'integer', array('comment' => false));
        $table->addColumn('comment_empty_string', 'integer', array('comment' => ''));
        $table->addColumn('comment_integer_0', 'integer', array('comment' => 0));
        $table->addColumn('comment_float_0', 'integer', array('comment' => 0.0));
        $table->addColumn('comment_string_0', 'integer', array('comment' => '0'));
        $table->addColumn('comment', 'integer', array('comment' => 'Doctrine 0wnz you!'));
        $table->addColumn('`comment_quoted`', 'integer', array('comment' => 'Doctrine 0wnz comments for explicitely quoted columns!'));
        $table->addColumn('create', 'integer', array('comment' => 'Doctrine 0wnz comments for reserved keyword columns!'));
        $table->addColumn('commented_type', 'object');
        $table->addColumn('commented_type_with_comment', 'array', array('comment' => 'Doctrine array type.'));
        $table->setPrimaryKey(array('id'));

        $tableDiff = new TableDiff('mytable');
        $tableDiff->fromTable = $table;
        $tableDiff->addedColumns['added_comment_none'] = new Column('added_comment_none', Type::getType('integer'));
        $tableDiff->addedColumns['added_comment_null'] = new Column('added_comment_null', Type::getType('integer'), array('comment' => null));
        $tableDiff->addedColumns['added_comment_false'] = new Column('added_comment_false', Type::getType('integer'), array('comment' => false));
        $tableDiff->addedColumns['added_comment_empty_string'] = new Column('added_comment_empty_string', Type::getType('integer'), array('comment' => ''));
        $tableDiff->addedColumns['added_comment_integer_0'] = new Column('added_comment_integer_0', Type::getType('integer'), array('comment' => 0));
        $tableDiff->addedColumns['added_comment_float_0'] = new Column('added_comment_float_0', Type::getType('integer'), array('comment' => 0.0));
        $tableDiff->addedColumns['added_comment_string_0'] = new Column('added_comment_string_0', Type::getType('integer'), array('comment' => '0'));
        $tableDiff->addedColumns['added_comment'] = new Column('added_comment', Type::getType('integer'), array('comment' => 'Doctrine'));
        $tableDiff->addedColumns['`added_comment_quoted`'] = new Column('`added_comment_quoted`', Type::getType('integer'), array('comment' => 'rulez'));
        $tableDiff->addedColumns['select'] = new Column('select', Type::getType('integer'), array('comment' => '666'));
        $tableDiff->addedColumns['added_commented_type'] = new Column('added_commented_type', Type::getType('object'));
        $tableDiff->addedColumns['added_commented_type_with_comment'] = new Column('added_commented_type_with_comment', Type::getType('array'), array('comment' => '666'));

        $tableDiff->renamedColumns['comment_float_0'] = new Column('comment_double_0', Type::getType('decimal'), array('comment' => 'Double for real!'));

        // Add comment to non-commented column.
        $tableDiff->changedColumns['id'] = new ColumnDiff(
            'id',
            new Column('id', Type::getType('integer'), array('autoincrement' => true, 'comment' => 'primary')),
            array('comment'),
            new Column('id', Type::getType('integer'), array('autoincrement' => true))
        );

        // Remove comment from null-commented column.
        $tableDiff->changedColumns['comment_null'] = new ColumnDiff(
            'comment_null',
            new Column('comment_null', Type::getType('string')),
            array('type'),
            new Column('comment_null', Type::getType('integer'), array('comment' => null))
        );

        // Add comment to false-commented column.
        $tableDiff->changedColumns['comment_false'] = new ColumnDiff(
            'comment_false',
            new Column('comment_false', Type::getType('integer'), array('comment' => 'false')),
            array('comment'),
            new Column('comment_false', Type::getType('integer'), array('comment' => false))
        );

        // Change type to custom type from empty string commented column.
        $tableDiff->changedColumns['comment_empty_string'] = new ColumnDiff(
            'comment_empty_string',
            new Column('comment_empty_string', Type::getType('object')),
            array('type'),
            new Column('comment_empty_string', Type::getType('integer'), array('comment' => ''))
        );

        // Change comment to false-comment from zero-string commented column.
        $tableDiff->changedColumns['comment_string_0'] = new ColumnDiff(
            'comment_string_0',
            new Column('comment_string_0', Type::getType('integer'), array('comment' => false)),
            array('comment'),
            new Column('comment_string_0', Type::getType('integer'), array('comment' => '0'))
        );

        // Remove comment from regular commented column.
        $tableDiff->changedColumns['comment'] = new ColumnDiff(
            'comment',
            new Column('comment', Type::getType('integer')),
            array('comment'),
            new Column('comment', Type::getType('integer'), array('comment' => 'Doctrine 0wnz you!'))
        );

        // Change comment and change type to custom type from regular commented column.
        $tableDiff->changedColumns['`comment_quoted`'] = new ColumnDiff(
            '`comment_quoted`',
            new Column('`comment_quoted`', Type::getType('array'), array('comment' => 'Doctrine array.')),
            array('comment', 'type'),
            new Column('`comment_quoted`', Type::getType('integer'), array('comment' => 'Doctrine 0wnz you!'))
        );

        // Remove comment and change type to custom type from regular commented column.
        $tableDiff->changedColumns['create'] = new ColumnDiff(
            'create',
            new Column('create', Type::getType('object')),
            array('comment', 'type'),
            new Column('create', Type::getType('integer'), array('comment' => 'Doctrine 0wnz comments for reserved keyword columns!'))
        );

        // Add comment and change custom type to regular type from non-commented column.
        $tableDiff->changedColumns['commented_type'] = new ColumnDiff(
            'commented_type',
            new Column('commented_type', Type::getType('integer'), array('comment' => 'foo')),
            array('comment', 'type'),
            new Column('commented_type', Type::getType('object'))
        );

        // Remove comment from commented custom type column.
        $tableDiff->changedColumns['commented_type_with_comment'] = new ColumnDiff(
            'commented_type_with_comment',
            new Column('commented_type_with_comment', Type::getType('array')),
            array('comment'),
            new Column('commented_type_with_comment', Type::getType('array'), array('comment' => 'Doctrine array type.'))
        );

        $tableDiff->removedColumns['comment_integer_0'] = new Column('comment_integer_0', Type::getType('integer'), array('comment' => 0));

        $this->assertEquals(
            array(
                // Renamed columns.
                "sp_RENAME 'mytable.comment_float_0', 'comment_double_0', 'COLUMN'",

                // Added columns.
                "ALTER TABLE mytable ADD added_comment_none INT NOT NULL",
                "ALTER TABLE mytable ADD added_comment_null INT NOT NULL",
                "ALTER TABLE mytable ADD added_comment_false INT NOT NULL",
                "ALTER TABLE mytable ADD added_comment_empty_string INT NOT NULL",
                "ALTER TABLE mytable ADD added_comment_integer_0 INT NOT NULL",
                "ALTER TABLE mytable ADD added_comment_float_0 INT NOT NULL",
                "ALTER TABLE mytable ADD added_comment_string_0 INT NOT NULL",
                "ALTER TABLE mytable ADD added_comment INT NOT NULL",
                "ALTER TABLE mytable ADD [added_comment_quoted] INT NOT NULL",
                "ALTER TABLE mytable ADD [select] INT NOT NULL",
                "ALTER TABLE mytable ADD added_commented_type VARCHAR(MAX) NOT NULL",
                "ALTER TABLE mytable ADD added_commented_type_with_comment VARCHAR(MAX) NOT NULL",
                "ALTER TABLE mytable DROP COLUMN comment_integer_0",
                "ALTER TABLE mytable ALTER COLUMN comment_null NVARCHAR(255) NOT NULL",
                "ALTER TABLE mytable ALTER COLUMN comment_empty_string VARCHAR(MAX) NOT NULL",
                "ALTER TABLE mytable ALTER COLUMN [comment_quoted] VARCHAR(MAX) NOT NULL",
                "ALTER TABLE mytable ALTER COLUMN [create] VARCHAR(MAX) NOT NULL",
                "ALTER TABLE mytable ALTER COLUMN commented_type INT NOT NULL",

                // Renamed columns.
                "ALTER TABLE mytable ALTER COLUMN comment_double_0 NUMERIC(10, 0) NOT NULL",

                // Added columns.
                "EXEC sp_addextendedproperty N'MS_Description', N'0', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', added_comment_integer_0",
                "EXEC sp_addextendedproperty N'MS_Description', N'0', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', added_comment_float_0",
                "EXEC sp_addextendedproperty N'MS_Description', N'0', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', added_comment_string_0",
                "EXEC sp_addextendedproperty N'MS_Description', N'Doctrine', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', added_comment",
                "EXEC sp_addextendedproperty N'MS_Description', N'rulez', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', [added_comment_quoted]",
                "EXEC sp_addextendedproperty N'MS_Description', N'666', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', [select]",
                "EXEC sp_addextendedproperty N'MS_Description', N'(DC2Type:object)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', added_commented_type",
                "EXEC sp_addextendedproperty N'MS_Description', N'666(DC2Type:array)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', added_commented_type_with_comment",

                // Changed columns.
                "EXEC sp_addextendedproperty N'MS_Description', N'primary', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', id",
                "EXEC sp_addextendedproperty N'MS_Description', N'false', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment_false",
                "EXEC sp_addextendedproperty N'MS_Description', N'(DC2Type:object)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment_empty_string",
                "EXEC sp_dropextendedproperty N'MS_Description', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment_string_0",
                "EXEC sp_dropextendedproperty N'MS_Description', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', comment",
                "EXEC sp_updateextendedproperty N'MS_Description', N'Doctrine array.(DC2Type:array)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', [comment_quoted]",
                "EXEC sp_updateextendedproperty N'MS_Description', N'(DC2Type:object)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', [create]",
                "EXEC sp_updateextendedproperty N'MS_Description', N'foo', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', commented_type",
                "EXEC sp_updateextendedproperty N'MS_Description', N'(DC2Type:array)', N'SCHEMA', dbo, N'TABLE', mytable, N'COLUMN', commented_type_with_comment",
            ),
            $this->_platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @group DBAL-122
     */
    public function testInitializesDoctrineTypeMappings()
    {
        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('bigint'));
        $this->assertSame('bigint', $this->_platform->getDoctrineTypeMapping('bigint'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('numeric'));
        $this->assertSame('decimal', $this->_platform->getDoctrineTypeMapping('numeric'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('bit'));
        $this->assertSame('boolean', $this->_platform->getDoctrineTypeMapping('bit'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('smallint'));
        $this->assertSame('smallint', $this->_platform->getDoctrineTypeMapping('smallint'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('decimal'));
        $this->assertSame('decimal', $this->_platform->getDoctrineTypeMapping('decimal'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('smallmoney'));
        $this->assertSame('integer', $this->_platform->getDoctrineTypeMapping('smallmoney'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('int'));
        $this->assertSame('integer', $this->_platform->getDoctrineTypeMapping('int'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('tinyint'));
        $this->assertSame('smallint', $this->_platform->getDoctrineTypeMapping('tinyint'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('money'));
        $this->assertSame('integer', $this->_platform->getDoctrineTypeMapping('money'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('float'));
        $this->assertSame('float', $this->_platform->getDoctrineTypeMapping('float'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('real'));
        $this->assertSame('float', $this->_platform->getDoctrineTypeMapping('real'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('double'));
        $this->assertSame('float', $this->_platform->getDoctrineTypeMapping('double'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('double precision'));
        $this->assertSame('float', $this->_platform->getDoctrineTypeMapping('double precision'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('smalldatetime'));
        $this->assertSame('datetime', $this->_platform->getDoctrineTypeMapping('smalldatetime'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('datetime'));
        $this->assertSame('datetime', $this->_platform->getDoctrineTypeMapping('datetime'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('char'));
        $this->assertSame('string', $this->_platform->getDoctrineTypeMapping('char'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('varchar'));
        $this->assertSame('string', $this->_platform->getDoctrineTypeMapping('varchar'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('text'));
        $this->assertSame('text', $this->_platform->getDoctrineTypeMapping('text'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('nchar'));
        $this->assertSame('string', $this->_platform->getDoctrineTypeMapping('nchar'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('nvarchar'));
        $this->assertSame('string', $this->_platform->getDoctrineTypeMapping('nvarchar'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('ntext'));
        $this->assertSame('text', $this->_platform->getDoctrineTypeMapping('ntext'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('binary'));
        $this->assertSame('binary', $this->_platform->getDoctrineTypeMapping('binary'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('varbinary'));
        $this->assertSame('binary', $this->_platform->getDoctrineTypeMapping('varbinary'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('image'));
        $this->assertSame('blob', $this->_platform->getDoctrineTypeMapping('image'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('uniqueidentifier'));
        $this->assertSame('guid', $this->_platform->getDoctrineTypeMapping('uniqueidentifier'));
    }

    protected function getBinaryMaxLength()
    {
        return 8000;
    }

    public function testReturnsBinaryTypeDeclarationSQL()
    {
        $this->assertSame('VARBINARY(255)', $this->_platform->getBinaryTypeDeclarationSQL(array()));
        $this->assertSame('VARBINARY(255)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 0)));
        $this->assertSame('VARBINARY(8000)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 8000)));
        $this->assertSame('VARBINARY(MAX)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 8001)));

        $this->assertSame('BINARY(255)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true)));
        $this->assertSame('BINARY(255)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 0)));
        $this->assertSame('BINARY(8000)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 8000)));
        $this->assertSame('VARBINARY(MAX)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 8001)));
    }

    /**
     * @group DBAL-234
     */
    protected function getAlterTableRenameIndexSQL()
    {
        return array(
            "EXEC sp_RENAME N'mytable.idx_foo', N'idx_bar', N'INDEX'",
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexSQL()
    {
        return array(
            "EXEC sp_RENAME N'[table].[create]', N'[select]', N'INDEX'",
            "EXEC sp_RENAME N'[table].[foo]', N'[bar]', N'INDEX'",
        );
    }
}
