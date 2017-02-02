<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Schema\Table;

class StatementTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function testReuseStatementAfterClosingCursor()
    {
        $this->createCloseCursorTable();

        $stmt = $this->_conn->prepare('SELECT id FROM close_cursor WHERE id = ?');

        $stmt->execute(array(1));
        $id = $stmt->fetchColumn();
        $this->assertEquals(1, $id);

        $stmt->closeCursor();

        $stmt->execute(array(2));
        $id = $stmt->fetchColumn();
        $this->assertEquals(2, $id);
    }

    public function testPartiallyFetchedStatementIsReusable()
    {
        $this->createCloseCursorTable();

        $stmt = $this->_conn->prepare('SELECT id FROM close_cursor ORDER BY id');

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
        $this->createCloseCursorTable();

        $stmt = $this->_conn->prepare('SELECT id FROM close_cursor');

        $stmt->execute();

        $id = $stmt->fetchColumn();
        $this->assertNotEmpty($id);

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

    private function createCloseCursorTable()
    {
        $sm = $this->_conn->getSchemaManager();

        if (!$sm->tablesExist('close_cursor')) {
            $table = new Table('close_cursor');
            $table->addColumn('id', 'integer');
            $sm->createTable($table);
            $this->_conn->insert('close_cursor', array('id' => 1));
            $this->_conn->insert('close_cursor', array('id' => 2));
        }
    }
}
