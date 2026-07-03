<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Exception\UnsupportedName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;

final class SchemaManagerTest extends FunctionalTestCase
{
    private AbstractSchemaManager $schemaManager;

    /** @throws Exception */
    #[Override]
    protected function setUp(): void
    {
        $this->schemaManager = $this->connection->createSchemaManager();
    }

    #[DataProvider('dataEmptyDiffRegardlessOfForeignTableQuotes')]
    public function testEmptyDiffRegardlessOfForeignTableQuotes(OptionallyQualifiedName $foreignTableName): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSchemas()) {
            self::markTestSkipped('Platform does not support schemas.');
        }

        $this->dropAndCreateSchema(UnqualifiedName::unquoted('other_schema'));

        $tableForeign = Table::editor()
            ->setName($foreignTableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($tableForeign);

        $tableTo = Table::editor()
            ->setUnquotedName('other_table', 'other_schema')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('user_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('user_id')
                    ->setReferencedTableName($foreignTableName)
                    ->setUnquotedReferencedColumnNames('id')
                    ->setUnquotedName('fk_user_id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($tableTo);

        $schemaFrom = $this->schemaManager->introspectSchema();
        $tableFrom  = $schemaFrom->getTable('other_schema.other_table');

        $diff = $this->schemaManager->createComparator()->compareTables($tableFrom, $tableTo);
        self::assertTrue($diff->isEmpty());
    }

    /** @return iterable<string,array{OptionallyQualifiedName}> */
    public static function dataEmptyDiffRegardlessOfForeignTableQuotes(): iterable
    {
        return [
            'unquoted' => [OptionallyQualifiedName::unquoted('user', 'other_schema')],
            'partially quoted' => [
                new OptionallyQualifiedName(
                    Identifier::quoted('user'),
                    Identifier::unquoted('other_schema'),
                ),
            ],
            'fully quoted' => [OptionallyQualifiedName::quoted('user', 'other_schema')],
        ];
    }

    #[DataProvider('dataDropIndexInAnotherSchema')]
    public function testDropIndexInAnotherSchema(OptionallyQualifiedName $tableName): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSchemas()) {
            self::markTestSkipped('Platform does not support schemas.');
        }

        $this->dropAndCreateSchema(UnqualifiedName::unquoted('other_schema'));
        $this->dropAndCreateSchema(UnqualifiedName::quoted('case'));

        $tableFrom = Table::editor()
            ->setName($tableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('some_table_name_unique_index')
                    ->setUnquotedColumnNames('name')
                    ->setType(IndexType::UNIQUE)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($tableFrom);

        $tableTo = $tableFrom->edit()
            ->dropIndexByUnquotedName('some_table_name_unique_index')
            ->create();

        $diff = $this->schemaManager->createComparator()->compareTables($tableFrom, $tableTo);
        self::assertFalse($diff->isEmpty());

        $this->schemaManager->alterTable($diff);
        $tableFinal = $this->schemaManager->introspectTable($tableName);
        self::assertEmpty($tableFinal->getIndexes());
    }

    /** @return iterable<string,array{OptionallyQualifiedName}> */
    public static function dataDropIndexInAnotherSchema(): iterable
    {
        return [
            'default schema' => [OptionallyQualifiedName::unquoted('some_table')],
            'unquoted schema' => [OptionallyQualifiedName::unquoted('some_table', 'other_schema')],
            'quoted schema' => [
                new OptionallyQualifiedName(
                    Identifier::unquoted('some_table'),
                    Identifier::quoted('other_schema'),
                ),
            ],
            'reserved schema' => [OptionallyQualifiedName::unquoted('some_table', 'case')],
        ];
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testAutoIncrementColumnIntrospection(bool $autoincrement): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform->supportsIdentityColumns()) {
            self::markTestSkipped('This test is only supported on platforms that have autoincrement');
        }

        $table = Table::editor()
            ->setUnquotedName('test_autoincrement')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement($autoincrement)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $table = $this->schemaManager->introspectTableByUnquotedName('test_autoincrement');

        self::assertSame($autoincrement, $table->getColumn('id')->getAutoincrement());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testAutoIncrementColumnInCompositePrimaryKeyIntrospection(bool $autoincrement): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform->supportsIdentityColumns()) {
            self::markTestSkipped('This test is only supported on platforms that have autoincrement');
        }

        if ($autoincrement && $platform instanceof SQLitePlatform) {
            self::markTestSkipped(
                'SQLite does not support auto-increment columns as part of composite primary key constraint',
            );
        }

        $table = Table::editor()
            ->setUnquotedName('test_autoincrement')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id1')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement($autoincrement)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('id2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id1', 'id2')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $table = $this->schemaManager->introspectTableByUnquotedName('test_autoincrement');

        self::assertSame($autoincrement, $table->getColumn('id1')->getAutoincrement());
        self::assertFalse($table->getColumn('id2')->getAutoincrement());
    }

    /** @throws Exception */
    public function testIntrospectTableWithDotInName(): void
    {
        $table = Table::editor()
            ->setQuotedName('example.com')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $table = $this->schemaManager->introspectTableByQuotedName('example.com');

        self::assertCount(1, $table->getColumns());
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testChangeColumnNullability(bool $notNull): void
    {
        $table = Table::editor()
            ->setUnquotedName('change_column_nullability')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('val')
                    ->setTypeName(Types::INTEGER)
                    ->setNotNull(! $notNull)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $table = $this->schemaManager->introspectTableByUnquotedName('change_column_nullability');

        $newTable = $table->edit()
            ->modifyColumnByUnquotedName('val', static function (ColumnEditor $editor) use ($notNull): void {
                $editor->setNotNull($notNull);
            })
            ->create();

        $diff = $this->schemaManager->createComparator()
            ->compareTables($table, $newTable);

        self::assertFalse($diff->isEmpty());

        $this->schemaManager->alterTable($diff);

        self::assertSame(
            $notNull,
            $this->schemaManager->introspectTableByUnquotedName('change_column_nullability')
                ->getColumn('val')
                ->getNotnull(),
        );
    }

    public function testAlterSequence(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSequences()) {
            self::markTestSkipped('The platform does not support sequences.');
        }

        $name = 'alter_sequence_test_seq';

        $this->schemaManager->createSequence(
            Sequence::editor()
                ->setUnquotedName($name)
                ->setAllocationSize(1)
                ->create(),
        );

        $sequence = $this->findSequence($this->schemaManager->introspectSequences(), $name);
        self::assertNotNull($sequence);

        $oldSchema = Schema::editor()
            ->addSequence($sequence)
            ->create();

        $newSchema = Schema::editor()
            ->addSequence(
                Sequence::editor()
                    ->setName($sequence->getObjectName())
                    ->setAllocationSize(5)
                    ->create(),
            )
            ->create();

        $diff = $this->schemaManager->createComparator()
            ->compareSchemas($oldSchema, $newSchema);

        self::assertFalse($diff->isEmpty());

        $this->schemaManager->alterSchema($diff);

        $sequence = $this->findSequence($this->schemaManager->introspectSequences(), $name);
        self::assertNotNull($sequence);
        self::assertSame(5, $sequence->getAllocationSize());
    }

    public function testDropForeignKey(): void
    {
        $this->dropTableIfExists('orders');
        $this->dropTableIfExists('articles');

        $articles = Table::editor()
            ->setUnquotedName('articles')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $orders = Table::editor()
            ->setUnquotedName('orders')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('article_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('articles_fk')
                    ->setUnquotedReferencingColumnNames('article_id')
                    ->setUnquotedReferencedTableName('articles')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->schemaManager->createTable($articles);
        $this->schemaManager->createTable($orders);

        $this->schemaManager->dropForeignKey('articles_fk', 'orders');

        self::assertEmpty(
            $this->schemaManager->introspectTableByUnquotedName('orders')
                ->getForeignKeys(),
        );
    }

    /** @param callable(AbstractSchemaManager): list<Index> $introspect */
    #[DataProvider('quotedAndUnquotedIndexIntrospection')]
    public function testIntrospectTableIndexes(
        OptionallyQualifiedName $tableName,
        UnqualifiedName $indexName,
        callable $introspect,
    ): void {
        $this->createTableWithIndex($tableName, $indexName);

        self::assertNotEmpty($introspect($this->schemaManager));
    }

    /** @return iterable<string, array{
     *     OptionallyQualifiedName,
     *     UnqualifiedName,
     *     callable(AbstractSchemaManager): list<Index>,
     *  }> */
    public static function quotedAndUnquotedIndexIntrospection(): iterable
    {
        yield 'unquoted name' => [
            OptionallyQualifiedName::unquoted('Orders'),
            UnqualifiedName::unquoted('Orders_index'),
            static fn (AbstractSchemaManager $sm): array => $sm->introspectTableIndexesByUnquotedName('Orders'),
        ];

        yield 'quoted name' => [
            OptionallyQualifiedName::quoted('Orders'),
            UnqualifiedName::quoted('Orders_index'),
            static fn (AbstractSchemaManager $sm): array => $sm->introspectTableIndexesByQuotedName('Orders'),
        ];
    }

    /** @param callable(AbstractSchemaManager): list<ForeignKeyConstraint> $introspect */
    #[DataProvider('quotedAndUnquotedForeignKeyIntrospection')]
    public function testIntrospectTableForeignKeyConstraints(
        OptionallyQualifiedName $referencedTableName,
        OptionallyQualifiedName $referencingTableName,
        UnqualifiedName $indexName,
        UnqualifiedName $foreignKeyName,
        callable $introspect,
    ): void {
        $this->createTablesWithForeignKey($referencedTableName, $referencingTableName, $indexName, $foreignKeyName);

        self::assertNotEmpty($introspect($this->schemaManager));
    }

    /**
     * @return iterable<string, array{
     *     OptionallyQualifiedName,
     *     OptionallyQualifiedName,
     *     UnqualifiedName,
     *     UnqualifiedName,
     *     callable(AbstractSchemaManager): list<ForeignKeyConstraint>,
     * }>
     */
    public static function quotedAndUnquotedForeignKeyIntrospection(): iterable
    {
        yield 'unquoted name' => [
            OptionallyQualifiedName::unquoted('Articles'),
            OptionallyQualifiedName::unquoted('Orders'),
            UnqualifiedName::unquoted('Orders_article_id_index'),
            UnqualifiedName::unquoted('Articles_fk'),
            static function (AbstractSchemaManager $sm): array {
                return $sm->introspectTableForeignKeyConstraintsByUnquotedName('Orders');
            },
        ];

        yield 'quoted name' => [
            OptionallyQualifiedName::quoted('Articles'),
            OptionallyQualifiedName::quoted('Orders'),
            UnqualifiedName::quoted('Orders_article_id_index'),
            UnqualifiedName::quoted('Articles_fk'),
            static function (AbstractSchemaManager $sm): array {
                return $sm->introspectTableForeignKeyConstraintsByQuotedName('Orders');
            },
        ];
    }

    private function createTableWithIndex(OptionallyQualifiedName $tableName, UnqualifiedName $indexName): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $table = Table::editor()
            ->setName($tableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setName($indexName)
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropTableIfExists($table->getObjectName()->toSQL($platform));

        $this->schemaManager->createTable($table);
    }

    private function createTablesWithForeignKey(
        OptionallyQualifiedName $referencedTableName,
        OptionallyQualifiedName $referencingTableName,
        UnqualifiedName $indexName,
        UnqualifiedName $foreignKeyName,
    ): void {
        $platform = $this->connection->getDatabasePlatform();

        $referencedTable = Table::editor()
            ->setName($referencedTableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $referencingTable = Table::editor()
            ->setName($referencingTableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('article_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            // Create the index explicitly to prevent the implicit one, whose name is auto-generated.
            // That generation is faulty for names that differ only in whether they are quoted.
            // See https://github.com/doctrine/dbal/issues/7434
            ->setIndexes(
                Index::editor()
                    ->setName($indexName)
                    ->setUnquotedColumnNames('article_id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setName($foreignKeyName)
                    ->setUnquotedReferencingColumnNames('article_id')
                    ->setReferencedTableName($referencedTableName)
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropTableIfExists($referencingTable->getObjectName()->toSQL($platform));
        $this->dropTableIfExists($referencedTable->getObjectName()->toSQL($platform));

        $this->schemaManager->createTable($referencedTable);
        $this->schemaManager->createTable($referencingTable);
    }

    public function testIntrospectSchemaNamesOnSchemalessPlatform(): void
    {
        if ($this->connection->getDatabasePlatform()->supportsSchemas()) {
            self::markTestSkipped('The platform supports schemas.');
        }

        $this->expectException(NotSupported::class);

        $this->schemaManager->introspectSchemaNames();
    }

    public function testIntrospectSequencesWithoutSequenceSupport(): void
    {
        if ($this->connection->getDatabasePlatform()->supportsSequences()) {
            self::markTestSkipped('The platform supports sequences.');
        }

        $this->expectException(NotSupported::class);

        $this->schemaManager->introspectSequences();
    }

    public function testCreateSequenceWithoutSequenceSupport(): void
    {
        if ($this->connection->getDatabasePlatform()->supportsSequences()) {
            self::markTestSkipped('The platform supports sequences.');
        }

        $this->expectException(NotSupported::class);

        $this->schemaManager->createSequence(
            Sequence::editor()
                ->setUnquotedName('s')
                ->create(),
        );
    }

    /** @param callable(AbstractSchemaManager): mixed $introspect */
    #[DataProvider('introspectionWithSchemaNameProvider')]
    public function testIntrospectionWithSchemaNameWithoutSchemaSupport(callable $introspect): void
    {
        if ($this->connection->getDatabasePlatform()->supportsSchemas()) {
            self::markTestSkipped('The platform supports schemas.');
        }

        $this->expectException(UnsupportedName::class);

        $introspect($this->schemaManager);
    }

    /** @return iterable<string, array{callable(AbstractSchemaManager): mixed}> */
    public static function introspectionWithSchemaNameProvider(): iterable
    {
        $tableName = OptionallyQualifiedName::unquoted('orders', 'billing');

        yield 'table' => [
            static fn (AbstractSchemaManager $sm): Table => $sm->introspectTable($tableName),
        ];

        yield 'indexes' => [
            static fn (AbstractSchemaManager $sm): array => $sm->introspectTableIndexes($tableName),
        ];

        yield 'primary key constraint' => [
            static fn (
                AbstractSchemaManager $sm,
            ): ?PrimaryKeyConstraint => $sm->introspectTablePrimaryKeyConstraint($tableName),
        ];

        yield 'foreign key constraints' => [
            static fn (AbstractSchemaManager $sm): array => $sm->introspectTableForeignKeyConstraints($tableName),
        ];
    }

    /** @param callable(AbstractSchemaManager): void $drop */
    #[DataProvider('dropWithInvalidNameProvider')]
    public function testDropWithInvalidName(callable $drop): void
    {
        $this->expectException(InvalidName::class);

        $drop($this->schemaManager);
    }

    /** @return iterable<string, array{callable(AbstractSchemaManager): void}> */
    public static function dropWithInvalidNameProvider(): iterable
    {
        yield 'table name' => [
            static fn (AbstractSchemaManager $sm) => $sm->dropTable('"orders'),
        ];

        yield 'index name' => [
            static fn (AbstractSchemaManager $sm) => $sm->dropIndex('"idx_article', 'orders'),
        ];

        yield 'index table name' => [
            static fn (AbstractSchemaManager $sm) => $sm->dropIndex('idx_article', '"orders'),
        ];

        yield 'foreign key constraint name' => [
            static fn (AbstractSchemaManager $sm) => $sm->dropForeignKey('"fk_article', 'orders'),
        ];

        yield 'foreign key constraint table name' => [
            static fn (AbstractSchemaManager $sm) => $sm->dropForeignKey('fk_article', '"orders'),
        ];

        yield 'unique constraint name' => [
            static fn (AbstractSchemaManager $sm) => $sm->dropUniqueConstraint('"uq_article', 'orders'),
        ];

        yield 'unique constraint table name' => [
            static fn (AbstractSchemaManager $sm) => $sm->dropUniqueConstraint('uq_article', '"orders'),
        ];

        yield 'view name' => [
            static fn (AbstractSchemaManager $sm) => $sm->dropView('"available_articles'),
        ];
    }

    /**
     * @param array<Sequence>  $sequences
     * @param non-empty-string $name
     *
     * @throws Exception
     */
    private function findSequence(array $sequences, string $name): ?Sequence
    {
        $expectedName = Identifier::unquoted($name);

        $folding = $this->connection->getDatabasePlatform()
            ->getUnquotedIdentifierFolding();

        foreach ($sequences as $sequence) {
            if ($sequence->getObjectName()->getUnqualifiedName()->equals($expectedName, $folding)) {
                return $sequence;
            }
        }

        return null;
    }
}
