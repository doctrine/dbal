<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\InvalidName;
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
    public function testEmptyDiffRegardlessOfForeignTableQuotes(string $foreignTableName): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSchemas()) {
            self::markTestSkipped('Platform does not support schemas.');
        }

        $this->dropAndCreateSchema(UnqualifiedName::unquoted('other_schema'));

        $tableForeign = new Table($foreignTableName, [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $tableForeign->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );
        $this->dropAndCreateTable($tableForeign);

        $tableTo = new Table('other_schema.other_table', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('user_id')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $tableTo->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );
        $tableTo->addForeignKeyConstraint($foreignTableName, ['user_id'], ['id']);
        $this->dropAndCreateTable($tableTo);

        $schemaFrom = $this->schemaManager->introspectSchema();
        $tableFrom  = $schemaFrom->getTable('other_schema.other_table');

        $diff = $this->schemaManager->createComparator()->compareTables($tableFrom, $tableTo);
        self::assertTrue($diff->isEmpty());
    }

    /** @return iterable<string,array{string}> */
    public static function dataEmptyDiffRegardlessOfForeignTableQuotes(): iterable
    {
        return [
            'unquoted' => ['other_schema.user'],
            'partially quoted' => ['other_schema."user"'],
            'fully quoted' => ['"other_schema"."user"'],
        ];
    }

    #[DataProvider('dataDropIndexInAnotherSchema')]
    public function testDropIndexInAnotherSchema(string $tableName): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSchemas()) {
            self::markTestSkipped('Platform does not support schemas.');
        }

        $this->dropAndCreateSchema(UnqualifiedName::unquoted('other_schema'));
        $this->dropAndCreateSchema(UnqualifiedName::quoted('case'));

        $tableFrom = new Table($tableName, [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('name')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->create(),
        ]);
        $tableFrom->addUniqueIndex(['name'], 'some_table_name_unique_index');
        $this->dropAndCreateTable($tableFrom);

        $tableTo = clone $tableFrom;
        $tableTo->dropIndex('some_table_name_unique_index');

        $diff = $this->schemaManager->createComparator()->compareTables($tableFrom, $tableTo);
        self::assertFalse($diff->isEmpty());

        $this->schemaManager->alterTable($diff);
        $tableFinal = $this->schemaManager->introspectTable($tableName);
        self::assertEmpty($tableFinal->getIndexes());
    }

    /** @return iterable<string,array{string}> */
    public static function dataDropIndexInAnotherSchema(): iterable
    {
        return [
            'default schema' => ['some_table'],
            'unquoted schema' => ['other_schema.some_table'],
            'quoted schema' => ['"other_schema".some_table'],
            'reserved schema' => ['case.some_table'],
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

        $table = new Table('test_autoincrement', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setAutoincrement($autoincrement)
                ->create(),
        ]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );
        $this->dropAndCreateTable($table);

        $table = $this->schemaManager->introspectTable('test_autoincrement');

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

        $table = new Table('test_autoincrement', [
            Column::editor()
                ->setUnquotedName('id1')
                ->setTypeName(Types::INTEGER)
                ->setAutoincrement($autoincrement)
                ->create(),
            Column::editor()
                ->setUnquotedName('id2')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id1', 'id2')
                ->create(),
        );
        $this->dropAndCreateTable($table);

        $table = $this->schemaManager->introspectTable('test_autoincrement');

        self::assertSame($autoincrement, $table->getColumn('id1')->getAutoincrement());
        self::assertFalse($table->getColumn('id2')->getAutoincrement());
    }

    /** @throws Exception */
    public function testIntrospectTableWithDotInName(): void
    {
        $table = new Table('"example.com"');
        $table->addColumn('id', Types::INTEGER);

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
