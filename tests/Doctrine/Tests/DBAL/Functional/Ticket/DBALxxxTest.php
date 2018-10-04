<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

final class DBALxxxTest extends DbalFunctionalTestCase
{
    public function setUp()
    {
        parent::setUp();

        if (!in_array($this->getPlatform()->getName(), ['mysql']))
        {
            $this->markTestSkipped('Restricted to MySQL.');

            return;
        }
    }

    public function testAlterPrimaryKeyToAutoIncrementColumn()
    {
        $table = new Table('my_table');
        $table->addColumn('initial_id', 'integer');
        $table->setPrimaryKey(['initial_id']);

        $newTable = clone $table;
        $newTable->addColumn('new_id', 'integer', ['autoincrement' => true]);
        $newTable->dropPrimaryKey();
        $newTable->setPrimaryKey(['new_id']);

        $diff = (new Comparator())->diffTable($table, $newTable);

        $this->assertSame(
            ['ALTER TABLE my_table ADD new_id INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (new_id)',],
            $this->getPlatform()->getAlterTableSQL($diff)
        );
    }

    protected function getPlatform()
    {
        return $this->_conn->getDatabasePlatform();
    }
}
