<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\InformixPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;

require_once __DIR__ . '/../../TestInit.php';

class InformixPlatformTest extends AbstractPlatformTestCase
{

    public function createPlatform()
    {
        return new InformixPlatform();
    }

    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id SERIAL NOT NULL, '
            . 'test VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return array(
            'CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL)',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)'
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

    public function getGenerateForeignKeySql()
    {
        return 'ALTER TABLE test ADD CONSTRAINT FOREIGN KEY (fk_name_id) '
            . 'REFERENCES other_table (id)';
    }

    public function getGenerateConstraintUniqueIndexSql()
    {
        return 'ALTER TABLE test ADD CONSTRAINT UNIQUE (test) '
            . 'CONSTRAINT constraint_name';
    }

    public function getGenerateConstraintPrimaryIndexSql()
    {
        return 'ALTER TABLE test ADD CONSTRAINT PRIMARY KEY (test) '
            . 'CONSTRAINT constraint_name';
    }

    public function getGenerateConstraintForeignKeySql(ForeignKeyConstraint $fk)
    {
        $quotedForeignTable = $fk->getQuotedForeignTableName($this->_platform);

        return 'ALTER TABLE test ADD CONSTRAINT FOREIGN KEY (fk_name) '
            . 'REFERENCES ' . $quotedForeignTable . ' (id) CONSTRAINT constraint_fk';
    }

    public function getBitAndComparisonExpressionSql($value1, $value2)
    {
        return 'BITAND(' . $value1 . ', ' .  $value2 . ')';
    }

    public function getBitOrComparisonExpressionSql($value1, $value2)
    {
        return 'BITOR(' . $value1 . ', ' . $value2 . ')';
    }

    public function getGenerateAlterTableSql()
    {
        return array(
            'RENAME COLUMN mytable.bar TO baz',
            'ALTER TABLE mytable ADD quota INTEGER DEFAULT NULL, DROP foo, '
            . 'MODIFY baz VARCHAR(255) DEFAULT \'def\' NOT NULL, '
            . 'MODIFY bloo BOOLEAN DEFAULT \'f\' NOT NULL',
            'RENAME TABLE mytable TO userlist',
        );
    }

    public function getQuotedColumnInPrimaryKeySQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, '
            . 'PRIMARY KEY("create"))'
        );
    }

    public function getQuotedColumnInIndexSQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB ON "quoted" ("create")'
        );
    }

    public function getQuotedColumnInForeignKeySQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, '
            . 'foo VARCHAR(255) NOT NULL, "bar" VARCHAR(255) NOT NULL)',
            'ALTER TABLE "quoted" ADD CONSTRAINT FOREIGN KEY ("create", foo, '
            . '"bar") REFERENCES "foreign" ("create", bar, "foo-bar") '
            . 'CONSTRAINT FK_WITH_RESERVED_KEYWORD',
            'ALTER TABLE "quoted" ADD CONSTRAINT FOREIGN KEY ("create", foo, '
            . '"bar") REFERENCES foo ("create", bar, "foo-bar") '
            . 'CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD',
            'ALTER TABLE "quoted" ADD CONSTRAINT FOREIGN KEY ("create", foo, '
            . '"bar") REFERENCES "foo-bar" ("create", bar, "foo-bar") '
            . 'CONSTRAINT FK_WITH_INTENDED_QUOTATION'
        );
    }

    public function testReturnsBinaryTypeDeclarationSQL()
    {
        $this->assertSame(
            'BYTE', 
            $this->_platform->getBinaryTypeDeclarationSQL(array())
        );

        $this->assertSame(
            'BYTE', 
            $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 0))
        );

        $this->assertSame(
            'BYTE', 
            $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 9999999))
        );

        $this->assertSame(
            'BYTE', 
            $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true))
        );

        $this->assertSame(
            'BYTE', 
            $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 0))
        );

        $this->assertSame(
            'BYTE', 
            $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 9999999))
        );
    }

    public function testConvertBooleans()
    {
        $this->assertSame(
            array('t', 'f', 't', 'f'),
            $this->_platform->convertBooleans(array(true, false, true, false))
        );

        $this->assertSame('t', $this->_platform->convertBooleans(true));
        $this->assertSame('f', $this->_platform->convertBooleans(false));
    }

    public function testGeneratesTypeDeclarationForStrings()
    {
        $this->assertEquals(
            'CHAR(150)',
            $this->_platform->getVarcharTypeDeclarationSQL(
                array('fixed' => true, 'length' => 150)
            )
        );

        $this->assertEquals(
            'VARCHAR(150)',
            $this->_platform->getVarcharTypeDeclarationSQL(
                array('fixed' => false, 'length' => 150)
            )
        );

        $this->assertEquals(
            $this->_platform->getClobTypeDeclarationSQL(
                array('fixed' => true, 'length' => 2000)
            ),
            $this->_platform->getVarcharTypeDeclarationSQL(
                array('fixed' => true, 'length' => 2000)
            )
        );

        $this->assertEquals(
            $this->_platform->getClobTypeDeclarationSQL(
                array('fixed' => false, 'length' => 2000)
            ),
            $this->_platform->getVarcharTypeDeclarationSQL(
                array('fixed' => false, 'length' => 2000)
            )
        );
    }

    public function testHasCorrectPlatformName()
    {
        $this->assertEquals('informix', $this->_platform->getName());
    }

    public function testHasCorrectIdentifierQuoteCharacter()
    {
        $this->assertSame('"', $this->_platform->getIdentifierQuoteCharacter());
    }

    public function testHasCorrectMaxIdentifierLength()
    {
        $this->assertSame(128, $this->_platform->getMaxIdentifierLength());
    }

    public function testGeneratesSQLSnippets()
    {
        $this->assertEquals('TODAY', $this->_platform->getNowExpression());

        $this->assertEquals('TODAY', $this->_platform->getCurrentDateSQL());

        $this->assertEquals(
            'CURRENT HOUR TO SECOND',
            $this->_platform->getCurrentTimeSQL()
        );

        $this->assertEquals(
            'CURRENT YEAR TO SECOND',
            $this->_platform->getCurrentTimestampSQL()
        );

        $this->assertEquals(
            "'01/20/2014'::DATE - '01/18/2014'::DATE",
            $this->_platform->getDateDiffExpression("'01/20/2014'", "'01/18/2014'")
        );

        $this->assertEquals(
            'CURRENT + INTERVAL(5) HOUR(9) TO HOUR',
            $this->_platform->getDateAddHourExpression('CURRENT', 5)
        );

        $this->assertEquals(
            'CURRENT - INTERVAL(5) HOUR(9) TO HOUR',
            $this->_platform->getDateSubHourExpression('CURRENT', 5)
        );

        $this->assertEquals(
            'CURRENT + INTERVAL(5) DAY(9) TO DAY',
            $this->_platform->getDateAddDaysExpression('CURRENT', 5)
        );

        $this->assertEquals(
            'CURRENT - INTERVAL(5) DAY(9) TO DAY',
            $this->_platform->getDateSubDaysExpression('CURRENT', 5)
        );

        $this->assertEquals(
            'ADD_MONTHS(CURRENT,5)',
            $this->_platform->getDateAddMonthExpression('CURRENT', 5)
        );

        $this->assertEquals(
            'ADD_MONTHS(CURRENT,-5)',
            $this->_platform->getDateSubMonthExpression('CURRENT', 5)
        );

        $this->assertEquals(
            'CREATE TEMP TABLE',
            $this->_platform->getCreateTemporaryTableSnippetSQL()
        );

        $this->assertEquals(
            'SUBSTRING(col1 FROM 2)',
            $this->_platform->getSubstringExpression('col1', 2)
        );

        $this->assertEquals(
            'SUBSTRING(col1 FROM 2 FOR 4)',
            $this->_platform->getSubstringExpression('col1', 2, 4)
        );

        $this->assertEquals(
            'FOR UPDATE',
            $this->_platform->getForUpdateSQL()
        );
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     * @expectedExceptionMessage not supported by platform
     * @dataProvider dataProviderTestThrowsNotSupportedExceptions
     */
    public function testThrowsNotSupportedExceptions($methodName, $p1 = null, $p2 = null)
    {
        $this->_platform->$methodName($p1, $p2);
    }

    public function dataProviderTestThrowsNotSupportedExceptions()
    {
        return array(
            array('getIndexDeclarationSQL', 'idx1', new Index('idx1', array('col1'))),
            array('getListUsersSQL'),
            array('getMd5Expression', 'column'),
            array('getNotExpression', 'expression'),
            array('getPiExpression')
        );
    }

    public function testReturnsDropForeignKeySQL()
    {

        $table = new Table('test');
        $table->addColumn('fk1', 'string');

        $fk = new ForeignKeyConstraint(
            array('fk1'), 'foreign_table', array('id'), 'fk1_constraint'
        );

        $this->assertEquals(
            'ALTER TABLE test DROP CONSTRAINT fk1_constraint',
            $this->_platform->getDropForeignKeySQL($fk, $table)
        );

        $this->assertEquals(
            'ALTER TABLE test DROP CONSTRAINT fk1_constraint',
            $this->_platform->getDropForeignKeySQL($fk->getName(), $table->getName())
        );

    }

    public function testReturnsCommentOnColumnSQL()
    {
        $this->assertSame(
            '',
            $this->_platform->getCommentOnColumnSQL('table', 'column', 'comment')
        );
    }

    public function testGeneratesTypeDeclarationForBooleans()
    {
        $this->assertEquals(
            'BOOLEAN',
            $this->_platform->getBooleanTypeDeclarationSQL(array())
        );
    }

    public function testGeneratesTypeDeclarationForIntegers()
    {
        $columnDef['autoincrement'] = false;

        $this->assertEquals(
            'INTEGER',
            $this->_platform->getIntegerTypeDeclarationSQL($columnDef)
        );

        $this->assertEquals(
            'BIGINT',
            $this->_platform->getBigIntTypeDeclarationSQL($columnDef)
        );

        $columnDef['autoincrement'] = true;

        $this->assertEquals(
            'SERIAL',
            $this->_platform->getIntegerTypeDeclarationSQL($columnDef)
        );

        $this->assertEquals(
            'BIGSERIAL',
            $this->_platform->getBigIntTypeDeclarationSQL($columnDef)
        );

        $this->assertEquals(
            'SMALLINT',
            $this->_platform->getSmallIntTypeDeclarationSQL(array())
        );
    }

    public function testGeneratesTypeDeclarationForDateAndTime()
    {
        $this->assertEquals(
            'DATETIME YEAR TO SECOND',
            $this->_platform->getDateTimeTypeDeclarationSQL(array())
        );

        $this->assertEquals(
            'DATE',
            $this->_platform->getDateTypeDeclarationSQL(array())
        );

        $this->assertEquals(
            'DATETIME HOUR TO SECOND',
            $this->_platform->getTimeTypeDeclarationSQL(array())
        );
    }

    public function testReturnsSequenceNextValSQL()
    {
        $this->assertEquals(
            'SELECT sequence.NEXTVAL FROM systables WHERE tabid = 1',
            $this->_platform->getSequenceNextValSQL('sequence')
        );
    }

    public function testGeneratesDDLSnippets()
    {
        $sql = 'SELECT * FROM bar';

        $this->assertEquals(
            'CREATE VIEW foo_view AS ' . $sql,
            $this->_platform->getCreateViewSQL('foo_view', $sql)
        );

        $this->assertEquals(
            'DROP VIEW foo_view',
            $this->_platform->getDropViewSQL('foo_view')
        );

        $this->assertEquals(
            'CREATE DATABASE baz_db WITH LOG',
            $this->_platform->getCreateDatabaseSQL('baz_db')
        );
    }

    public function testReturnsIndexDeclarationSQL()
    {
        $index = new Index('idx1', array('col1', 'col2'), false);

        $this->assertEquals(
            ' UNIQUE (col1, col2) CONSTRAINT idx1',
            $this->_platform->getUniqueConstraintDeclarationSQL('idx1', $index)
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testThrowsExceptionWhenContraintDeclarationHasNoColumns()
    {
        $index = new Index('ctr1', array());

        $this->_platform->getUniqueConstraintDeclarationSQL('ctr1', $index);
    }

    public function testReturnsSequenceSQL()
    {
        $sequence = new Sequence('test_seq', 1, 10);

        $this->assertEquals(
            'CREATE SEQUENCE ' . $sequence->getQuotedName($this->_platform)
            . ' START WITH ' . $sequence->getInitialValue()
            . ' INCREMENT BY ' . $sequence->getAllocationSize()
            . ' MINVALUE ' . $sequence->getInitialValue(),
            $this->_platform->getCreateSequenceSQL($sequence)
        );

        $this->assertEquals(
            'ALTER SEQUENCE ' . $sequence->getQuotedName($this->_platform)
            . ' INCREMENT BY ' . $sequence->getAllocationSize(),
            $this->_platform->getAlterSequenceSQL($sequence)
        );

        $this->assertEquals(
            'DROP SEQUENCE ' . $sequence->getName(),
            $this->_platform->getDropSequenceSQL($sequence)
        );

        $this->assertEquals(
            'DROP SEQUENCE ' . $sequence->getName(),
            $this->_platform->getDropSequenceSQL($sequence->getName())
        );
    }

    /**
     * @dataProvider dataProviderTestDoModifyLimitQuery
     */
    public function testDoModifyLimitQuery($limit, $offset, $sqlExpected)
    {
      $sql = 'SELECT * FROM foobar';

      $this->assertEquals(
          $sqlExpected,
          $this->_platform->modifyLimitQuery($sql, $limit, $offset)
      );
    }

    public function dataProviderTestDoModifyLimitQuery()
    {
        return array(
            array(null, null, 'SELECT * FROM foobar'),
            array(null, 10, 'SELECT SKIP 10 * FROM foobar'),
            array(5, null, 'SELECT LIMIT 5 * FROM foobar'),
            array(5, 10, 'SELECT SKIP 10 LIMIT 5 * FROM foobar'),
        );
    }

    public function testReturnsSQLResultCasing()
    {
        $this->assertEquals(
            'FOO',
            $this->_platform->getSQLResultCasing('Foo')
        );
    }

    public function testGeneratesCreatePrimaryKeySQL()
    {
        $index = new Index('', array('col1', 'col2'));

        $this->assertEquals(
            'ALTER TABLE test_table ADD CONSTRAINT PRIMARY KEY (col1, col2)',
            $this->_platform->getCreatePrimaryKeySQL($index, 'test_table')
        );

        $index = new Index('pk1', array('col1', 'col2'));

        $this->assertEquals(
            'ALTER TABLE test_table ADD CONSTRAINT PRIMARY KEY (col1, col2) '
            . 'CONSTRAINT pk1',
            $this->_platform->getCreatePrimaryKeySQL($index, 'test_table')
        );

    }

    public function testReturnsCorrectAnswerOnSupportedFeatures()
    {
        $this->assertFalse($this->_platform->hasNativeGuidType());
        $this->assertFalse($this->_platform->hasNativeJsonType());
        $this->assertFalse($this->_platform->supportsCommentOnStatement());
        $this->assertFalse($this->_platform->supportsCreateDropDatabase());
        $this->assertFalse($this->_platform->supportsInlineColumnComments());
        $this->assertFalse($this->_platform->supportsSchemas());

        $this->assertTrue($this->_platform->prefersIdentityColumns());
        $this->assertTrue($this->_platform->supportsAlterTable());
        $this->assertTrue($this->_platform->supportsForeignKeyConstraints());
        $this->assertTrue($this->_platform->supportsForeignKeyOnUpdate());
        $this->assertTrue($this->_platform->supportsGettingAffectedRows());
        $this->assertTrue($this->_platform->supportsIdentityColumns());
        $this->assertTrue($this->_platform->supportsIndexes());
        $this->assertTrue($this->_platform->supportsLimitOffset());
        $this->assertTrue($this->_platform->supportsPrimaryConstraints());
        $this->assertTrue($this->_platform->supportsReleaseSavepoints());
        $this->assertTrue($this->_platform->supportsSavepoints());
        $this->assertTrue($this->_platform->supportsSequences());
        $this->assertTrue($this->_platform->supportsTransactions());
        $this->assertTrue($this->_platform->supportsViews());
    }

    public function testGeneratesListTableConstraintsSQL()
    {
        $this->assertEquals(
            'SELECT sc.constrid, sc.constrname, sc.owner, sc.tabid, '
            . 'sc.constrtype, sc.idxname, sc.collation '
            . 'FROM systables st, sysconstraints sc WHERE '
            . 'st.tabname = \'test_table\' '
            . 'AND st.tabid = sc.tabid',
            $this->_platform->getListTableConstraintsSQL('test_table')
        );
    }

    public function getQuotedAlterTableRenameColumnSQL()
    {
        return array(
            'RENAME COLUMN mytable.unquoted1 TO unquoted',
            'RENAME COLUMN mytable.unquoted2 TO "where"',
            'RENAME COLUMN mytable.unquoted3 TO "foo"',
            'RENAME COLUMN mytable.create TO reserved_keyword',
            'RENAME COLUMN mytable.table TO "from"',
            'RENAME COLUMN mytable.select TO "bar"',
            'RENAME COLUMN mytable.quoted1 TO quoted',
            'RENAME COLUMN mytable.quoted2 TO "and"',
            'RENAME COLUMN mytable.quoted3 TO "baz"',
        );
    }

}
