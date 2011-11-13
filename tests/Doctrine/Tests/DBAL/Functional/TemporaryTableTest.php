<?php

namespace Doctrine\Tests\DBAL\Functional;

use \Doctrine\DBAL\Schema\Table;
use \Doctrine\DBAL\Schema\Column;
use \Doctrine\DBAL\Types\Type;

class TemporaryTableTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function setUp()
    {
        parent::setUp();
        try {
            $this->_conn->exec($this->_conn->getDatabasePlatform()->getDropTableSQL("nontemporary"));
        } catch(\Exception $e) {

        }
    }

    public function tearDown()
    {
        try {
            $tempTable = $this->_conn->getDatabasePlatform()->getTemporaryTableName("temporary");
            $this->_conn->exec($this->_conn->getDatabasePlatform()->getDropTemporaryTableSQL($tempTable));
        } catch(\Exception $e) {

        }
    }

    /**
     * @group DDC-1337
     * @return void
     */
    public function testDropTemporaryTableNotAutoCommitTransaction()
    {
        $platform = $this->_conn->getDatabasePlatform();
        $columnDefinitions = array("id" => array("type" => Type::getType("integer"), "notnull" => true));
        $tempTable = $platform->getTemporaryTableName("temporary");

        $createTempTableSQL = $platform->getCreateTemporaryTableSnippetSQL() . ' ' . $tempTable . ' ('
                . $platform->getColumnDeclarationListSQL($columnDefinitions) . ')';
        $this->_conn->exec($createTempTableSQL);

        $table = new Table("nontemporary");
        $table->addColumn("id", "integer");
        $table->setPrimaryKey(array('id'));

        $this->_conn->beginTransaction();
        $this->_conn->getSchemaManager()->createTable($table);
        $this->_conn->commit();

        $this->_conn->beginTransaction();
        try {
            $this->_conn->insert("nontemporary", array("id" => 1));
        } catch(Exception $e) {
            $this->fail("Error: " . $e->getMessage() . " --- Existing tables: " . implode(", ", $this->_conn->getSchemaManager()->listTableNames()));
        }

        $this->_conn->exec($platform->getDropTemporaryTableSQL($tempTable));

        $this->_conn->insert("nontemporary", array("id" => 2));

        $this->_conn->rollback();

        $rows = $this->_conn->fetchAll('SELECT * FROM nontemporary');
        $this->assertEquals(array(), $rows, "In an event of an error this result has one row, because of an implicit commit.");
    }

    /**
     * @group DDC-1337
     * @return void
     */
    public function testCreateTemporaryTableNotAutoCommitTransaction()
    {
        $platform = $this->_conn->getDatabasePlatform();
        $columnDefinitions = array("id" => array("type" => Type::getType("integer"), "notnull" => true));
        $tempTable = $platform->getTemporaryTableName("temporary");

        $createTempTableSQL = $platform->getCreateTemporaryTableSnippetSQL() . ' ' . $tempTable . ' ('
                . $platform->getColumnDeclarationListSQL($columnDefinitions) . ')';

        $table = new Table("nontemporary");
        $table->addColumn("id", "integer");
        $table->setPrimaryKey(array('id'));

        $this->_conn->getSchemaManager()->createTable($table);
        $this->_conn->beginTransaction();
        $this->_conn->insert("nontemporary", array("id" => 1));

        $this->_conn->exec($createTempTableSQL);
        $this->_conn->insert("nontemporary", array("id" => 2));

        $this->_conn->rollback();

        try {
            $this->_conn->exec($platform->getDropTemporaryTableSQL($tempTable));
        } catch(\Exception $e) {

        }

        $rows = $this->_conn->fetchAll('SELECT * FROM nontemporary');
        $this->assertEquals(array(), $rows, "In an event of an error this result has one row, because of an implicit commit.");
    }
}