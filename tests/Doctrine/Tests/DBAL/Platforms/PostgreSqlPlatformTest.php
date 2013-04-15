<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Types\Type;

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
            "ALTER TABLE mytable ALTER bar SET  DEFAULT 'def'",
            'ALTER TABLE mytable ALTER bar SET NOT NULL',
            'ALTER TABLE mytable ALTER bloo TYPE BOOLEAN',
            "ALTER TABLE mytable ALTER bloo SET  DEFAULT 'false'",
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
    }

    public function testGeneratesSqlSnippets()
    {
        $this->assertEquals('SIMILAR TO', $this->_platform->getRegexpExpression(), 'Regular expression operator is not correct');
        $this->assertEquals('"', $this->_platform->getIdentifierQuoteCharacter(), 'Identifier quote character is not correct');
        $this->assertEquals('column1 || column2 || column3', $this->_platform->getConcatExpression('column1', 'column2', 'column3'), 'Concatenation expression is not correct');
        $this->assertEquals('SUBSTR(column, 5)', $this->_platform->getSubstringExpression('column', 5), 'Substring expression without length is not correct');
        $this->assertEquals('SUBSTR(column, 0, 5)', $this->_platform->getSubstringExpression('column', 0, 5), 'Substring expression with length is not correct');
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
            'CREATE TABLE "quoted" ("key" VARCHAR(255) NOT NULL, PRIMARY KEY("key"))',
        );
    }

    protected function getQuotedColumnInIndexSQL()
    {
        return array(
            'CREATE TABLE "quoted" ("key" VARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028A90ABA9 ON "quoted" ("key")',
        );
    }
    
    public function testGetListTableColumnsSQL() {
      $this->assertEquals($this->_platform->getListTableColumnsSQL('test_table'),
      "SELECT
                    a.attnum,
                    a.attname AS field,
                    t.typname AS type,
                    format_type(a.atttypid, a.atttypmod) AS complete_type,
                    (SELECT t1.typname FROM pg_catalog.pg_type t1 WHERE t1.oid = t.typbasetype) AS domain_type,
                    (SELECT format_type(t2.typbasetype, t2.typtypmod) FROM pg_catalog.pg_type t2
                     WHERE t2.typtype = 'd' AND t2.typname = format_type(a.atttypid, a.atttypmod)) AS domain_complete_type,
                    a.attnotnull AS isnotnull,
                    (SELECT 't'
                     FROM pg_index
                     WHERE c.oid = pg_index.indrelid
                        AND pg_index.indkey[0] = a.attnum
                        AND pg_index.indisprimary = 't'
                    ) AS pri,
                    (SELECT pg_attrdef.adsrc
                     FROM pg_attrdef
                     WHERE c.oid = pg_attrdef.adrelid
                        AND pg_attrdef.adnum=a.attnum
                    ) AS default,
                    (SELECT pg_description.description
                        FROM pg_description WHERE pg_description.objoid = c.oid AND a.attnum = pg_description.objsubid
                    ) AS comment
                    FROM pg_attribute a, pg_class c, pg_type t, pg_namespace n
                    WHERE n.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast') AND c.relname = 'test_table' AND n.nspname = ANY(string_to_array((select replace(setting,'\"\$user\"',user) from pg_catalog.pg_settings where name = 'search_path'),','))
                        AND a.attnum > 0
                        AND a.attrelid = c.oid
                        AND a.atttypid = t.oid
                        AND n.oid = c.relnamespace
                    ORDER BY a.attnum"
      );
    }
    
    public function testGetListTableForeignKeysSQL() {
      $this->assertEquals($this->_platform->getListTableForeignKeysSQL('test_table'),
        "SELECT r.conname, pg_catalog.pg_get_constraintdef(r.oid, true) as condef
                  FROM pg_catalog.pg_constraint r
                  WHERE r.conrelid =
                  (
                      SELECT c.oid
                      FROM pg_catalog.pg_class c, pg_catalog.pg_namespace n
                      WHERE n.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast') AND c.relname = 'test_table' AND n.nspname = ANY(string_to_array((select replace(setting,'\"\$user\"',user) from pg_catalog.pg_settings where name = 'search_path'),',')) AND n.oid = c.relnamespace
                  )
                  AND r.contype = 'f'"
      );
    }
    
    public function testGetListTableIndexesSQL() {
      $this->assertEquals($this->_platform->getListTableIndexesSQL('test_table'), "SELECT relname, pg_index.indisunique, pg_index.indisprimary,
                       pg_index.indkey, pg_index.indrelid
                 FROM pg_class, pg_index
                 WHERE oid IN (
                    SELECT indexrelid
                    FROM pg_index si, pg_class sc, pg_namespace sn
                    WHERE sn.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast') AND sc.relname = 'test_table' AND sn.nspname = ANY(string_to_array((select replace(setting,'\"\$user\"',user) from pg_catalog.pg_settings where name = 'search_path'),',')) AND sc.oid=si.indrelid AND sc.relnamespace = sn.oid
                 ) AND pg_index.indexrelid = oid"
      );
    }
}
