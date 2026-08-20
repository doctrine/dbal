<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_map;
use function implode;
use function sort;
use function sprintf;

/**
 * Migrating a table to a schema reaches the same state as creating it fresh from that schema, modulo
 * any name the application did not give — the engine names the indexes and constraints it creates.
 */
final class MigrationDeterminismTest extends FunctionalTestCase
{
    /** @throws Exception */
    #[DataProvider('migrationProvider')]
    public function testMigrationReachesTheStateCreationWouldHave(Table $oldTable, Table $newTable): void
    {
        $this->assertMigrationReachesCreation($oldTable, $newTable);
    }

    /**
     * An index the application withdraws is dropped, though a wider one still covers the foreign key.
     *
     * @throws Exception
     */
    public function testWithdrawnIndexCoveredByAWiderOne(): void
    {
        $wide = Index::editor()
            ->setUnquotedName('wide_idx')
            ->setUnquotedColumnNames('parent_id', 'code')
            ->create();

        $narrow = Index::editor()
            ->setUnquotedName('narrow_idx')
            ->setUnquotedColumnNames('parent_id')
            ->create();

        $this->assertMigrationReachesCreation(
            self::childTableWithIndexes($wide, $narrow),
            self::childTableWithIndexes($wide),
        );
    }

    /**
     * Withdrawing every index over a foreign key's columns leaves whatever the engine makes of it.
     *
     * @throws Exception
     */
    public function testEveryCoveringIndexWithdrawn(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof OraclePlatform || $platform instanceof DB2Platform) {
            self::markTestSkipped(sprintf(
                '%s rejects a second index over the columns another one already indexes.',
                $platform::class,
            ));
        }

