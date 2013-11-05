<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\DB2iSeriesPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Index;


class DB2iSeriesPlatformTest extends AbstractPlatformTestCase
{
    public function createPlatform()
    {
        return new DB2iSeriesPlatform;
    }

    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id INTEGER GENERATED ALWAYS AS IDENTITY  (START WITH 1, INCREMENT BY 1) NOT NULL, test VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
    return array(
            'CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL)','CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)'
        );   
    }

    
    public function getGenerateAlterTableSql()
    {
        return array(
            "ALTER TABLE mytable ADD COLUMN quota INTEGER DEFAULT NULL DROP COLUMN foo ALTER COLUMN baz SET DATA TYPE VARCHAR(255) ALTER COLUMN bloo SET DATA TYPE SMALLINT","RENAME TABLE TO userlist"
        );
    }
    
    /*public function testGeneratesDDLSnippets()
    {
        //$this->assertEquals('SHOW DATABASES', $this->_platform->getListDatabasesSQL());
        $this->assertEquals('CREATE DATABASE foobar', $this->_platform->getCreateDatabaseSQL('foobar'));
        $this->assertEquals('DROP DATABASE foobar;', $this->_platform->getDropDatabaseSQL('foobar'));
        $this->assertEquals('DROP TABLE foobar', $this->_platform->getDropTableSQL('foobar'));
    }
    
    */


    public function testGeneratesDDLSnippets()
    {
        $this->assertEquals('CREATE DATABASE foobar', $this->_platform->getCreateDatabaseSQL('foobar'));
        $this->assertEquals('DROP DATABASE foobar;', $this->_platform->getDropDatabaseSQL('foobar'));
        $this->assertEquals('DROP TABLE foobar', $this->_platform->getDropTableSQL('foobar'));
    }

    public function testGeneratesTypeDeclarationForIntegers()
    {
        $this->assertEquals(
            'INTEGER',
            $this->_platform->getIntegerTypeDeclarationSQL(array())
        );
        $this->assertEquals(
            'INTEGER GENERATED ALWAYS AS IDENTITY  (START WITH 1, INCREMENT BY 1)',
            $this->_platform->getIntegerTypeDeclarationSQL(array('autoincrement' => true)
        ));
        $this->assertEquals(
            'INTEGER GENERATED ALWAYS AS IDENTITY  (START WITH 1, INCREMENT BY 1)',
            $this->_platform->getIntegerTypeDeclarationSQL(
                array('autoincrement' => true, 'primary' => true)
        ));
    }

    public function testGeneratesTypeDeclarationForStrings()
    {
        $this->assertEquals(
            'CHAR(10)',
            $this->_platform->getVarcharTypeDeclarationSQL(
                array('length' => 10, 'fixed' => true)
        ));
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

    public function testPrefersIdentityColumns()
    {
        $this->assertTrue($this->_platform->prefersIdentityColumns());
    }

    public function testSupportsIdentityColumns()
    {
        $this->assertTrue($this->_platform->supportsIdentityColumns());
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

    public function testUniquePrimaryKey()
    {
        $keyTable = new Table("foo");
        $keyTable->addColumn("bar", "integer");
        $keyTable->addColumn("baz", "string");
        $keyTable->setPrimaryKey(array("bar"));
        $keyTable->addUniqueIndex(array("baz"));

        $oldTable = new Table("foo");
        $oldTable->addColumn("bar", "integer");
        $oldTable->addColumn("baz", "string");

        $c = new \Doctrine\DBAL\Schema\Comparator;
        $diff = $c->diffTable($oldTable, $keyTable);

        $sql = $this->_platform->getAlterTableSQL($diff);

        $this->assertEquals(array(
            "ALTER TABLE foo ADD PRIMARY KEY (bar)",
            "CREATE UNIQUE INDEX UNIQ_8C73652178240498 ON foo (baz)",
        ), $sql);
    }
    
    public function testGetDateTimeTypeDeclarationSql()
    {
        $this->assertEquals("TIMESTAMP", $this->_platform->getDateTimeTypeDeclarationSQL(array('version' => false)));
        $this->assertEquals("TIMESTAMP WITH DEFAULT", $this->_platform->getDateTimeTypeDeclarationSQL(array('version' => true)));
        $this->assertEquals("TIMESTAMP", $this->_platform->getDateTimeTypeDeclarationSQL(array()));
    }


    public function getCreateTableColumnTypeCommentsSQL()
    {
    	return array("CREATE TABLE test (id INTEGER NOT NULL, \"data\" CLOB(1M) NOT NULL, PRIMARY KEY(id))");      
    }



    protected function getQuotedColumnInPrimaryKeySQL()
    {
	    return array(
            'CREATE TABLE "quoted" ("key" VARCHAR(255) NOT NULL, PRIMARY KEY("key"))'
        );       
    }

    protected function getQuotedColumnInIndexSQL()
    {
	    return array(
            'CREATE TABLE quoted (key VARCHAR(255) NOT NULL, INDEX IDX_22660D028A90ABA9 (key))'
        );      
    }    

    public function testClobTypeDeclarationSQL()
    {        
        $this->assertEquals('CLOB(1M)', $this->_platform->getClobTypeDeclarationSQL(array()));
    }
    
     public function testQuotedColumnInPrimaryKeyPropagation()
    {
        $table = new Table('`quoted`');
        $table->addColumn('`key`', 'string');
        $table->setPrimaryKey(array('key'));

        $sql = $this->_platform->getCreateTableSQL($table);
        $this->assertEquals($this->getQuotedColumnInPrimaryKeySQL(), $sql);
    }

}
