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

        self::assertInstanceOf('Doctrine\DBAL\Types\BooleanType', $columns['bool']->getType());
        self::assertInstanceOf('Doctrine\DBAL\Types\BooleanType', $columns['bool_commented']->getType());

        self::assertNull($columns['bool']->getComment());
        self::assertSame("That's a comment", $columns['bool_commented']->getComment());
    }

    public function testListTableWithBinary()
    {
        self::markTestSkipped('Binary data type is currently not supported on DB2 LUW');
    }
}
