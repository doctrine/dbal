<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\ObjectOrigin;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\Constraint\LogicalNot;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_values;
use function sprintf;
use function usort;

abstract class FunctionalTestCase extends TestCase
{
    /**
     * Shared connection when a TestCase is run alone (outside of it's functional suite)
     */
    private static ?Connection $sharedConnection = null;

    protected Connection $connection;

    /**
     * Whether the shared connection could be reused by subsequent tests.
     */
    private bool $isConnectionReusable = true;

    /**
     * Mark shared connection not reusable for subsequent tests.
     *
     * Should be called by the tests that modify configuration
     * or alter the connection state in another way that may impact other tests.
     */
    protected function markConnectionNotReusable(): void
    {
        $this->isConnectionReusable = false;
    }

    #[Before]
    final protected function connect(): void
    {
        if (self::$sharedConnection === null) {
            self::$sharedConnection = TestUtil::getConnection();
        }

        $this->connection = self::$sharedConnection;
    }

    #[After]
    final protected function disconnect(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        if ($this->isConnectionReusable) {
            return;
        }

        if (self::$sharedConnection !== null) {
            self::$sharedConnection->close();
            self::$sharedConnection = null;
        }

        // Make sure the connection is no longer available to the test.
        // Otherwise, there is a chance that a teardown method of the test will reconnect
        // (e.g. to drop a table), and then this reopened connection will remain open and attached to the PHPUnit result
        // until the end of the suite leaking connection resources, while subsequent tests will use
        // the newly established shared connection.
        unset($this->connection); // @phpstan-ignore unset.possiblyHookedProperty

        $this->isConnectionReusable = true;
    }

