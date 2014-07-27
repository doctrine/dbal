<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

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
        if ($this->_conn) {
            try {
                $tempTable = $this->_conn->getDatabasePlatform()->getTemporaryTableName("my_temporary");
                $this->_conn->exec($this->_conn->getDatabasePlatform()->getDropTemporaryTableSQL($tempTable));
            } catch(\Exception $e) { }
        }
    }

    /**
     * @group DDC-1337
     * @return void
     */
    public function testDropTemporaryTableNotAutoCommitTransaction()
    {
        if ($this->_conn->getDatabasePlatform()->getName() == 'sqlanywhere' ||
            $this->_conn->getDatabasePlatform()->getName() == 'oracle') {
            $this->markTestSkipped("Test does not work on Oracle and SQL Anywhere.");
        }

        $platform = $this->_conn->getDatabasePlatform();
        $columnDefinitions = array("id" => array("type" => Type::getType("integer"), "notnull" => true));
        $tempTable = $platform->getTemporaryTableName("my_temporary");

        $createTempTableSQL = $platform->getCreateTemporaryTableSnippetSQL() . ' ' . $tempTable . ' ('
                . $platform->getColumnDeclarationListSQL($columnDefinitions) . ')';
        $this->_conn->executeUpdate($createTempTableSQL);

        $table = new Table("nontemporary");
        $table->addColumn("id", "integer");
        $table->setPrimaryKey(array('id'));

        foreach ($platform->getCreateTableSQL($table) as $sql) {
            $this->_conn->executeQuery($sql);
        }

        $this->_conn->beginTransaction();
        $this->_conn->insert("nontemporary", array("id" => 1));
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
        if ($this->_conn->getDatabasePlatform()->getName() == 'sqlanywhere' ||
            $this->_conn->getDatabasePlatform()->getName() == 'oracle') {
            $this->markTestSkipped("Test does not work on Oracle and SQL Anywhere.");
        }

        $platform = $this->_conn->getDatabasePlatform();
        $columnDefinitions = array("id" => array("type" => Type::getType("integer"), "notnull" => true));
        $tempTable = $platform->getTemporaryTableName("my_temporary");

        $createTempTableSQL = $platform->getCreateTemporaryTableSnippetSQL() . ' ' . $tempTable . ' ('
                . $platform->getColumnDeclarationListSQL($columnDefinitions) . ')';

        $table = new Table("nontemporary");
        $table->addColumn("id", "integer");
        $table->setPrimaryKey(array('id'));

        foreach ($platform->getCreateTableSQL($table) as $sql) {
            $this->_conn->executeQuery($sql);
        }

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