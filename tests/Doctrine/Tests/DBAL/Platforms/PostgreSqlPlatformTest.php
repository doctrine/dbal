<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;

class PostgreSqlPlatformTest extends AbstractPlatformTestCase
{
    public function createPlatform()
    {
        return new PostgreSqlPlatform;
    }

    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id SERIAL NOT NULL, test VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return array(
            'CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL)',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)'
        );
    }

    public function getGenerateAlterTableSql()
    {
        return array(
            'ALTER TABLE mytable ADD quota INT DEFAULT NULL',
            'ALTER TABLE mytable DROP foo',
            'ALTER TABLE mytable ALTER bar TYPE VARCHAR(255)',
            "ALTER TABLE mytable ALTER bar SET DEFAULT 'def'",
            'ALTER TABLE mytable ALTER bar SET NOT NULL',
            'ALTER TABLE mytable ALTER bloo TYPE BOOLEAN',
            "ALTER TABLE mytable ALTER bloo SET DEFAULT 'false'",
            'ALTER TABLE mytable ALTER bloo SET NOT NULL',
            'ALTER TABLE mytable RENAME TO userlist',
        );
    }

    public function getGenerateIndexSql()
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateForeignKeySql()
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id) NOT DEFERRABLE INITIALLY IMMEDIATE';
    }

    public function testGeneratesForeignKeySqlForNonStandardOptions()
    {
        $foreignKey = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
                array('foreign_id'), 'my_table', array('id'), 'my_fk', array('onDelete' => 'CASCADE')
        );
        $this->assertEquals(
            "CONSTRAINT my_fk FOREIGN KEY (foreign_id) REFERENCES my_table (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE",
            $this->_platform->getForeignKeyDeclarationSQL($foreignKey)
        );

        $foreignKey = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
            array('foreign_id'), 'my_table', array('id'), 'my_fk', array('match' => 'full')
        );
        $this->assertEquals(
            "CONSTRAINT my_fk FOREIGN KEY (foreign_id) REFERENCES my_table (id) MATCH full NOT DEFERRABLE INITIALLY IMMEDIATE",
            $this->_platform->getForeignKeyDeclarationSQL($foreignKey)
        );

        $foreignKey = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
            array('foreign_id'), 'my_table', array('id'), 'my_fk', array('deferrable' => true)
        );
        $this->assertEquals(
            "CONSTRAINT my_fk FOREIGN KEY (foreign_id) REFERENCES my_table (id) DEFERRABLE INITIALLY IMMEDIATE",
            $this->_platform->getForeignKeyDeclarationSQL($foreignKey)
        );

        $foreignKey = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
            array('foreign_id'), 'my_table', array('id'), 'my_fk', array('deferred' => true)
        );
        $this->assertEquals(
            "CONSTRAINT my_fk FOREIGN KEY (foreign_id) REFERENCES my_table (id) NOT DEFERRABLE INITIALLY DEFERRED",
            $this->_platform->getForeignKeyDeclarationSQL($foreignKey)
        );

        $foreignKey = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
            array('foreign_id'), 'my_table', array('id'), 'my_fk', array('feferred' => true)
        );
        $this->assertEquals(
            "CONSTRAINT my_fk FOREIGN KEY (foreign_id) REFERENCES my_table (id) NOT DEFERRABLE INITIALLY DEFERRED",
            $this->_platform->getForeignKeyDeclarationSQL($foreignKey)
        );

        $foreignKey = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
            array('foreign_id'), 'my_table', array('id'), 'my_fk', array('deferrable' => true, 'deferred' => true, 'match' => 'full')
        );
        $this->assertEquals(
            "CONSTRAINT my_fk FOREIGN KEY (foreign_id) REFERENCES my_table (id) MATCH full DEFERRABLE INITIALLY DEFERRED",
            $this->_platform->getForeignKeyDeclarationSQL($foreignKey)
        );
    }

    public function testGeneratesSqlSnippets()
    {
        $this->assertEquals('SIMILAR TO', $this->_platform->getRegexpExpression(), 'Regular expression operator is not correct');
        $this->assertEquals('"', $this->_platform->getIdentifierQuoteCharacter(), 'Identifier quote character is not correct');
        $this->assertEquals('column1 || column2 || column3', $this->_platform->getConcatExpression('column1', 'column2', 'column3'), 'Concatenation expression is not correct');
        $this->assertEquals('SUBSTRING(column FROM 5)', $this->_platform->getSubstringExpression('column', 5), 'Substring expression without length is not correct');
        $this->assertEquals('SUBSTRING(column FROM 1 FOR 5)', $this->_platform->getSubstringExpression('column', 1, 5), 'Substring expression with length is not correct');
    }

    public function testGeneratesTransactionCommands()
    {
        $this->assertEquals(
            'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL READ UNCOMMITTED',
            $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_READ_UNCOMMITTED)
        );
        $this->assertEquals(
            'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL READ COMMITTED',
            $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_READ_COMMITTED)
        );
        $this->assertEquals(
            'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL REPEATABLE READ',
            $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_REPEATABLE_READ)
        );
        $this->assertEquals(
            'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL SERIALIZABLE',
            $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_SERIALIZABLE)
        );
    }

    public function testGeneratesDDLSnippets()
    {
        $this->assertEquals('CREATE DATABASE foobar', $this->_platform->getCreateDatabaseSQL('foobar'));
        $this->assertEquals('DROP DATABASE foobar', $this->_platform->getDropDatabaseSQL('foobar'));
        $this->assertEquals('DROP TABLE foobar', $this->_platform->getDropTableSQL('foobar'));
    }

    public function testGenerateTableWithAutoincrement()
    {
        $table = new \Doctrine\DBAL\Schema\Table('autoinc_table');
        $column = $table->addColumn('id', 'integer');
        $column->setAutoincrement(true);

        $this->assertEquals(array('CREATE TABLE autoinc_table (id SERIAL NOT NULL)'), $this->_platform->getCreateTableSQL($table));
    }

    public function testGeneratesTypeDeclarationForIntegers()
    {
        $this->assertEquals(
            'INT',
            $this->_platform->getIntegerTypeDeclarationSQL(array())
        );
        $this->assertEquals(
            'SERIAL',
            $this->_platform->getIntegerTypeDeclarationSQL(array('autoincrement' => true)
        ));
        $this->assertEquals(
            'SERIAL',
            $this->_platform->getIntegerTypeDeclarationSQL(
                array('autoincrement' => true, 'primary' => true)
        ));
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

    public function getGenerateUniqueIndexSql()
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    public function testGeneratesSequenceSqlCommands()
    {
        $sequence = new \Doctrine\DBAL\Schema\Sequence('myseq', 20, 1);
        $this->assertEquals(
            'CREATE SEQUENCE myseq INCREMENT BY 20 MINVALUE 1 START 1',
            $this->_platform->getCreateSequenceSQL($sequence)
        );
        $this->assertEquals(
            'DROP SEQUENCE myseq CASCADE',
            $this->_platform->getDropSequenceSQL('myseq')
        );
        $this->assertEquals(
            "SELECT NEXTVAL('myseq')",
            $this->_platform->getSequenceNextValSQL('myseq')
        );
    }

    public function testDoesNotPreferIdentityColumns()
    {
        $this->assertFalse($this->_platform->prefersIdentityColumns());
    }

    public function testPrefersSequences()
    {
        $this->assertTrue($this->_platform->prefersSequences());
    }

    public function testSupportsIdentityColumns()
    {
        $this->assertTrue($this->_platform->supportsIdentityColumns());
    }

    public function testSupportsSavePoints()
    {
        $this->assertTrue($this->_platform->supportsSavepoints());
    }

    public function testSupportsSequences()
    {
        $this->assertTrue($this->_platform->supportsSequences());
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

    public function getCreateTableColumnCommentsSQL()
    {
        return array(
            "CREATE TABLE test (id INT NOT NULL, PRIMARY KEY(id))",
            "COMMENT ON COLUMN test.id IS 'This is a comment'",
        );
    }

    public function getAlterTableColumnCommentsSQL()
    {
        return array(
            "ALTER TABLE mytable ADD quota INT NOT NULL",
            "COMMENT ON COLUMN mytable.quota IS 'A comment'",
            "COMMENT ON COLUMN mytable.foo IS NULL",
            "COMMENT ON COLUMN mytable.baz IS 'B comment'",
        );
    }

    public function getCreateTableColumnTypeCommentsSQL()
    {
        return array(
            "CREATE TABLE test (id INT NOT NULL, data TEXT NOT NULL, PRIMARY KEY(id))",
            "COMMENT ON COLUMN test.data IS '(DC2Type:array)'"
        );
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
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, "bar" VARCHAR(255) NOT NULL)',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES "foreign" ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES foo ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar") REFERENCES "foo-bar" ("create", bar, "foo-bar") NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
    }

    /**
     * @group DBAL-457
     */
    public function testConvertBooleanAsStrings()
    {
        $platform = $this->createPlatform();

        $this->assertEquals('true', $platform->convertBooleans(true));
        $this->assertEquals('false', $platform->convertBooleans(false));
    }

    /**
     * @group DBAL-457
     */
    public function testConvertBooleanAsIntegers()
    {
        $platform = $this->createPlatform();
        $platform->setUseBooleanTrueFalseStrings(false);

        $this->assertEquals('1', $platform->convertBooleans(true));
        $this->assertEquals('0', $platform->convertBooleans(false));
    }

    public function testAlterDecimalPrecisionScale()
    {
        $table = new Table('mytable');
        $table->addColumn('dfoo1', 'decimal');
        $table->addColumn('dfoo2', 'decimal', array('precision' => 10, 'scale' => 6));
        $table->addColumn('dfoo3', 'decimal', array('precision' => 10, 'scale' => 6));
        $table->addColumn('dfoo4', 'decimal', array('precision' => 10, 'scale' => 6));

        $tableDiff = new TableDiff('mytable');
        $tableDiff->fromTable = $table;

        $tableDiff->changedColumns['dloo1'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'dloo1', new \Doctrine\DBAL\Schema\Column(
                'dloo1', \Doctrine\DBAL\Types\Type::getType('decimal'), array('precision' => 16, 'scale' => 6)
            ),
            array('precision')
        );
        $tableDiff->changedColumns['dloo2'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'dloo2', new \Doctrine\DBAL\Schema\Column(
                'dloo2', \Doctrine\DBAL\Types\Type::getType('decimal'), array('precision' => 10, 'scale' => 4)
            ),
            array('scale')
        );
        $tableDiff->changedColumns['dloo3'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'dloo3', new \Doctrine\DBAL\Schema\Column(
                'dloo3', \Doctrine\DBAL\Types\Type::getType('decimal'), array('precision' => 10, 'scale' => 6)
            ),
            array()
        );
        $tableDiff->changedColumns['dloo4'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'dloo4', new \Doctrine\DBAL\Schema\Column(
                'dloo4', \Doctrine\DBAL\Types\Type::getType('decimal'), array('precision' => 16, 'scale' => 8)
            ),
            array('precision', 'scale')
        );

        $sql = $this->_platform->getAlterTableSQL($tableDiff);

        $expectedSql = array(
            'ALTER TABLE mytable ALTER dloo1 TYPE NUMERIC(16, 6)',
            'ALTER TABLE mytable ALTER dloo2 TYPE NUMERIC(10, 4)',
            'ALTER TABLE mytable ALTER dloo4 TYPE NUMERIC(16, 8)',
        );

        $this->assertEquals($expectedSql, $sql);
    }

    /**
     * @group DBAL-365
     */
    public function testDroppingConstraintsBeforeColumns()
    {
        $newTable = new Table('mytable');
        $newTable->addColumn('id', 'integer');
        $newTable->setPrimaryKey(array('id'));

        $oldTable = clone $newTable;
        $oldTable->addColumn('parent_id', 'integer');
        $oldTable->addUnnamedForeignKeyConstraint('mytable', array('parent_id'), array('id'));

        $comparator = new \Doctrine\DBAL\Schema\Comparator();
        $tableDiff = $comparator->diffTable($oldTable, $newTable);

        $sql = $this->_platform->getAlterTableSQL($tableDiff);

        $expectedSql = array(
            'ALTER TABLE mytable DROP CONSTRAINT FK_6B2BD609727ACA70',
            'DROP INDEX IDX_6B2BD609727ACA70',
            'ALTER TABLE mytable DROP parent_id',
        );

        $this->assertEquals($expectedSql, $sql);
    }
}

