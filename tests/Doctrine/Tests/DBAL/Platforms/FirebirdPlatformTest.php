<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

use Doctrine\DBAL\Schema\Table;

/**
 * Description of FirebirdPlatformTest
 *
 * @author Andreas Prucha, Helicon Software Development
 */
class FirebirdPlatformTest extends \Doctrine\Tests\DBAL\Platforms\AbstractPlatformTestCase
{

    /**
     * {@inheritdoc}
     */
    public function createPlatform()
    {
        return new \Doctrine\DBAL\Platforms\FirebirdPlatform();
    }

    public function testReturnsIdentitySequenceName()
    {
        $this->assertSame('table_with_too_long_naX5824_D2IS', $this->_platform->getIdentitySequenceName('table_with_too_long_name_to_combine', 'column_with_too_long_name_to_combine'));
        $this->assertSame('table_with_too_long_naX5824_D2IS', $this->_platform->getIdentitySequenceName('table_with_too_long_name_to_combine', 'id'));
        $this->assertSame('"QuotedTable_D2IS"', $this->_platform->getIdentitySequenceName('"QuotedTable"', 'id'));
    }

    public function testUsesSequenceEmulatedIdentityColumns()
    {
        $this->assertTrue($this->_platform->usesSequenceEmulatedIdentityColumns());
    }

