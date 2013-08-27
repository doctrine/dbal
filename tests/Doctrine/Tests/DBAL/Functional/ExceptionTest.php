<?php
namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\DBALException;

require_once __DIR__ . '/../../TestInit.php';

class ExceptionTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function testDuplicateKeyException()
    {
        /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
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

    public function testTableAlreadyExists()
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
}
 