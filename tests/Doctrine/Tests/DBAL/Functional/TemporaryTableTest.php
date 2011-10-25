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
            $this->_conn->exec($this->_conn->getDatabasePlatform()->getDropTableSQL("non_temporary"));
        } catch(\Exception $e) {

        }
    }

    /**
     * @group DDC-1337
     * @return void
     */
    public function testDropTemporaryTableNotAbortsTransaction()
    {
        $platform = $this->_conn->getDatabasePlatform();
        $columnDefinitions = array("id" => array("type" => Type::getType("integer"), "notnull" => true));
        $tempTable = $platform->getTemporaryTableName("temporary");

        $tempTableSQL = $platform->getCreateTemporaryTableSnippetSQL() . ' ' . $tempTable . ' ('
                . $platform->getColumnDeclarationListSQL($columnDefinitions) . ')';

        $table = new Table("non_temporary");
        $table->addColumn("id", "integer");

        $this->_conn->getSchemaManager()->createTable($table);
        $this->_conn->beginTransaction();
        $this->_conn->insert("non_temporary", array("id" => 1));

        $this->_conn->exec($tempTableSQL);
        $this->_conn->exec($platform->getDropTemporaryTableSQL($tempTable));

        $this->_conn->insert("non_temporary", array("id" => 2));

        $this->_conn->rollback();

        $rows = $this->_conn->fetchAll('SELECT * FROM non_temporary');
        $this->assertEquals(array(), $rows);
    }

    /**
     * @group DDC-1337
     * @return void
     */
    public function testCreateTemporaryTableNotAbortsTransaction()
    {
        $platform = $this->_conn->getDatabasePlatform();
        $columnDefinitions = array("id" => array("type" => Type::getType("integer"), "notnull" => true));
        $tempTable = $platform->getTemporaryTableName("temporary");

        $tempTableSQL = $platform->getCreateTemporaryTableSnippetSQL() . ' ' . $tempTable . ' ('
                . $platform->getColumnDeclarationListSQL($columnDefinitions) . ')';

        $table = new Table("non_temporary");
        $table->addColumn("id", "integer");

        $this->_conn->getSchemaManager()->createTable($table);
        $this->_conn->beginTransaction();
        $this->_conn->insert("non_temporary", array("id" => 1));

        $this->_conn->exec($tempTableSQL);
        $this->_conn->insert("non_temporary", array("id" => 2));

        $this->_conn->rollback();

        try {
            $this->_conn->exec($platform->getDropTemporaryTableSQL($tempTable));
        } catch(\Exception $e) {
            
        }

        $rows = $this->_conn->fetchAll('SELECT * FROM non_temporary');
        $this->assertEquals(array(), $rows);
    }
}