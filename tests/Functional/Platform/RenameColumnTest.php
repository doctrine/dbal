<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

class RenameColumnTest extends FunctionalTestCase
{
    /** @dataProvider columnNameProvider */
    public function testColumnPositionRetainedAfterImplicitRenaming(string $columnName, string $newColumnName): void
    {
        $table = new Table('test_rename');
        $table->addColumn($columnName, Types::STRING, ['length' => 16]);
        $table->addColumn('c2', Types::INTEGER);

        $this->dropAndCreateTable($table);

        $table->dropColumn($columnName)
            ->addColumn($newColumnName, Types::STRING, ['length' => 16]);

        $sm   =  $this->connection->createSchemaManager();
        $diff = $sm->createComparator()
            ->compareTables($sm->introspectTable('test_rename'), $table);

        $sm->alterTable($diff);

        $table   = $sm->introspectTable('test_rename');
        $columns = $table->getColumns();

        self::assertCount(2, $columns);
        self::assertEqualsIgnoringCase($newColumnName, $columns[0]->getName());
        self::assertEqualsIgnoringCase('c2', $columns[1]->getName());
        self::assertCount(1, self::getRenamedColumns($diff));
        self::assertCount(1, $diff->getRenamedColumns());
    }

    /** @return array<string,Column> */
    public static function getRenamedColumns(TableDiff $tableDiff): array
    {
        $renamed = [];
        foreach ($tableDiff->getChangedColumns() as $diff) {
            if (! $diff->hasNameChanged()) {
                continue;
            }

            $oldColumnName           = $diff->getOldColumn()->getName();
            $renamed[$oldColumnName] = $diff->getNewColumn();
        }

        return $renamed;
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

        $sm   = $this->connection->createSchemaManager();
        $diff = $sm->createComparator()
            ->compareTables($sm->introspectTable('test_rename'), $table);

        $sm->alterTable($diff);

        $table   = $sm->introspectTable('test_rename');
        $columns = $table->getColumns();

        self::assertCount(1, $diff->getChangedColumns());
        self::assertCount(1, $diff->getRenamedColumns());
        self::assertCount(1, $diff->getModifiedColumns());
        self::assertCount(2, $columns);
        self::assertEqualsIgnoringCase($newColumnName, $columns[0]->getName());
        self::assertEqualsIgnoringCase('c2', $columns[1]->getName());
    }

    /** @return iterable<array{string,string}> */
    public static function columnNameProvider(): iterable
    {
        yield ['c1', 'c1_x'];
        yield ['C1', 'c1_x'];
        yield ['importantColumn', 'veryImportantColumn'];
    }
}
