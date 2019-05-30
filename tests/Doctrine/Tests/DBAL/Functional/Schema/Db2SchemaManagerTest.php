<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BooleanType;

class Db2SchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    /**
     * @group DBAL-939
     */
    public function testGetBooleanColumn() : void
    {
        $table = new Table('boolean_column_test');
        $table->addColumn('bool', 'boolean');
        $table->addColumn('bool_commented', 'boolean', ['comment' => "That's a comment"]);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns('boolean_column_test');

        self::assertInstanceOf(BooleanType::class, $columns['bool']->getType());
        self::assertInstanceOf(BooleanType::class, $columns['bool_commented']->getType());

        self::assertNull($columns['bool']->getComment());
        self::assertSame("That's a comment", $columns['bool_commented']->getComment());
    }

    public function testListTableWithBinary() : void
    {
        self::markTestSkipped('Binary data type is currently not supported on DB2 LUW');
    }
}
