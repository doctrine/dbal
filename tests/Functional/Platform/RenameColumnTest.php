<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_map;

class RenameColumnTest extends FunctionalTestCase
{
    /**
     * @param non-empty-string $oldColumnName
     * @param non-empty-string $newColumnName
     */
    #[DataProvider('columnNameProvider')]
    public function testColumnPositionRetainedAfterImplicitRenaming(string $oldColumnName, string $newColumnName): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_rename')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName($oldColumnName)
                    ->setTypeName(Types::STRING)
                    ->setLength(16)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('c2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $table = $table->edit()
            ->dropColumnByUnquotedName($oldColumnName)
            ->addColumn(
                Column::editor()
                    ->setUnquotedName($newColumnName)
                    ->setTypeName(Types::STRING)
                    ->setLength(16)
                    ->create(),
            )
            ->create();

        $sm   =  $this->connection->createSchemaManager();
        $diff = $sm->createComparator()
            ->compareTables($sm->introspectTableByUnquotedName('test_rename'), $table);

        $sm->alterTable($diff);

        $table = $sm->introspectTableByUnquotedName('test_rename');

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted($newColumnName),
            UnqualifiedName::unquoted('c2'),
        ], array_map(
            static fn (Column $column): UnqualifiedName => $column->getObjectName(),
            $table->getColumns(),
        ));

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

            $oldColumnName = $diff->getOldColumn()
                ->getObjectName()
                ->toString();

            $renamed[$oldColumnName] = $diff->getNewColumn();
        }

        return $renamed;
    }

    /**
     * @param non-empty-string $oldColumnName
     * @param non-empty-string $newColumnName
     */
    #[DataProvider('columnNameProvider')]
    public function testColumnPositionRetainedAfterExplicitRenaming(string $oldColumnName, string $newColumnName): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_rename')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName($oldColumnName)
                    ->setTypeName(Types::INTEGER)
                    ->setLength(16)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('c2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        // Force a different type to make sure it's not being caught implicitly
        $table = $table->edit()
            ->renameColumnByUnquotedName($oldColumnName, $newColumnName)
            ->modifyColumnByUnquotedName($newColumnName, static function (ColumnEditor $editor): void {
                $editor
                    ->setTypeName(Types::BIGINT)
                    ->setLength(32);
            })
            ->create();

        $sm   = $this->connection->createSchemaManager();
        $diff = $sm->createComparator()
            ->compareTables($sm->introspectTableByUnquotedName('test_rename'), $table);

        $sm->alterTable($diff);

        $table = $sm->introspectTableByUnquotedName('test_rename');

        self::assertCount(1, $diff->getChangedColumns());
        self::assertCount(1, $diff->getRenamedColumns());
        self::assertCount(1, $diff->getModifiedColumns());

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted($newColumnName),
            UnqualifiedName::unquoted('c2'),
        ], array_map(
            static fn (Column $column): UnqualifiedName => $column->getObjectName(),
            $table->getColumns(),
        ));
    }

    /** @return iterable<array{non-empty-string,non-empty-string}> */
    public static function columnNameProvider(): iterable
    {
        yield ['c1', 'c1_x'];
        yield ['C1', 'c1_x'];
        yield ['importantColumn', 'very_important_column'];
    }
}
