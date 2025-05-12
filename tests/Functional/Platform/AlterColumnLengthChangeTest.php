<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class AlterColumnLengthChangeTest extends FunctionalTestCase
{
    public function testColumnLengthIsChanged(): void
    {
        $table = new Table('test_alter_length', [
            Column::editor()
                ->setUnquotedName('c1')
                ->setTypeName(Types::STRING)
                ->setLength(50)
                ->create(),
        ]);

        $this->dropAndCreateTable($table);

        $sm      = $this->connection->createSchemaManager();
        $table   = $sm->introspectTable('test_alter_length');
        $columns = $table->getColumns();
        self::assertCount(1, $columns);
        self::assertSame(50, $columns[0]->getLength());

        $table->getColumn('c1')->setLength(100);

        $diff = $sm->createComparator()
            ->compareTables($sm->introspectTable('test_alter_length'), $table);

        $sm->alterTable($diff);

        $table   = $sm->introspectTable('test_alter_length');
        $columns = $table->getColumns();

        self::assertCount(1, $columns);
        self::assertSame(100, $columns[0]->getLength());
    }
}
