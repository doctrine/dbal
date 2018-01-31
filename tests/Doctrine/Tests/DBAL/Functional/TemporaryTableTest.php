<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

class TemporaryTableTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();
        try {
            $this->conn->exec($this->conn->getDatabasePlatform()->getDropTableSQL("nontemporary"));
        } catch(\Exception $e) {

        }
    }

    protected function tearDown()
    {
        if ($this->conn) {
            try {
                $tempTable = $this->conn->getDatabasePlatform()->getTemporaryTableName("my_temporary");
                $this->conn->exec($this->conn->getDatabasePlatform()->getDropTemporaryTableSQL($tempTable));
            } catch(\Exception $e) { }
        }

        parent::tearDown();
    }

    /**
     * @group DDC-1337
     * @return void
     */
    public function testDropTemporaryTableNotAutoCommitTransaction()
    {
        if ($this->conn->getDatabasePlatform()->getName() == 'sqlanywhere' ||
            $this->conn->getDatabasePlatform()->getName() == 'oracle') {
            $this->markTestSkipped("Test does not work on Oracle and SQL Anywhere.");
        }

        $platform = $this->conn->getDatabasePlatform();
        $columnDefinitions = array("id" => array("type" => Type::getType("integer"), "notnull" => true));
        $tempTable = $platform->getTemporaryTableName("my_temporary");

        $createTempTableSQL = $platform->getCreateTemporaryTableSnippetSQL() . ' ' . $tempTable . ' ('
                . $platform->getColumnDeclarationListSQL($columnDefinitions) . ')';
        $this->conn->executeUpdate($createTempTableSQL);

        $table = new Table("nontemporary");
        $table->addColumn("id", "integer");
        $table->setPrimaryKey(array('id'));

        $this->conn->getSchemaManager()->createTable($table);

        $this->conn->beginTransaction();
        $this->conn->insert("nontemporary", array("id" => 1));
        $this->conn->exec($platform->getDropTemporaryTableSQL($tempTable));
        $this->conn->insert("nontemporary", array("id" => 2));

        $this->conn->rollBack();

        $rows = $this->conn->fetchAll('SELECT * FROM nontemporary');
        self::assertEquals(array(), $rows, "In an event of an error this result has one row, because of an implicit commit.");
    }

    /**
     * @group DDC-1337
     * @return void
     */
    public function testCreateTemporaryTableNotAutoCommitTransaction()
    {
        if ($this->conn->getDatabasePlatform()->getName() == 'sqlanywhere' ||
            $this->conn->getDatabasePlatform()->getName() == 'oracle') {
            $this->markTestSkipped("Test does not work on Oracle and SQL Anywhere.");
        }

        $platform = $this->conn->getDatabasePlatform();
        $columnDefinitions = array("id" => array("type" => Type::getType("integer"), "notnull" => true));
        $tempTable = $platform->getTemporaryTableName("my_temporary");

        $createTempTableSQL = $platform->getCreateTemporaryTableSnippetSQL() . ' ' . $tempTable . ' ('
                . $platform->getColumnDeclarationListSQL($columnDefinitions) . ')';

        $table = new Table("nontemporary");
        $table->addColumn("id", "integer");
        $table->setPrimaryKey(array('id'));

        $this->conn->getSchemaManager()->createTable($table);

        $this->conn->beginTransaction();
        $this->conn->insert("nontemporary", array("id" => 1));

        $this->conn->exec($createTempTableSQL);
        $this->conn->insert("nontemporary", array("id" => 2));

        $this->conn->rollBack();

        try {
            $this->conn->exec($platform->getDropTemporaryTableSQL($tempTable));
        } catch(\Exception $e) {

        }

        $rows = $this->conn->fetchAll('SELECT * FROM nontemporary');
        self::assertEquals(array(), $rows, "In an event of an error this result has one row, because of an implicit commit.");
    }
}
