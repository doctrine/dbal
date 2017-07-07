<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema\Table;

class Db2SchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    /**
     * @group DBAL-939
     */
    public function testGetBooleanColumn()
    {
        $table = new Table('boolean_column_test');
        $table->addColumn('bool', 'boolean');
        $table->addColumn('bool_commented', 'boolean', array('comment' => "That's a comment"));

        $this->_sm->createTable($table);

        $columns = $this->_sm->listTableColumns('boolean_column_test');

        $this->assertInstanceOf('Doctrine\DBAL\Types\BooleanType', $columns['bool']->getType());
        $this->assertInstanceOf('Doctrine\DBAL\Types\BooleanType', $columns['bool_commented']->getType());

        $this->assertNull($columns['bool']->getComment());
        $this->assertSame("That's a comment", $columns['bool_commented']->getComment());
    }
}
