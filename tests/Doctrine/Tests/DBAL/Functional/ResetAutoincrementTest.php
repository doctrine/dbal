<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

class ResetAutoincrementTest extends DbalFunctionalTestCase
{
    public function testAutoincrementResetsOnTruncate()
    {
        $table = new Table('autoincremented_table');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('test_int', 'integer');
        $table->setPrimaryKey(['id']);

        $sm = $this->connection->getSchemaManager();
        $sm->createTable($table);

        $this->connection->insert('autoincremented_table', ['test_int' => 1]);
        $this->connection->insert('autoincremented_table', ['test_int' => 2]);
        $this->connection->insert('autoincremented_table', ['test_int' => 3]);

        $lastId = $this->getIdByTestInt(3);

        $this->assertEquals(3, $lastId);

        $this->connection->exec($this->connection->getDatabasePlatform()->getTruncateTableSQL('autoincremented_table'));

        $this->connection->insert('autoincremented_table', ['test_int' => 4]);
        $lastId = $this->getIdByTestInt(4);
        $this->assertEquals(1, $lastId);
    }

    protected function getIdByTestInt(int $whereTestInt)
    {
        return $this->connection->fetchColumn('SELECT id FROM autoincremented_table WHERE test_int = ?', [$whereTestInt]);
    }
}
