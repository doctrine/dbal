<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Schema\Table;

class StatementTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function testReuseStatementAfterClosingCursor()
    {
        $table = new Table('close_cursor');
        $table->addColumn('id', 'integer');
        $this->_conn->getSchemaManager()->createTable($table);
        $this->_conn->insert('close_cursor', array('id' => 1));
        $this->_conn->insert('close_cursor', array('id' => 2));

        $stmt = $this->_conn->prepare('SELECT id FROM close_cursor WHERE id = ?');

        $stmt->execute(array(1));
        $id = $stmt->fetchColumn();
        $this->assertEquals(1, $id);

        $stmt->closeCursor();

        $stmt->execute(array(2));
        $id = $stmt->fetchColumn();
        $this->assertEquals(2, $id);
    }
}
