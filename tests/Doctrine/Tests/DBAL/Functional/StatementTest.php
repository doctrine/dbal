<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Schema\Table;

class StatementTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function testStatementIsReusableAfterClosingCursor()
    {
        $sm = $this->_conn->getSchemaManager();
        $table = new Table('stmt_test_reusable');
        $table->addColumn('id', 'integer');
        $sm->createTable($table);
        $this->_conn->insert('stmt_test_reusable', array('id' => 1));
        $this->_conn->insert('stmt_test_reusable', array('id' => 2));

        $stmt = $this->_conn->prepare('SELECT id FROM stmt_test_reusable ORDER BY id');

        $stmt->execute();

        $id = $stmt->fetchColumn();
        $this->assertEquals(1, $id);

        $stmt->closeCursor();

        $stmt->execute();
        $id = $stmt->fetchColumn();
        $this->assertEquals(1, $id);
        $id = $stmt->fetchColumn();
        $this->assertEquals(2, $id);
    }

    public function testClosedCursorDoesNotContainResults()
    {
        $sm = $this->_conn->getSchemaManager();
        $table = new Table('stmt_test_no_results');
        $table->addColumn('id', 'integer');
        $sm->createTable($table);
        $this->_conn->insert('stmt_test_no_results', array('id' => 1));

        $stmt = $this->_conn->prepare('SELECT id FROM stmt_test_no_results');
        $stmt->execute();
        $stmt->closeCursor();

        try {
            $value = $stmt->fetchColumn();
        } catch (\Exception $e) {
            // some adapters trigger PHP error or throw adapter-specific exception in case of fetching
            // from a closed cursor, which still proves that it has been closed
            return;
        }

        $this->assertFalse($value);
    }
}
