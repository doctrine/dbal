<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

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
        $table->addColumn('c1', 'integer');
        $table->addColumn('c2', 'integer');

        $sm = $this->connection->createSchemaManager();
        $sm->dropAndCreateTable($table);

        $table->getColumn('c1')
            ->setType(Type::getType(Types::STRING))
            ->setLength(16);

        $diff = $sm->createComparator()
            ->diffTable($sm->listTableDetails('test_alter'), $table);

        self::assertNotNull($diff);
        $sm->alterTable($diff);

        $table = $sm->listTableDetails('test_alter');
        self::assertEquals(['c1', 'c2'], array_keys($table->getColumns()));
    }
}
