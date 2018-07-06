<?php

namespace Doctrine\Tests\DBAL\Functional;

use const CASE_LOWER;

class ResetAutoincrementTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function testAutoincrementResetsOnTruncate()
    {
        $table = new \Doctrine\DBAL\Schema\Table('autoincremented_table');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('test_int', 'integer');
        $table->setPrimaryKey(['id']);

        /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
        $sm = $this->_conn->getSchemaManager();
        $sm->createTable($table);

        $this->_conn->insert('autoincremented_table', ['test_int' => 1]);
        $this->_conn->insert('autoincremented_table', ['test_int' => 2]);
        $this->_conn->insert('autoincremented_table', ['test_int' => 3]);

        $lastId = $this->getIdByTestInt(3);

        $this->assertEquals(3, $lastId);

        $this->_conn->exec($this->_conn->getDatabasePlatform()->getTruncateTableSQL('autoincremented_table'));

        $this->_conn->insert('autoincremented_table', ['test_int' => 4]);
        $lastId = $this->getIdByTestInt(4);
        $this->assertEquals(1, $lastId);
    }

    protected function getIdByTestInt(int $whereTestInt)
    {
        $row = $this->_conn->fetchAssoc('SELECT id FROM autoincremented_table WHERE test_int = ?', [$whereTestInt]);
        $row = array_change_key_case($row, CASE_LOWER);

        return $row['id'];
    }
}
