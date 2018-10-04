<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

final class DBAL2807Test extends DbalFunctionalTestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        if (!in_array($this->getPlatform()->getName(), ['mysql']))
        {
            $this->markTestSkipped('Restricted to MySQL.');

            return;
        }
    }

    /**
     * Ensures that the primary key is created within the same "alter table" statement that an auto-increment column
     * is added to the table as part of the new primary key.
     *
     * Before the fix for this problem this resulted in a database error:
     * SQLSTATE[42000]: Syntax error or access violation: 1075 Incorrect table definition; there can be only one auto column and it must be defined as a key
     */
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
