<?php

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function array_keys;

class AlterColumnTest extends FunctionalTestCase
{
    public function testColumnPositionRetainedAfterAltering(): void
    {
        $table = new Table('test_alter');
        $table->addColumn('c1', Types::INTEGER);
        $table->addColumn('c2', Types::INTEGER);

        $this->dropAndCreateTable($table);

        $table->getColumn('c1')
            ->setType(Type::getType(Types::STRING));

        $sm         = $this->connection->createSchemaManager();
        $comparator = new Comparator();
        $diff       = $comparator->diffTable($sm->introspectTable('test_alter'), $table);

        self::assertNotFalse($diff);
        $sm->alterTable($diff);

        $table = $sm->introspectTable('test_alter');
        self::assertSame(['c1', 'c2'], array_keys($table->getColumns()));
    }
}