    /**
     * Drops the table with the specified name, if it exists.
     *
     * @throws Exception
     */
    public function dropTableIfExists(string $name): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        try {
            $schemaManager->dropTable($name);
        } catch (DatabaseObjectNotFoundException) {
        }
    }

    /**
     * Drops and creates a new table.
     *
     * @throws Exception
     */
    public function dropAndCreateTable(Table $table): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $platform      = $this->connection->getDatabasePlatform();
        $tableName     = $table->getObjectName()->toSQL($platform);

        $this->dropTableIfExists($tableName);
        $schemaManager->createTable($table);
    }

    /**
     * Drops the schema with the specified name, if it exists.
     *
     * @throws Exception
     */
    protected function dropSchemaIfExists(UnqualifiedName $schemaName): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform->supportsSchemas()) {
            throw NotSupported::new(__METHOD__);
        }

        $folding = $platform->getUnquotedIdentifierFolding();

        $normalizedSchemaName = $schemaName->getIdentifier()
            ->toNormalizedValue($folding);

        $schemaManager = $this->connection->createSchemaManager();

        $schemaToDrop = Schema::editor();

        if ($platform->supportsSequences()) {
            foreach ($schemaManager->introspectSequences() as $sequence) {
                $qualifier = $sequence->getObjectName()
                    ->getQualifier();

                if ($qualifier === null || $qualifier->toNormalizedValue($folding) !== $normalizedSchemaName) {
                    continue;
                }

                $schemaToDrop->addSequence($sequence);
            }
        }

        foreach ($schemaManager->introspectTables() as $table) {
            $qualifier = $table->getObjectName()
                ->getQualifier();

            if ($qualifier === null || $qualifier->toNormalizedValue($folding) !== $normalizedSchemaName) {
                continue;
            }

            $schemaToDrop->addTable($table);
        }

        $schemaManager->dropSchemaObjects($schemaToDrop->create());

        try {
            $schemaManager->dropSchema($schemaName->toSQL($platform));
        } catch (DatabaseObjectNotFoundException) {
        }
    }

    /**
     * Drops and creates a new schema.
     *
     * @throws Exception
     */
    protected function dropAndCreateSchema(UnqualifiedName $schemaName): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform->supportsSchemas()) {
            throw NotSupported::new(__METHOD__);
        }

        $this->dropSchemaIfExists($schemaName);

        $this->connection->executeStatement(
            $platform->getCreateSchemaSQL($schemaName->toSQL($platform)),
        );
    }

    /** @throws Exception */
    protected function assertOptionallyQualifiedNameEquals(
        OptionallyQualifiedName $expected,
        OptionallyQualifiedName $actual,
    ): void {
        self::assertEquals(
            $this->toComparableOptionallyQualifiedName($expected),
            $this->toComparableOptionallyQualifiedName($actual),
        );
    }

    /** @throws Exception */
    protected function toComparableOptionallyQualifiedName(OptionallyQualifiedName $name): OptionallyQualifiedName
    {
        return new OptionallyQualifiedName(
            $this->toComparableIdentifier($name->getUnqualifiedName()),
            $name->getQualifier() === null
                ? null
                : $this->toComparableIdentifier($name->getQualifier()),
        );
    }

    /** @throws Exception */
    protected function assertUnqualifiedNameEquals(
        UnqualifiedName $expected,
        UnqualifiedName $actual,
    ): void {
        self::assertEquals(
            $this->toComparableUnqualifiedName($expected),
            $this->toComparableUnqualifiedName($actual),
        );
    }

    /** @throws Exception */
    protected function toComparableUnqualifiedName(UnqualifiedName $name): UnqualifiedName
    {
        return new UnqualifiedName(
            $this->toComparableIdentifier($name->getIdentifier()),
        );
    }

    /**
     * @param non-empty-list<UnqualifiedName> $expected
     * @param list<UnqualifiedName>           $actual
     *
     * @throws Exception
     */
    protected function assertUnqualifiedNameListEquals(array $expected, array $actual): void
    {
        self::assertEquals(
            $this->toComparableUnqualifiedNameList($expected),
            $this->toComparableUnqualifiedNameList($actual),
        );
    }

    /**
     * @param non-empty-string          $needle
     * @param iterable<UnqualifiedName> $haystack
     */
    protected function assertUnqualifiedNameListContainsUnquotedName(string $needle, iterable $haystack): void
    {
        static::assertThat(
            $haystack,
            $this->unqualifiedNameListContains(UnqualifiedName::unquoted($needle)),
            sprintf('The list does not contain the unquoted name "%s".', $needle),
        );
    }

    /**
     * @param non-empty-string          $needle
     * @param iterable<UnqualifiedName> $haystack
     */
    protected function assertUnqualifiedNameListContainsQuotedName(string $needle, iterable $haystack): void
    {
        static::assertThat(
            $haystack,
            $this->unqualifiedNameListContains(UnqualifiedName::quoted($needle)),
            sprintf('The list does not contain the quoted name "%s".', $needle),
        );
    }

    /**
     * @param non-empty-string          $needle
     * @param iterable<UnqualifiedName> $haystack
     */
    protected function assertUnqualifiedNameListNotContainsUnquotedName(string $needle, iterable $haystack): void
    {
        static::assertThat(
            $haystack,
            new LogicalNot($this->unqualifiedNameListContains(UnqualifiedName::unquoted($needle))),
            sprintf('The list contains the unquoted name "%s".', $needle),
        );
    }

    private function unqualifiedNameListContains(UnqualifiedName $needle): Callback
    {
        return self::callback(
            /**
             * @param iterable<UnqualifiedName> $haystack
             *
             * @throws Exception
             */
            function (iterable $haystack) use ($needle): bool {
                $platform = $this->connection->getDatabasePlatform();
                $folding  = $platform->getUnquotedIdentifierFolding();

                foreach ($haystack as $name) {
                    if ($name->equals($needle, $folding)) {
                        return true;
                    }
                }

                return false;
            },
        );
    }

    /**
     * @param non-empty-string                  $needleName
     * @param ?non-empty-string                 $needleQualifier
     * @param iterable<OptionallyQualifiedName> $haystack
     *
     * @throws Exception
     */
    protected function assertOptionallyQualifiedNameListContainsUnquotedName(
        string $needleName,
        ?string $needleQualifier,
        iterable $haystack,
    ): void {
        $needle = OptionallyQualifiedName::unquoted($needleName, $needleQualifier);

        static::assertThat(
            $haystack,
            $this->optionallyQualifiedNameListContains($needle),
            sprintf('The list does not contain the unquoted name "%s".', $needle->toString()),
        );
    }

    private function optionallyQualifiedNameListContains(OptionallyQualifiedName $needle): Callback
    {
        return self::callback(
            /**
             * @param iterable<OptionallyQualifiedName> $haystack
             *
             * @throws Exception
             */
            function (iterable $haystack) use ($needle): bool {
                $platform = $this->connection->getDatabasePlatform();
                $folding  = $platform->getUnquotedIdentifierFolding();

                $isNeedleQualified = $needle->getQualifier() !== null;

                foreach ($haystack as $name) {
                    // In the testing context, consider non-equally qualified names non-equal as they don't match field
                    // by field. From the perspective of the library logic, they are still incomparable.
                    if (($name->getQualifier() !== null) !== $isNeedleQualified) {
                        continue;
                    }

                    if ($name->equals($needle, $folding)) {
                        return true;
                    }
                }

                return false;
            },
        );
    }

    /**
     * @param list<UnqualifiedName> $names
     *
     * @return ($names is non-empty-list ? non-empty-list<UnqualifiedName> : list<UnqualifiedName>)
     *
     * @throws Exception
     */
    protected function toComparableUnqualifiedNameList(array $names): array
    {
        return array_map(
            fn (UnqualifiedName $name): UnqualifiedName => $this->toComparableUnqualifiedName($name),
            $names,
        );
    }

    /** @throws Exception */
    protected function toComparableColumn(Column $column): Column
    {
        return $column->edit()
            ->setName($this->toComparableUnqualifiedName($column->getObjectName()))
            ->create();
    }

    /**
     * @param non-empty-list<Column> $expected
     * @param list<Column>           $actual
     *
     * @throws Exception
     */
    protected function assertColumnNamesEqual(array $expected, array $actual): void
    {
        $this->assertUnqualifiedNameListEquals(
            array_map(
                static fn (Column $column): UnqualifiedName => $column->getObjectName(),
                $expected,
            ),
            array_map(
                static fn (Column $column): UnqualifiedName => $column->getObjectName(),
                $actual,
            ),
        );
    }

    /**
     * @param list<Column> $columns
     *
     * @return ($columns is non-empty-list ? non-empty-list<Column> : list<Column>)
     *
     * @throws Exception
     */
    protected function toComparableColumnList(array $columns): array
    {
        return array_map(
            fn (Column $column): Column => $this->toComparableColumn($column),
            $columns,
        );
    }

    /** @throws Exception */
    protected function assertIndexedColumnEquals(
        IndexedColumn $expected,
        IndexedColumn $actual,
    ): void {
        self::assertEquals(
            $this->toComparableIndexedColumn($expected),
            $this->toComparableIndexedColumn($actual),
        );
    }

    /** @throws Exception */
    protected function toComparableIndexedColumn(IndexedColumn $column): IndexedColumn
    {
        return new IndexedColumn(
            $this->toComparableUnqualifiedName($column->getColumnName()),
            $column->getLength(),
        );
    }

    /**
     * @param non-empty-list<IndexedColumn> $expected
     * @param non-empty-list<IndexedColumn> $actual
     *
     * @throws Exception
     */
    protected function assertIndexedColumnListEquals(array $expected, array $actual): void
    {
        self::assertEquals(
            $this->toComparableIndexedColumnList($expected),
            $this->toComparableIndexedColumnList($actual),
        );
    }

    /**
     * @param list<IndexedColumn> $indexedColumns
     *
     * @return ($indexedColumns is non-empty-list ? non-empty-list<IndexedColumn> : list<IndexedColumn>)
     *
     * @throws Exception
     */
    protected function toComparableIndexedColumnList(array $indexedColumns): array
    {
        return array_map(
            fn (IndexedColumn $indexedColumn): IndexedColumn => $this->toComparableIndexedColumn($indexedColumn),
            $indexedColumns,
        );
    }

    /** @throws Exception */
    protected function assertIndexEquals(Index $expected, Index $actual): void
    {
        self::assertEquals($this->toComparableIndex($expected), $this->toComparableIndex($actual));
    }

    /** @throws Exception */
    protected function toComparableIndex(Index $index): Index
    {
        return $index->edit()
            ->setName($this->toComparableUnqualifiedName($index->getObjectName()))
            ->setColumns(...$this->toComparableIndexedColumnList($index->getIndexedColumns()))
            ->setOrigin(ObjectOrigin::DECLARED)
            ->create();
    }

    /**
     * @param array<Index> $expected
     * @param array<Index> $actual
     *
     * @throws Exception
     */
    protected function assertIndexListEquals(array $expected, array $actual): void
    {
        $comparableExpected = $this->toComparableIndexList(array_values($expected));
        $comparableActual   = $this->toComparableIndexList(array_values($actual));

        $folding = $this->connection->getDatabasePlatform()
            ->getUnquotedIdentifierFolding();

        // PHPUnit's implementation of assertEqualsCanonicalizing() sorts object properties and may trigger notices
        // while comparing an integer IndexedColumn::$length with an object IndexedColumn::$columnName
        $comparator = static function (Index $a, Index $b) use ($folding): int {
            return $a->getObjectName()->getIdentifier()->toNormalizedValue($folding)
                <=> $b->getObjectName()->getIdentifier()->toNormalizedValue($folding);
        };

        usort($comparableExpected, $comparator);
        usort($comparableActual, $comparator);

        self::assertEquals($comparableExpected, $comparableActual);
    }

    /**
     * @param list<Index> $indexes
     *
     * @return list<Index>
     *
     * @throws Exception
     */
    protected function toComparableIndexList(array $indexes): array
    {
        return array_map(
            fn (Index $index): Index => $this->toComparableIndex($index),
            $indexes,
        );
    }

    /** @throws Exception */
    protected function assertPrimaryKeyConstraintEquals(
        PrimaryKeyConstraint $expected,
        ?PrimaryKeyConstraint $actual,
    ): void {
        self::assertNotNull($actual);

        $expectedName = $expected->getObjectName();
        $actualName   = $actual->getObjectName();

        // ignore auto-generated name on the actual constraint
        if ($expectedName === null && $actualName !== null) {
            $actual = $actual->edit()
                ->setName(null)
                ->create();
        }

        self::assertEquals(
            $this->toComparablePrimaryKeyConstraint($expected),
            $this->toComparablePrimaryKeyConstraint($actual),
        );
    }

    /** @throws Exception */
    protected function toComparablePrimaryKeyConstraint(PrimaryKeyConstraint $constraint): PrimaryKeyConstraint
    {
        $name = $constraint->getObjectName();

        if ($name !== null) {
            $name = $this->toComparableUnqualifiedName($name);
        }

        return $constraint->edit()
            ->setName($name)
            ->setColumnNames(...$this->toComparableUnqualifiedNameList($constraint->getColumnNames()))
            ->create();
    }

    /** @throws Exception */
    protected function assertForeignKeyConstraintEquals(
        ForeignKeyConstraint $expected,
        ForeignKeyConstraint $actual,
    ): void {
        self::assertEquals(
            $this->toComparableForeignKeyConstraint($expected),
            $this->toComparableForeignKeyConstraint($actual),
        );
    }

    /** @throws Exception */
    protected function toComparableForeignKeyConstraint(ForeignKeyConstraint $constraint): ForeignKeyConstraint
    {
        $name = $constraint->getObjectName();

        if ($name !== null) {
            $name = $this->toComparableUnqualifiedName($name);
        }

        return $constraint->edit()
            ->setName($name)
            ->setReferencingColumnNames(
                ...$this->toComparableUnqualifiedNameList($constraint->getReferencingColumnNames()),
            )
            ->setReferencedTableName($this->toComparableOptionallyQualifiedName($constraint->getReferencedTableName()))
            ->setReferencedColumnNames(
                ...$this->toComparableUnqualifiedNameList($constraint->getReferencedColumnNames()),
            )
            ->create();
    }

    /**
     * @param list<ForeignKeyConstraint> $expected
     * @param list<ForeignKeyConstraint> $actual
     *
     * @throws Exception
     */
    protected function assertForeignKeyConstraintListEquals(array $expected, array $actual): void
    {
        self::assertEquals(
            $this->toComparableForeignKeyConstraintList($expected),
            $this->toComparableForeignKeyConstraintList($actual),
        );
    }

    /**
     * @param list<ForeignKeyConstraint> $constraints
     *
     * @return ($constraints is non-empty-list ? non-empty-list<ForeignKeyConstraint> : list<ForeignKeyConstraint>)
     *
     * @throws Exception
     */
    protected function toComparableForeignKeyConstraintList(array $constraints): array
    {
        return array_map(
            fn (ForeignKeyConstraint $constraint): ForeignKeyConstraint => $this->toComparableForeignKeyConstraint(
                $constraint,
            ),
            $constraints,
        );
    }

    /** @throws Exception */
    protected function assertUniqueConstraintEquals(
        UniqueConstraint $expected,
        UniqueConstraint $actual,
    ): void {
        $expectedName = $expected->getObjectName();
        $actualName   = $actual->getObjectName();

        // ignore auto-generated name on the actual constraint
        if ($expectedName === null && $actualName !== null) {
            $actual = $actual->edit()
                ->setName(null)
                ->create();
        }

        self::assertEquals(
            $this->toComparableUniqueConstraint($expected),
            $this->toComparableUniqueConstraint($actual),
        );
    }

    /** @throws Exception */
    protected function toComparableUniqueConstraint(UniqueConstraint $constraint): UniqueConstraint
    {
        $name = $constraint->getObjectName();

        if ($name !== null) {
            $name = $this->toComparableUnqualifiedName($name);
        }

        return $constraint->edit()
            ->setName($name)
            ->setColumnNames(...$this->toComparableUnqualifiedNameList($constraint->getColumnNames()))
            ->setOrigin(ObjectOrigin::DECLARED)
            ->create();
    }

    /**
     * @param list<UniqueConstraint> $expected
     * @param list<UniqueConstraint> $actual
     *
     * @throws Exception
     */
    protected function assertUniqueConstraintListEquals(array $expected, array $actual): void
    {
        self::assertEquals(
            $this->toComparableUniqueConstraintList($expected),
            $this->toComparableUniqueConstraintList($actual),
        );
    }

    /**
     * @param list<UniqueConstraint> $constraints
     *
     * @return list<UniqueConstraint>
     *
     * @throws Exception
     */
    protected function toComparableUniqueConstraintList(array $constraints): array
    {
        return array_map(
            fn (UniqueConstraint $constraint): UniqueConstraint => $this->toComparableUniqueConstraint($constraint),
            $constraints,
        );
    }

    /** @throws Exception */
    protected function toComparableIdentifier(Identifier $identifier): Identifier
    {
        if ($identifier->isQuoted()) {
            return $identifier;
        }

        return Identifier::quoted(
            $this->connection->getDatabasePlatform()
                ->getUnquotedIdentifierFolding()
                ->foldUnquotedIdentifier($identifier->getValue()),
        );
    }
}
