<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;

use function sprintf;

final class SchemaManagerTest extends FunctionalTestCase
{
    use VerifyDeprecations;

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

        $tableForeign = new Table($foreignTableName);
        $tableForeign->addColumn('id', 'integer');
        $tableForeign->setPrimaryKey(['id']);
        $this->dropAndCreateTable($tableForeign);

        $tableTo = new Table('other_schema.other_table');
        $tableTo->addColumn('id', 'integer');
        $tableTo->addColumn('user_id', 'integer');
        $tableTo->setPrimaryKey(['id']);
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

        $tableFrom = new Table($tableName);
        $tableFrom->addColumn('id', Types::INTEGER);
        $tableFrom->addColumn('name', Types::STRING, ['length' => 32]);
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

        if (! $autoincrement && $platform instanceof SQLitePlatform) {
            self::markTestIncomplete('See https://github.com/doctrine/dbal/issues/6844');
        }

        $table = new Table('test_autoincrement');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => $autoincrement]);
        $table->setPrimaryKey(['id']);
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

        $table = new Table('test_autoincrement');
        $table->addColumn('id1', Types::INTEGER, ['autoincrement' => $autoincrement]);
        $table->addColumn('id2', Types::INTEGER);
        $table->setPrimaryKey(['id1', 'id2']);
        $this->dropAndCreateTable($table);

        $table = $this->schemaManager->introspectTable('test_autoincrement');

        self::assertSame($autoincrement, $table->getColumn('id1')->getAutoincrement());
        self::assertFalse($table->getColumn('id2')->getAutoincrement());
    }

    /** @throws Exception */
    #[TestWith([false])]
    #[TestWith([true])]
    public function testIntrospectTableWithDotInName(bool $quoted): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform->supportsSchemas()) {
            self::markTestIncomplete('DBAL 4.x will fail to introspect this table on a platform that supports schemas');
        }

        $name           = 'example.com';
        $normalizedName = $platform->getUnquotedIdentifierFolding()->foldUnquotedIdentifier($name);
        $quotedName     = $this->connection->quoteSingleIdentifier($normalizedName);

        // create the table manually since identifiers with dots are not supported in DBAL 4.x
        $sql = sprintf('CREATE TABLE %s (s VARCHAR(16))', $quotedName);

        $this->dropTableIfExists($quotedName);
        $this->connection->executeStatement($sql);

        if ($quoted) {
            $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6768');

            $table = $this->schemaManager->introspectTable($quotedName);
        } else {
            $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6768');

            $table = $this->schemaManager->introspectTable($name);
        }

        self::assertCount(1, $table->getColumns());
    }

    /** @throws Exception */
    public function testIntrospectTableWithInvalidName(): void
    {
        $table = new Table('"example"');
        $table->addColumn('id', 'integer');

        $this->dropAndCreateTable($table);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6768');

        $table = $this->schemaManager->introspectTable('"example');
        self::assertCount(1, $table->getColumns());
    }
}
