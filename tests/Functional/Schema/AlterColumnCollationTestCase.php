<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

abstract class AlterColumnCollationTestCase extends FunctionalTestCase
{
    /**
     * @param ?non-empty-string            $oldCollation
     * @param callable(ColumnEditor): void $modifyColumn
     * @param ?non-empty-string            $expectedCollation
     */
    #[DataProvider('provideAlterColumnCollationCases')]
    public function testAlterColumnCollation(
        ?string $oldCollation,
        callable $modifyColumn,
        bool $expectEmptyDiff,
        ?string $expectedCollation,
    ): void {
        $table = Table::editor()
            ->setUnquotedName('test_column_collation')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('value')
                    ->setTypeName(Types::STRING)
                    ->setLength(50)
                    ->setCollation($oldCollation)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $schemaManager = $this->connection->createSchemaManager();
        $tableFrom     = $schemaManager->introspectTableByUnquotedName('test_column_collation');
        $column        = $tableFrom->getColumn('value');

        self::assertSame($oldCollation, $column->getCollation());

        $tableTo = $tableFrom->edit()
            ->modifyColumnByUnquotedName('value', $modifyColumn)
            ->create();

        $diff = $schemaManager->createComparator()->compareTables($tableFrom, $tableTo);
        self::assertSame($expectEmptyDiff, $diff->isEmpty());

        $schemaManager->alterTable($diff);

        $column = $schemaManager
            ->introspectTableByUnquotedName('test_column_collation')
            ->getColumn('value');

        self::assertSame($expectedCollation, $column->getCollation());
    }

    /**
     * @return iterable<string, array{
     *     ?non-empty-string,
     *     callable(ColumnEditor): void,
     *     bool,
     *     ?non-empty-string
     * }>
     */
    abstract public static function provideAlterColumnCollationCases(): iterable;
}
