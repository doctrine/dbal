<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
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
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;

use function sprintf;
use function array_values;

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

        if (! $autoincrement && $platform instanceof SQLitePlatform) {
            self::markTestIncomplete('See https://github.com/doctrine/dbal/issues/6844');
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
        $table = Table::editor()
            ->setQuotedName('example')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6768');

        $table = $this->schemaManager->introspectTable('"example');
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

    public function testAlterTableRenameForeignKey(): void
    {
        $tableForeign = Table::editor()
            ->setUnquotedName('fk_referenced')
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

        $fk = ForeignKeyConstraint::editor()
            ->setUnquotedName('foo_constraint')
            ->setUnquotedReferencingColumnNames('foreign_id')
            ->setReferencedTableName(OptionallyQualifiedName::unquoted('fk_referenced'))
            ->setUnquotedReferencedColumnNames('id')
            ->create();

        $tableFrom = Table::editor()
            ->setUnquotedName('fk_referencing')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('foreign_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setForeignKeyConstraints($fk)
            ->create();

        $this->dropAndCreateTable($tableFrom);

        $tableTo = $tableFrom->edit()
            ->setForeignKeyConstraints(
                $fk->edit()
                    ->setUnquotedName('bar_constraint')
                    ->create(),
            )
            ->create();

        $diff = $this->schemaManager->createComparator()->compareTables($tableFrom, $tableTo);
        self::assertFalse($diff->isEmpty());

        $this->schemaManager->alterTable($diff);

        $foreignKeys = $this->schemaManager->introspectTableByUnquotedName('fk_referencing')->getForeignKeys();
        self::assertCount(1, $foreignKeys);

        $foreignKey = array_values($foreignKeys)[0];
        self::assertNotNull($foreignKey);

        self::assertTrue(
            $foreignKey->getObjectName()->equals(
                UnqualifiedName::unquoted('bar_constraint'),
                $this->connection->getDatabasePlatform()->getUnquotedIdentifierFolding(),
            ),
        );

        $this->dropTableIfExists('fk_referencing');
        $this->dropTableIfExists('fk_referenced');
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
