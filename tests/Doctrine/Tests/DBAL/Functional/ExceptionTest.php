<?php
namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\DBALException;

class ExceptionTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function testDuplicateKeyException()
    {
        $table = new \Doctrine\DBAL\Schema\Table("duplicatekey_table");
        $table->addColumn('id', 'integer', array());
        $table->setPrimaryKey(array('id'));

        foreach ($this->_conn->getDatabasePlatform()->getCreateTableSQL($table) AS $sql) {
            $this->_conn->executeQuery($sql);
        }

        $this->_conn->insert("duplicatekey_table", array('id' => 1));

        $this->setExpectedException('\Doctrine\DBAL\DBALException', null, DBALException::ERROR_DUPLICATE_KEY);
        $this->_conn->insert("duplicatekey_table", array('id' => 1));
    }

    public function testUnknownTableException()
    {
        $sql = "SELECT * FROM unknown_table";

        $this->setExpectedException('\Doctrine\DBAL\DBALException', null, DBALException::ERROR_UNKNOWN_TABLE);
        $this->_conn->executeQuery($sql);
    }

    public function testTableAlreadyExistsException()
    {
        $table = new \Doctrine\DBAL\Schema\Table("alreadyexist_table");
        $table->addColumn('id', 'integer', array());
        $table->setPrimaryKey(array('id'));

        $this->setExpectedException('\Doctrine\DBAL\DBALException', null, DBALException::ERROR_TABLE_ALREADY_EXISTS);
        foreach ($this->_conn->getDatabasePlatform()->getCreateTableSQL($table) AS $sql) {
            $this->_conn->executeQuery($sql);
        }
        foreach ($this->_conn->getDatabasePlatform()->getCreateTableSQL($table) AS $sql) {
            $this->_conn->executeQuery($sql);
        }
    }

    public function testForeignKeyContraintException()
    {
        if ( ! $this->_conn->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped("Only fails on platforms with foreign key constraints.");
        }

        $schema = new \Doctrine\DBAL\Schema\Schema();
        
        $table = $schema->createTable("constraint_error_table");
        $table->addColumn('id', 'integer', array());
        $table->setPrimaryKey(array('id'));

        $owningTable = $schema->createTable("owning_table");
        $owningTable->addColumn('id', 'integer', array());
        $owningTable->addColumn('constraint_id', 'integer', array());
        $owningTable->setPrimaryKey(array('id'));
        $owningTable->addForeignKeyConstraint($table, array('constraint_id'), array('id'));

        foreach ($schema->toSql($this->_conn->getDatabasePlatform()) AS $sql) {
            $this->_conn->executeQuery($sql);
        }

        $this->_conn->insert("constraint_error_table", array('id' => 1));
        $this->_conn->insert("owning_table", array('id' => 1, 'constraint_id' => 1));

        $this->setExpectedException('\Doctrine\DBAL\DBALException', null, DBALException::ERROR_FOREIGN_KEY_CONSTRAINT);
        $this->_conn->delete('constraint_error_table', array('id' => 1));
    }
    
    public function testNotNullException()
    {
        $schema = new \Doctrine\DBAL\Schema\Schema();

        $table = $schema->createTable("notnull_table");
        $table->addColumn('id', 'integer', array());
        $table->addColumn('value', 'integer', array('notnull' => true));
        $table->setPrimaryKey(array('id'));

        foreach ($schema->toSql($this->_conn->getDatabasePlatform()) AS $sql) {
            $this->_conn->executeQuery($sql);
        }

        $this->setExpectedException('\Doctrine\DBAL\DBALException', null, DBALException::ERROR_NOT_NULL);
        $this->_conn->insert("notnull_table", array('id' => 1));
    }

    public function testBadFieldNameException()
    {
        $schema = new \Doctrine\DBAL\Schema\Schema();

        $table = $schema->createTable("bad_fieldname_table");
        $table->addColumn('id', 'integer', array());

        foreach ($schema->toSql($this->_conn->getDatabasePlatform()) AS $sql) {
            $this->_conn->executeQuery($sql);
        }

        $this->setExpectedException('\Doctrine\DBAL\DBALException', null, DBALException::ERROR_BAD_FIELD_NAME);
        $this->_conn->insert("bad_fieldname_table", array('name' => 5));
    }

    public function testNonUniqueFieldNameException()
    {
        $schema = new \Doctrine\DBAL\Schema\Schema();

        $table = $schema->createTable("ambiguous_list_table");
        $table->addColumn('id', 'integer');

        $table2 = $schema->createTable("ambiguous_list_table_2");
        $table2->addColumn('id', 'integer');

        foreach ($schema->toSql($this->_conn->getDatabasePlatform()) AS $sql) {
            $this->_conn->executeQuery($sql);
        }

        $sql = 'SELECT id FROM ambiguous_list_table, ambiguous_list_table_2';
        $this->setExpectedException('\Doctrine\DBAL\DBALException', null, DBALException::ERROR_NON_UNIQUE_FIELD_NAME);
        $this->_conn->executeQuery($sql);
    }

    public function testNotUniqueException()
    {
        $schema = new \Doctrine\DBAL\Schema\Schema();

        $table = $schema->createTable("unique_field_table");
        $table->addColumn('id', 'integer');
        $table->addUniqueIndex(array('id'));

        foreach ($schema->toSql($this->_conn->getDatabasePlatform()) AS $sql) {
            $this->_conn->executeQuery($sql);
        }

        $this->_conn->insert("unique_field_table", array('id' => 5));
        $this->setExpectedException('\Doctrine\DBAL\DBALException', null, DBALException::ERROR_NOT_UNIQUE);
        $this->_conn->insert("unique_field_table", array('id' => 5));
    }

    public function testSyntaxErrorException()
    {
        $table = new \Doctrine\DBAL\Schema\Table("syntax_error_table");
        $table->addColumn('id', 'integer', array());
        $table->setPrimaryKey(array('id'));

        foreach ($this->_conn->getDatabasePlatform()->getCreateTableSQL($table) AS $sql) {
            $this->_conn->executeQuery($sql);
        }

        $sql = 'SELECT id FRO syntax_error_table';
        $this->setExpectedException('\Doctrine\DBAL\DBALException', null, DBALException::ERROR_SYNTAX);
        $this->_conn->executeQuery($sql);
    }

    protected function onNotSuccessfulTest(\Exception $e)
    {
        parent::onNotSuccessfulTest($e);
        if ("PHPUnit_Framework_SkippedTestError" == get_class($e)) {
            return;
        }
        var_dump($e);
    }
}
 