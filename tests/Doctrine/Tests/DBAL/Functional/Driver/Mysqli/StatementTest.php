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

    public function testSubsequentStatementResultIsBuffered()
    {
        $table = new Table('stmt_table');
        $table->addColumn('id', 'integer');
        $this->_conn->getSchemaManager()->createTable($table);
        $this->_conn->insert('stmt_table', array('id' => 1));
        $this->_conn->insert('stmt_table', array('id' => 2));

        $stmt1 = $this->_conn->prepare('SELECT id FROM stmt_table');
        $stmt1->execute();
        $stmt1->fetch();
        $stmt1->execute();
        // fetching only one record out of two from a non-buffered result
        $stmt1->fetch();
        $stmt2 = $this->_conn->prepare('SELECT id FROM stmt_table WHERE id = ?');
        $stmt2->execute(array(1));
        $this->assertEquals(1, $stmt2->fetchColumn());
    }
}
