<?php

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function array_keys;
use function array_values;
use function strtolower;

class RenameColumnTest extends FunctionalTestCase
{
    /** @dataProvider columnNameProvider */
    public function testColumnPositionRetainedAfterImplicitRenaming(string $columnName, string $newColumnName): void
    {
        $table = new Table('test_rename');
        $table->addColumn($columnName, Types::STRING);
        $table->addColumn('c2', Types::INTEGER);

        $this->dropAndCreateTable($table);

        $table->dropColumn($columnName)
            ->addColumn($newColumnName, Types::STRING);

        $sm         =  $this->connection->createSchemaManager();
        $comparator = new Comparator();
        $diff       = $comparator->diffTable($sm->introspectTable('test_rename'), $table);

        self::assertNotFalse($diff);
        $sm->alterTable($diff);

        $table = $sm->introspectTable('test_rename');
        self::assertSame([strtolower($newColumnName), 'c2'], array_keys($table->getColumns()));
        self::assertCount(1, $diff->getRenamedColumns());
    }

    /** @dataProvider columnNameProvider */
    public function testColumnPositionRetainedAfterExplicitRenaming(string $columnName, string $newColumnName): void
    {
        $table = new Table('test_rename');
        $table->addColumn($columnName, Types::INTEGER, ['length' => 16]);
        $table->addColumn('c2', Types::INTEGER);

        $this->dropAndCreateTable($table);

        // Force a different type to make sure it's not being caught implicitly
        $table->renameColumn($columnName, $newColumnName)->setType(Type::getType(Types::BIGINT))->setLength(32);

        $sm   =  $this->connection->createSchemaManager();
        $diff = $sm->createComparator()
            ->compareTables($sm->introspectTable('test_rename'), $table);

        $sm->alterTable($diff);

        $table   = $sm->introspectTable('test_rename');
        $columns = array_values($table->getColumns());

        self::assertCount(1, $diff->getChangedColumns());
        self::assertCount(2, $columns);
        self::assertEqualsIgnoringCase($newColumnName, $columns[0]->getName());
        self::assertEqualsIgnoringCase('c2', $columns[1]->getName());
    }

    /** @return iterable<array{string}> */
    public static function columnNameProvider(): iterable
    {
        yield ['c1', 'c1_x'];
        yield ['C1', 'c1_x'];
        yield ['importantColumn', 'veryImportantColumn'];
    }
}