    public function testReturnsBinaryTypeDeclarationSQL()
    {
        $this->assertSame('VARCHAR(255)', $this->_platform->getBinaryTypeDeclarationSQL(array()));
        $this->assertSame('VARCHAR(255)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 0)));
        $this->assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 65535)));
        $this->assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 65536)));
        $this->assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 16777215)));
        $this->assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 16777216)));

        $this->assertSame('CHAR(255)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true)));
        $this->assertSame('CHAR(255)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 0)));
        $this->assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 65535)));
        $this->assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 65536)));
        $this->assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 16777215)));
        $this->assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 16777216)));
    }

    public function testGeneratesTableAlterationSql()
    {
        $this->markTestSkipped('Firebird does not allow to alter table names');
    }

    public function testQuotesTableIdentifiersInAlterTableSQL()
    {
        $this->markTestSkipped('Firebird does not allow to alter table names');
    }

    public function testAlterTableRenameIndexInSchema()
    {
        $this->markTestSkipped('Firebird does not allow schema references - resulting SQL invalid');
    }

    public function testQuotesAlterTableRenameIndexInSchema()
    {
        $this->markTestSkipped('Firebird does not allow schema references - resulting SQL invalid');
    }

    public function testGeneratesAlterTableRenameIndexUsedByForeignKeySQL()
    {
        return array(
            'ALTER TABLE mytable DROP FOREIGN KEY fk_foo',
            'DROP INDEX idx_foo ON mytable',
            'CREATE INDEX idx_foo_renamed ON mytable (foo)',
            'ALTER TABLE mytable ADD CONSTRAINT fk_foo FOREIGN KEY (foo) REFERENCES foreign_table (id)',
        );
    }

    public function testQuotesAlterTableChangeColumnNotNull()
    {

        $expectedSql = array(
            'UPDATE RDB$RELATION_FIELDS SET RDB$NULL_FLAG = NULL WHERE UPPER(RDB$FIELD_NAME) = UPPER(\'notnull_to_null\') AND UPPER(RDB$RELATION_NAME) = UPPER(\'mytable\')',
            'UPDATE RDB$RELATION_FIELDS SET RDB$NULL_FLAG = 1 WHERE UPPER(RDB$FIELD_NAME) = UPPER(\'null_to_notnull\') AND UPPER(RDB$RELATION_NAME) = UPPER(\'mytable\')'
        );

        $fromTable = new Table('mytable');

        $fromTable->addColumn('notnull_to_null', 'string', array('notnull' => true, 'length' => 255));
        $fromTable->addColumn('null_to_notnull', 'string', array('notnull' => false, 'length' => 255));

        $toTable = new Table('mytable');

        $toTable->addColumn('notnull_to_null', 'string', array('notnull' => false, 'length' => 255));
        $toTable->addColumn('null_to_notnull', 'string', array('notnull' => true, 'length' => 255));

        $comparator = new \Doctrine\DBAL\Schema\Comparator();

        $this->assertEquals(
                $expectedSql, $this->_platform->getAlterTableSQL($comparator->diffTable($fromTable, $toTable))
        );
    }

    /**
     * {@inheritdoc}
     */
    public function testReturnsGuidTypeDeclarationSQL()
    {
        $this->assertSame('CHAR(36)', $this->_platform->getGuidTypeDeclarationSQL(array()));
    }

    /**
     * {@inheritdoc}
     */
    protected function getAlterStringToFixedStringSQL()
    {
        return array(
            'ALTER TABLE mytable ALTER COLUMN name TYPE CHAR(2)',
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

    protected function getCommentOnColumnSQL()
    {
        return array(
            'COMMENT ON COLUMN foo.bar IS \'comment\'',
            'COMMENT ON COLUMN "Foo"."BAR" IS \'comment\'',
            'COMMENT ON COLUMN "select"."from" IS \'comment\'',
        );
    }

    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id INTEGER NOT NULL, test VARCHAR(255) DEFAULT NULL, CONSTRAINT test_PK PRIMARY KEY (id))';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return array(
            'CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL)',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)',
        );
    }

    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL()
    {
        
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableChangeColumnLengthSQL()
    {
        return array(
            'ALTER TABLE mytable ALTER COLUMN unquoted1 TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER COLUMN unquoted2 TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER COLUMN unquoted3 TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER COLUMN "create" TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER COLUMN "table" TYPE VARCHAR(255)',
            'ALTER TABLE mytable ALTER COLUMN "select" TYPE VARCHAR(255)',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableRenameColumnSQL()
    {
        return array(
            'ALTER TABLE mytable ALTER COLUMN unquoted1 TO unquoted',
            'ALTER TABLE mytable ALTER COLUMN unquoted2 TO "where"',
            'ALTER TABLE mytable ALTER COLUMN unquoted3 TO "foo"',
            'ALTER TABLE mytable ALTER COLUMN "create" TO reserved_keyword',
            'ALTER TABLE mytable ALTER COLUMN "table" TO "from"',
            'ALTER TABLE mytable ALTER COLUMN "select" TO "bar"',
            'ALTER TABLE mytable ALTER COLUMN quoted1 TO quoted',
            'ALTER TABLE mytable ALTER COLUMN quoted2 TO "and"',
            'ALTER TABLE mytable ALTER COLUMN quoted3 TO "baz"',
        );
    }

    protected function getQuotesTableIdentifiersInAlterTableSQL()
    {
        
    }

    public function getAlterTableRenameColumnSQL()
    {
        return array(
            'ALTER TABLE foo ALTER COLUMN bar TO baz',
        );
    }

    public function getGenerateAlterTableSql()
    {
        
    }

    public function getGenerateIndexSql()
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateForeignKeySql()
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
    }

    public function getGenerateUniqueIndexSql()
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    protected function getBitAndComparisonExpressionSql($value1, $value2)
    {
        return 'BIN_AND (' . $value1 . ', ' . $value2 . ')';
    }

    protected function getBitOrComparisonExpressionSql($value1, $value2)
    {
        return 'BIN_OR (' . $value1 . ', ' . $value2 . ')';
    }

    protected function getQuotedColumnInPrimaryKeySQL()
    {
        return array('CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, CONSTRAINT "quoted_PK" PRIMARY KEY ("create"))');
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
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, "bar" VARCHAR(255) NOT NULL)',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES "foreign" ("create", bar, "foo-bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES foo ("create", bar, "foo-bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar") REFERENCES "foo-bar" ("create", bar, "foo-bar")',
        );
    }

    protected function getBinaryMaxLength()
    {
        return 8190;
    }

}
