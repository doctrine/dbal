<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

class AlterColumnTest extends FunctionalTestCase
{
    public function testColumnPositionRetainedAfterAltering(): void
    {
        $table = new Table('test_alter');
        $table->addColumn('c1', 'integer');
        $table->addColumn('c2', 'integer');

        $this->dropAndCreateTable($table);

        $table->getColumn('c1')
            ->setType(Type::getType(Types::STRING))
            ->setLength(16);

        $sm   = $this->connection->createSchemaManager();
        $diff = $sm->createComparator()
            ->diffTable($sm->introspectTable('test_alter'), $table);

        self::assertNotNull($diff);
        $sm->alterTable($diff);

        $table   = $sm->introspectTable('test_alter');
        $columns = $table->getColumns();

        self::assertCount(2, $columns);
        self::assertEqualsIgnoringCase('c1', $columns[0]->getName());
        self::assertEqualsIgnoringCase('c2', $columns[1]->getName());
    }
}
