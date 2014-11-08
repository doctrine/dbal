<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

require_once __DIR__ . '/../../TestInit.php';

class OraclePlatformTest extends AbstractPlatformTestCase
{
    static public function dataValidIdentifiers()
    {
        return array(
            array('a'),
            array('foo'),
            array('Foo'),
            array('Foo123'),
            array('Foo#bar_baz$'),
            array('"a"'),
            array('"1"'),
            array('"foo_bar"'),
            array('"@$%&!"'),
        );
    }

    /**
     * @dataProvider dataValidIdentifiers
     */
    public function testValidIdentifiers($identifier)
    {
        $platform = $this->createPlatform();
        $platform->assertValidIdentifier($identifier);
    }

    static public function dataInvalidIdentifiers()
    {
        return array(
            array('1'),
            array('abc&'),
            array('abc-def'),
            array('"'),
            array('"foo"bar"'),
        );
    }

    /**
     * @dataProvider dataInvalidIdentifiers
     */
    public function testInvalidIdentifiers($identifier)
    {
        $this->setExpectedException('Doctrine\DBAL\DBALException');
        $platform = $this->createPlatform();
        $platform->assertValidIdentifier($identifier);
    }

    public function createPlatform()
    {
        return new OraclePlatform;
    }

    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id NUMBER(10) NOT NULL, test VARCHAR2(255) DEFAULT NULL, PRIMARY KEY(id))';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return array(
            'CREATE TABLE test (foo VARCHAR2(255) DEFAULT NULL, bar VARCHAR2(255) DEFAULT NULL)',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)',
        );
    }

    public function getGenerateAlterTableSql()
    {
        return array(
            'ALTER TABLE mytable ADD (quota NUMBER(10) DEFAULT NULL)',
            "ALTER TABLE mytable MODIFY (baz  VARCHAR2(255) DEFAULT 'def' NOT NULL, bloo  NUMBER(1) DEFAULT '0' NOT NULL)",
            "ALTER TABLE mytable DROP (foo)",
            "ALTER TABLE mytable RENAME TO userlist",
        );
    }

    /**
     * @expectedException Doctrine\DBAL\DBALException
     */
    public function testRLike()
    {
        $this->assertEquals('RLIKE', $this->_platform->getRegexpExpression(), 'Regular expression operator is not correct');
    }

    public function testGeneratesSqlSnippets()
    {
        $this->assertEquals('"', $this->_platform->getIdentifierQuoteCharacter(), 'Identifier quote character is not correct');
        $this->assertEquals('column1 || column2 || column3', $this->_platform->getConcatExpression('column1', 'column2', 'column3'), 'Concatenation expression is not correct');
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
            'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE',
            $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_REPEATABLE_READ)
        );
        $this->assertEquals(
            'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE',
            $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_SERIALIZABLE)
        );
    }

    /**
     * @expectedException Doctrine\DBAL\DBALException
     */
    public function testCreateDatabaseThrowsException()
    {
        $this->assertEquals('CREATE DATABASE foobar', $this->_platform->getCreateDatabaseSQL('foobar'));
    }

    public function testDropDatabaseThrowsException()
    {
        $this->assertEquals('DROP USER foobar CASCADE', $this->_platform->getDropDatabaseSQL('foobar'));
    }

    public function testDropTable()
    {
        $this->assertEquals('DROP TABLE foobar', $this->_platform->getDropTableSQL('foobar'));
    }

    public function testGeneratesTypeDeclarationForIntegers()
    {
        $this->assertEquals(
            'NUMBER(10)',
            $this->_platform->getIntegerTypeDeclarationSQL(array())
        );
        $this->assertEquals(
            'NUMBER(10)',
            $this->_platform->getIntegerTypeDeclarationSQL(array('autoincrement' => true)
        ));
        $this->assertEquals(
            'NUMBER(10)',
            $this->_platform->getIntegerTypeDeclarationSQL(
                array('autoincrement' => true, 'primary' => true)
        ));
    }

    public function testGeneratesTypeDeclarationsForStrings()
    {
        $this->assertEquals(
            'CHAR(10)',
            $this->_platform->getVarcharTypeDeclarationSQL(
                array('length' => 10, 'fixed' => true)
        ));
        $this->assertEquals(
            'VARCHAR2(50)',
            $this->_platform->getVarcharTypeDeclarationSQL(array('length' => 50)),
            'Variable string declaration is not correct'
        );
        $this->assertEquals(
            'VARCHAR2(255)',
            $this->_platform->getVarcharTypeDeclarationSQL(array()),
            'Long string declaration is not correct'
        );
    }

    public function testPrefersIdentityColumns()
    {
        $this->assertFalse($this->_platform->prefersIdentityColumns());
    }

    public function testSupportsIdentityColumns()
    {
        $this->assertFalse($this->_platform->supportsIdentityColumns());
    }

    public function testSupportsSavePoints()
    {
        $this->assertTrue($this->_platform->supportsSavepoints());
    }

    public function getGenerateIndexSql()
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateUniqueIndexSql()
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    public function getGenerateForeignKeySql()
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
    }

    /**
     * @group DBAL-1097
     *
     * @dataProvider getGeneratesAdvancedForeignKeyOptionsSQLData
     */
    public function testGeneratesAdvancedForeignKeyOptionsSQL(array $options, $expectedSql)
    {
        $foreignKey = new ForeignKeyConstraint(array('foo'), 'foreign_table', array('bar'), null, $options);

        $this->assertSame($expectedSql, $this->_platform->getAdvancedForeignKeyOptionsSQL($foreignKey));
    }

    /**
     * @return array
     */
    public function getGeneratesAdvancedForeignKeyOptionsSQLData()
    {
        return array(
            array(array(), ''),
            array(array('onUpdate' => 'CASCADE'), ''),
            array(array('onDelete' => 'CASCADE'), ' ON DELETE CASCADE'),
            array(array('onDelete' => 'NO ACTION'), ''),
            array(array('onDelete' => 'RESTRICT'), ''),
            array(array('onUpdate' => 'SET NULL', 'onDelete' => 'SET NULL'), ' ON DELETE SET NULL'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnsForeignKeyReferentialActionSQL()
    {
        return array(
            array('CASCADE', 'CASCADE'),
            array('SET NULL', 'SET NULL'),
            array('NO ACTION', ''),
            array('RESTRICT', ''),
            array('CaScAdE', 'CASCADE'),
        );
    }

    public function testModifyLimitQuery()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        $this->assertEquals('SELECT a.* FROM (SELECT * FROM user) a WHERE ROWNUM <= 10', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10);
        $this->assertEquals('SELECT a.* FROM (SELECT * FROM user) a WHERE ROWNUM <= 10', $sql);
    }

    public function testModifyLimitQueryWithAscOrderBy()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username ASC', 10);
        $this->assertEquals('SELECT a.* FROM (SELECT * FROM user ORDER BY username ASC) a WHERE ROWNUM <= 10', $sql);
    }

    public function testModifyLimitQueryWithDescOrderBy()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10);
        $this->assertEquals('SELECT a.* FROM (SELECT * FROM user ORDER BY username DESC) a WHERE ROWNUM <= 10', $sql);
    }

    public function testGenerateTableWithAutoincrement()
    {
        $columnName = strtoupper('id' . uniqid());
        $tableName = strtoupper('table' . uniqid());
        $table = new \Doctrine\DBAL\Schema\Table($tableName);
        $column = $table->addColumn($columnName, 'integer');
        $column->setAutoincrement(true);
        $targets = array(
          "CREATE TABLE {$tableName} ({$columnName} NUMBER(10) NOT NULL)",
          "DECLARE constraints_Count NUMBER; BEGIN SELECT COUNT(CONSTRAINT_NAME) INTO constraints_Count FROM USER_CONSTRAINTS WHERE TABLE_NAME = '{$tableName}' AND CONSTRAINT_TYPE = 'P'; IF constraints_Count = 0 OR constraints_Count = '' THEN EXECUTE IMMEDIATE 'ALTER TABLE {$tableName} ADD CONSTRAINT {$tableName}_AI_PK PRIMARY KEY ({$columnName})'; END IF; END;",
          "CREATE SEQUENCE {$tableName}_{$columnName}_SEQ START WITH 1 MINVALUE 1 INCREMENT BY 1",
          "CREATE TRIGGER {$tableName}_AI_PK BEFORE INSERT ON {$tableName} FOR EACH ROW DECLARE last_Sequence NUMBER; last_InsertID NUMBER; BEGIN SELECT {$tableName}_{$columnName}_SEQ.NEXTVAL INTO :NEW.{$columnName} FROM DUAL; IF (:NEW.{$columnName} IS NULL OR :NEW.{$columnName} = 0) THEN SELECT {$tableName}_{$columnName}_SEQ.NEXTVAL INTO :NEW.{$columnName} FROM DUAL; ELSE SELECT NVL(Last_Number, 0) INTO last_Sequence FROM User_Sequences WHERE Sequence_Name = '{$tableName}_{$columnName}_SEQ'; SELECT :NEW.{$columnName} INTO last_InsertID FROM DUAL; WHILE (last_InsertID > last_Sequence) LOOP SELECT {$tableName}_{$columnName}_SEQ.NEXTVAL INTO last_Sequence FROM DUAL; END LOOP; END IF; END;"
        );
        $statements = $this->_platform->getCreateTableSQL($table);
        //strip all the whitespace from the statements
        array_walk($statements, function(&$value){
          $value = preg_replace('/\s+/', ' ',$value);
        });
        foreach($targets as $key => $sql){
          $this->assertArrayHasKey($key,$statements);
          $this->assertEquals($sql, $statements[$key]);
        }
    }

    public function getCreateTableColumnCommentsSQL()
    {
        return array(
            "CREATE TABLE test (id NUMBER(10) NOT NULL, PRIMARY KEY(id))",
            "COMMENT ON COLUMN test.id IS 'This is a comment'",
        );
    }

    public function getCreateTableColumnTypeCommentsSQL()
    {
        return array(
            "CREATE TABLE test (id NUMBER(10) NOT NULL, data CLOB NOT NULL, PRIMARY KEY(id))",
            "COMMENT ON COLUMN test.data IS '(DC2Type:array)'"
        );
    }

    public function getAlterTableColumnCommentsSQL()
    {
        return array(
            "ALTER TABLE mytable ADD (quota NUMBER(10) NOT NULL)",
            "COMMENT ON COLUMN mytable.quota IS 'A comment'",
            "COMMENT ON COLUMN mytable.foo IS ''",
            "COMMENT ON COLUMN mytable.baz IS 'B comment'",
        );
    }

    public function getBitAndComparisonExpressionSql($value1, $value2)
    {
        return 'BITAND('.$value1 . ', ' . $value2 . ')';
    }

    public function getBitOrComparisonExpressionSql($value1, $value2)
    {
        return '(' . $value1 . '-' .
                $this->getBitAndComparisonExpressionSql($value1, $value2)
                . '+' . $value2 . ')';
    }

    protected function getQuotedColumnInPrimaryKeySQL()
    {
        return array('CREATE TABLE "quoted" ("create" VARCHAR2(255) NOT NULL, PRIMARY KEY("create"))');
    }

    protected function getQuotedColumnInIndexSQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR2(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB ON "quoted" ("create")',
        );
    }

    protected function getQuotedNameInIndexSQL()
    {
        return array(
            'CREATE TABLE test (column1 VARCHAR2(255) NOT NULL)',
            'CREATE INDEX "create" ON test (column1)',
        );
    }

    protected function getQuotedColumnInForeignKeySQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR2(255) NOT NULL, foo VARCHAR2(255) NOT NULL, "bar" VARCHAR2(255) NOT NULL)',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES foreign ("create", bar, "foo-bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES foo ("create", bar, "foo-bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar") REFERENCES "foo-bar" ("create", bar, "foo-bar")',
        );
    }

    public function testAlterTableNotNULL()
    {
        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff('mytable');
        $tableDiff->changedColumns['foo'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'foo', new \Doctrine\DBAL\Schema\Column(
                'foo', \Doctrine\DBAL\Types\Type::getType('string'), array('default' => 'bla', 'notnull' => true)
            ),
            array('type')
        );
        $tableDiff->changedColumns['bar'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'bar', new \Doctrine\DBAL\Schema\Column(
                'baz', \Doctrine\DBAL\Types\Type::getType('string'), array('default' => 'bla', 'notnull' => true)
            ),
            array('type', 'notnull')
        );

        $expectedSql = array(
            "ALTER TABLE mytable MODIFY (foo  VARCHAR2(255) DEFAULT 'bla', baz  VARCHAR2(255) DEFAULT 'bla' NOT NULL)",
	);
        $this->assertEquals($expectedSql, $this->_platform->getAlterTableSQL($tableDiff));
    }

    /**
     * {@inheritdoc}
     */
    protected function getAlterStringToFixedStringSQL()
    {
        return array(
            'ALTER TABLE mytable MODIFY (name  CHAR(2) DEFAULT NULL)',
        );
    }
}
