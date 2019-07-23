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

<<<<<<< HEAD
        $this->assertInstanceOf('Doctrine\DBAL\Types\BooleanType', $columns['bool']->getType());
        $this->assertInstanceOf('Doctrine\DBAL\Types\BooleanType', $columns['bool_commented']->getType());

        $this->assertNull($columns['bool']->getComment());
        $this->assertSame("That's a comment", $columns['bool_commented']->getComment());
=======
        self::assertInstanceOf('Doctrine\DBAL\Types\BooleanType', $columns['bool']->getType());
        self::assertInstanceOf('Doctrine\DBAL\Types\BooleanType', $columns['bool_commented']->getType());

        self::assertNull($columns['bool']->getComment());
        self::assertSame("That's a comment", $columns['bool_commented']->getComment());
>>>>>>> 7f80c8e1eb3f302166387e2015709aafd77ddd01
    }
}
