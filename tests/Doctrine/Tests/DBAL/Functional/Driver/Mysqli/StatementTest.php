<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\Mysqli;

use Doctrine\DBAL\Schema\Table;

class StatementTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function setUp()
    {
        if (!extension_loaded('mysqli')) {
            $this->markTestSkipped('mysqli is not installed.');
        }

        parent::setUp();

        if (!($this->_conn->getDriver() instanceof \Doctrine\DBAL\Driver\Mysqli\Driver)) {
            $this->markTestSkipped('MySQLi only test.');
        }
    }

    public function testRowValuesReboundAfterFreeingResult()
    {
        $table = new Table('rebind_row_values');
        $table->addColumn('id', 'integer');
        $this->_conn->getSchemaManager()->createTable($table);
        $this->_conn->insert('rebind_row_values', array('id' => 1));
        $this->_conn->insert('rebind_row_values', array('id' => 2));

        $stmt = $this->_conn->prepare('SELECT id FROM rebind_row_values WHERE id = ?');

        $stmt->execute(array(1));
        $id = $stmt->fetchColumn();
        $this->assertEquals(1, $id);

        $stmt->closeCursor();

        $stmt->execute(array(2));
        $id = $stmt->fetchColumn();
        $this->assertEquals(2, $id);
    }
}
