<?php

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\Types;

class Db2SchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof DB2Platform;
    }

    public function testGetBooleanColumn(): void
    {
        $table = new Table('boolean_column_test');
        $table->addColumn('bool', Types::BOOLEAN);
        $table->addColumn('bool_commented', Types::BOOLEAN, ['comment' => "That's a comment"]);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns('boolean_column_test');

        self::assertInstanceOf(BooleanType::class, $columns['bool']->getType());
        self::assertInstanceOf(BooleanType::class, $columns['bool_commented']->getType());

        self::assertNull($columns['bool']->getComment());
        self::assertSame("That's a comment", $columns['bool_commented']->getComment());
    }
}
