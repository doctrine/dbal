<?php

namespace Doctrine\Tests\DBAL\Functional;

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

        $this->assertEquals(3, $this->_conn->lastInsertId('autoincremented_table_id_seq'));

        $this->_conn->exec($this->_conn->getDatabasePlatform()->getTruncateTableSQL('autoincremented_table'));

        $this->_conn->insert('autoincremented_table', ['test_int' => 4]);
        $this->assertEquals(1, $this->_conn->lastInsertId('autoincremented_table_id_seq'));
    }
}
