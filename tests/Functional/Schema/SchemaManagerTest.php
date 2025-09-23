<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;

final class SchemaManagerTest extends FunctionalTestCase
{
    private AbstractSchemaManager $schemaManager;

    /** @throws Exception */
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
        $tableFinal = $this->schemaManager->introspectTable($tableName->toString());
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

        $table = $this->schemaManager->introspectTable('"example.com"');

        self::assertCount(1, $table->getColumns());
    }

    /** @throws Exception */
    public function testIntrospectTableWithInvalidName(): void
    {
        $this->expectException(InvalidName::class);

        $this->schemaManager->introspectTable('"example');
    }
}
