<?php

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function array_keys;
use function strtolower;

class RenameColumnTest extends FunctionalTestCase
{
    /** @dataProvider columnNameProvider */
    public function testColumnPositionRetainedAfterRenaming(string $columnName, string $newColumnName): void
    {
        $table = new Table('test_rename');
        $table->addColumn($columnName, 'string');
        $table->addColumn('c2', 'integer');

        $this->dropAndCreateTable($table);

        $table->dropColumn($columnName)
            ->addColumn($newColumnName, 'string');

        $sm         =  $this->connection->createSchemaManager();
        $comparator = new Comparator();
        $diff       = $comparator->diffTable($sm->introspectTable('test_rename'), $table);

        self::assertNotFalse($diff);
        $sm->alterTable($diff);

        $table = $sm->introspectTable('test_rename');
        self::assertSame([strtolower($newColumnName), 'c2'], array_keys($table->getColumns()));
    }

    /** @return iterable<array{string}> */
    public static function columnNameProvider(): iterable
    {
        yield ['c1', 'c1_x'];
        yield ['C1', 'c1_x'];
        yield ['importantColumn', 'veryImportantColumn'];
    }
}