        $this->assertMigrationReachesCreation(
            self::childTableWithIndexes(
                Index::editor()
                    ->setUnquotedName('a_idx')
                    ->setUnquotedColumnNames('parent_id')
                    ->create(),
                Index::editor()
                    ->setUnquotedName('b_idx')
                    ->setUnquotedColumnNames('parent_id')
                    ->create(),
            ),
            self::childTableWithIndexes(),
        );
    }

    /**
     * Two foreign keys, one over the columns the other starts with, need one index between them.
     *
     * @throws Exception
     */
    public function testNestedForeignKeysNeedOneIndex(): void
    {
        $wide = Index::editor()
            ->setUnquotedName('wide_idx')
            ->setUnquotedColumnNames('parent_id', 'code')
            ->create();

        $narrow = Index::editor()
            ->setUnquotedName('narrow_idx')
            ->setUnquotedColumnNames('parent_id')
            ->create();

        $this->assertMigrationReachesCreation(
            self::childTableWithNestedForeignKeys($wide, $narrow),
            self::childTableWithNestedForeignKeys(),
        );
    }

    private static function childTableWithNestedForeignKeys(Index ...$indexes): Table
    {
        return Table::editor()
            ->setUnquotedName('child')
            ->setColumns(self::intColumn('id'), self::intColumn('parent_id'), self::intColumn('code'))
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setIndexes(...$indexes)
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('parent_fk')
                    ->setUnquotedReferencingColumnNames('parent_id')
                    ->setUnquotedReferencedTableName('parent')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('composite_fk')
                    ->setUnquotedReferencingColumnNames('parent_id', 'code')
                    ->setUnquotedReferencedTableName('composite_parent')
                    ->setUnquotedReferencedColumnNames('id', 'code')
                    ->create(),
            )
            ->create();
    }

    private static function childTableWithIndexes(Index ...$indexes): Table
    {
        return Table::editor()
            ->setUnquotedName('child')
            ->setColumns(self::intColumn('id'), self::intColumn('parent_id'), self::intColumn('code'))
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setIndexes(...$indexes)
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('parent_fk')
                    ->setUnquotedReferencingColumnNames('parent_id')
                    ->setUnquotedReferencedTableName('parent')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();
    }

    /** @throws Exception */
    private function assertMigrationReachesCreation(Table $oldTable, Table $newTable): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        $this->dropTableIfExists('child');
        $this->dropTableIfExists('parent');
        $this->dropTableIfExists('composite_parent');
        $schemaManager->createTable(self::parentTable());
        $schemaManager->createTable(self::compositeParentTable());

        $schemaManager->createTable($oldTable);

        $diff = $schemaManager->createComparator()
            ->compareTables($schemaManager->introspectTableByUnquotedName('child'), $newTable);

        if (! $diff->isEmpty()) {
            $schemaManager->alterTable($diff);
        }

        $migrated = $schemaManager->introspectTableByUnquotedName('child');

        $this->dropTableIfExists('child');
        $schemaManager->createTable($newTable);

        $created = $schemaManager->introspectTableByUnquotedName('child');

        $this->assertIndistinguishable($created, $migrated, $newTable);
    }

    /** @return iterable<string, array{Table, Table}> */
    public static function migrationProvider(): iterable
    {
        yield 'a foreign key is added' => [
            self::childTable(),
            self::childTable(foreignKey: true),
        ];

        yield 'a foreign key is dropped' => [
            self::childTable(foreignKey: true),
            self::childTable(),
        ];

        yield 'an index over a foreign key is declared' => [
            self::childTable(foreignKey: true),
            self::childTable(foreignKey: true, index: true),
        ];

        yield 'an index over a foreign key is withdrawn' => [
            self::childTable(foreignKey: true, index: true),
            self::childTable(foreignKey: true),
        ];

        yield 'the primary key moves to another column' => [
            self::childTable(),
            self::childTable(primaryKeyColumnName: 'code'),
        ];

        yield 'a unique constraint is added' => [
            self::childTable(),
            self::childTable(uniqueConstraint: true),
        ];

        yield 'a unique constraint is dropped' => [
            self::childTable(uniqueConstraint: true),
            self::childTable(),
        ];

        // On MySQL a unique index is at once a unique constraint, so a migration that dropped the
        // derived constraint would void the user's unique index.
        yield 'a unique index is added' => [
            self::childTable(),
            self::childTable(uniqueIndex: true),
        ];

        yield 'a table with a unique index is unchanged' => [
            self::childTable(uniqueIndex: true),
            self::childTable(uniqueIndex: true),
        ];

        yield 'nothing changes' => [
            self::childTable(uniqueConstraint: true, foreignKey: true),
            self::childTable(uniqueConstraint: true, foreignKey: true),
        ];
    }

    private static function compositeParentTable(): Table
    {
        return Table::editor()
            ->setUnquotedName('composite_parent')
            ->setColumns(self::intColumn('id'), self::intColumn('code'))
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id', 'code')
                    ->create(),
            )
            ->create();
    }

    private static function parentTable(): Table
    {
        return Table::editor()
            ->setUnquotedName('parent')
            ->setColumns(self::intColumn('id'))
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();
    }

    /** @param non-empty-string $primaryKeyColumnName */
    private static function childTable(
        bool $uniqueConstraint = false,
        bool $foreignKey = false,
        bool $index = false,
        bool $uniqueIndex = false,
        string $primaryKeyColumnName = 'id',
    ): Table {
        $editor = Table::editor()
            ->setUnquotedName('child')
            ->setColumns(
                self::intColumn('id'),
                self::intColumn('parent_id'),
                self::intColumn('code'),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames($primaryKeyColumnName)
                    ->create(),
            );

        if ($uniqueConstraint) {
            $editor->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedName('code_uq')
                    ->setUnquotedColumnNames('code')
                    ->create(),
            );
        }

        if ($foreignKey) {
            $editor->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('parent_fk')
                    ->setUnquotedReferencingColumnNames('parent_id')
                    ->setUnquotedReferencedTableName('parent')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            );
        }

        $indexes = [];

        if ($index) {
            $indexes[] = Index::editor()
                ->setUnquotedName('parent_idx')
                ->setUnquotedColumnNames('parent_id')
                ->create();
        }

        if ($uniqueIndex) {
            $indexes[] = Index::editor()
                ->setUnquotedName('code_uidx')
                ->setUnquotedColumnNames('code')
                ->setType(IndexType::UNIQUE)
                ->create();
        }

        if ($indexes !== []) {
            $editor->setIndexes(...$indexes);
        }

        return $editor->create();
    }

    /** @param non-empty-string $name */
    private static function intColumn(string $name): Column
    {
        return Column::editor()
            ->setUnquotedName($name)
            ->setTypeName(Types::INTEGER)
            // Every column is a candidate for the primary key, which cannot be nullable.
            ->setNotNull(true)
            ->create();
    }

    /**
     * Asserts that two introspected tables differ in nothing but the names of the indexes the engine
     * created for itself.
     *
     * @throws Exception
     */
    private function assertIndistinguishable(Table $expected, Table $actual, Table $declared): void
    {
        $this->assertColumnNamesEqual($expected->getColumns(), $actual->getColumns());

        $declaredPrimaryKeyConstraint = $declared->getPrimaryKeyConstraint();
        self::assertNotNull($declaredPrimaryKeyConstraint);

        $expectedPrimaryKeyConstraint = $expected->getPrimaryKeyConstraint();
        self::assertNotNull($expectedPrimaryKeyConstraint);

        $actualPrimaryKeyConstraint = $actual->getPrimaryKeyConstraint();
        self::assertNotNull($actualPrimaryKeyConstraint);

        if ($declaredPrimaryKeyConstraint->getObjectName() === null) {
            // The application did not name the constraint, so the engine did — and it names each one
            // it creates, not each one it is asked for: Oracle hands out SYS_C0010548 to the create
            // and SYS_C0010553 to the migration. Everything but the name must still agree.
            $expectedPrimaryKeyConstraint = self::withoutName($expectedPrimaryKeyConstraint);
            $actualPrimaryKeyConstraint   = self::withoutName($actualPrimaryKeyConstraint);
        }

        $this->assertPrimaryKeyConstraintEquals($expectedPrimaryKeyConstraint, $actualPrimaryKeyConstraint);
        $this->assertForeignKeyConstraintListEquals($expected->getForeignKeys(), $actual->getForeignKeys());
        $this->assertUniqueConstraintListEquals($expected->getUniqueConstraints(), $actual->getUniqueConstraints());

        $this->assertIndexListEquals(
            $this->declaredIndexes($expected, $declared),
            $this->declaredIndexes($actual, $declared),
        );

        self::assertSame(
            $this->indexShapes($this->engineIndexes($expected, $declared)),
            $this->indexShapes($this->engineIndexes($actual, $declared)),
            'the engine\'s own indexes differ in more than their names',
        );
    }

    private static function withoutName(PrimaryKeyConstraint $constraint): PrimaryKeyConstraint
    {
        return $constraint->edit()
            ->setName(null)
            ->create();
    }

    /**
     * @param array<Index> $indexes
     *
     * @return list<string>
     */
    private function indexShapes(array $indexes): array
    {
        $shapes = array_map(
            static function (Index $index): string {
                $columns = array_map(
                    static fn (Index\IndexedColumn $column): string => $column->getColumnName()
                        ->getIdentifier()
                        ->getValue() . '(' . ($column->getLength() ?? '') . ')',
                    $index->getIndexedColumns(),
                );

                return $index->getType()->name
                    . ' [' . implode(', ', $columns) . ']'
                    . ' ' . ($index->getPredicate() ?? '');
            },
            $indexes,
        );

        sort($shapes);

        return $shapes;
    }

    /**
     * @return list<Index>
     *
     * @throws Exception
     */
    private function declaredIndexes(Table $table, Table $declared): array
    {
        return $this->partitionIndexes($table, $declared, true);
    }

    /**
     * @return list<Index>
     *
     * @throws Exception
     */
    private function engineIndexes(Table $table, Table $declared): array
    {
        return $this->partitionIndexes($table, $declared, false);
    }

    /**
     * @return list<Index>
     *
     * @throws Exception
     */
    private function partitionIndexes(Table $table, Table $declared, bool $keepDeclared): array
    {
        $folding = $this->connection->getDatabasePlatform()
            ->getUnquotedIdentifierFolding();

        $indexes = [];

        foreach ($table->getIndexes() as $index) {
            if ($this->isDeclared($index, $declared, $folding) === $keepDeclared) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    private function isDeclared(Index $index, Table $declared, UnquotedIdentifierFolding $folding): bool
    {
        foreach ($declared->getIndexes() as $declaredIndex) {
            if ($index->getObjectName()->equals($declaredIndex->getObjectName(), $folding)) {
                return true;
            }
        }

        return false;
    }
}
