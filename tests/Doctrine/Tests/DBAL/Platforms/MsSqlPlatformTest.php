<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\MsSqlPlatform;
use Doctrine\DBAL\Types\Type;

require_once __DIR__ . '/../../TestInit.php';

class MsSqlPlatformTest extends AbstractPlatformTestCase
{

    public function createPlatform()
    {
        return new MsSqlPlatform;
    }

    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id INT IDENTITY NOT NULL, test NVARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return array(
            'CREATE TABLE test (foo NVARCHAR(255) DEFAULT NULL, bar NVARCHAR(255) DEFAULT NULL)',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar) WHERE foo IS NOT NULL AND bar IS NOT NULL'
        );
    }

    public function getGenerateAlterTableSql()
    {
        return array(
            'ALTER TABLE mytable RENAME TO userlist',
            'ALTER TABLE mytable ADD quota INT DEFAULT NULL',
            'ALTER TABLE mytable DROP COLUMN foo',
            'ALTER TABLE mytable CHANGE bar baz NVARCHAR(255) DEFAULT \'def\' NOT NULL',
        );
    }

    public function testGeneratesSqlSnippets()
    {
        $this->assertEquals('RLIKE', $this->_platform->getRegexpExpression(), 'Regular expression operator is not correct');
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

        $this->assertEquals('SHOW DATABASES', $this->_platform->getShowDatabasesSQL());
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
    }

    public function testPrefersIdentityColumns()
    {
        $this->assertTrue($this->_platform->prefersIdentityColumns());
    }

    public function testSupportsIdentityColumns()
    {
        $this->assertTrue($this->_platform->supportsIdentityColumns());
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
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table(id)';
    }

    public function testModifyLimitQuery()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        $this->assertEquals('SELECT TOP 10 * FROM user', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10);
        $this->assertEquals('SELECT TOP 10 * FROM user', $sql);
    }

    public function testModifyLimitQueryWithOffset()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10, 5);
        $this->assertEquals('SELECT * FROM (SELECT ROW_NUMBER() OVER (ORDER BY username DESC) AS "doctrine_rownum", * FROM user) AS doctrine_tbl WHERE "doctrine_rownum" BETWEEN 6 AND 15', $sql);
    }

    public function testModifyLimitQueryWithAscOrderBy()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username ASC', 10);
        $this->assertEquals('SELECT TOP 10 * FROM user ORDER BY username ASC', $sql);
    }

    public function testModifyLimitQueryWithDescOrderBy()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10);
        $this->assertEquals('SELECT TOP 10 * FROM user ORDER BY username DESC', $sql);
    }

    public function testQuoteIdentifier()
    {
        $this->assertEquals('[fo][o]', $this->_platform->quoteIdentifier('fo]o'));
    }
}