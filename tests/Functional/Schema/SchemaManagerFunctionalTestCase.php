<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Schema\View;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\SmallFloatType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\TimeType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_filter;
use function array_keys;
use function array_map;
use function array_search;
use function array_values;
use function count;
use function get_debug_type;
use function sprintf;
use function str_starts_with;
use function strcasecmp;
use function strtolower;

abstract class SchemaManagerFunctionalTestCase extends FunctionalTestCase
{
    protected AbstractSchemaManager $schemaManager;

    abstract protected function supportsPlatform(AbstractPlatform $platform): bool;

    protected function setUp(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $this->supportsPlatform($platform)) {
            self::markTestSkipped(sprintf('Skipping since connected to %s', get_debug_type($platform)));
        }

        $this->schemaManager = $this->connection->createSchemaManager();
    }

    protected function tearDown(): void
    {
        if (! isset($this->schemaManager)) {
            return;
        }

        //TODO: SchemaDiff does not drop removed namespaces?
        try {
            //sql server versions below 2016 do not support 'IF EXISTS' so we have to catch the exception here
            $this->connection->executeStatement('DROP SCHEMA testschema');
        } catch (Exception) {
        }

        try {
            $this->connection->executeStatement('DROP VIEW test_view');
        } catch (Exception) {
        }

        try {
            $this->connection->executeStatement('DROP VIEW doctrine_test_view');
        } catch (Exception) {
        }
    }

    public function testCreateSequence(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform->supportsSequences()) {
            self::markTestSkipped('The platform does not support sequences.');
        }

        $name = 'create_sequences_test_seq';

        $this->schemaManager->createSequence(new Sequence($name));

        self::assertTrue($this->hasElementWithName($this->schemaManager->listSequences(), $name));
    }

    /** @param AbstractAsset<OptionallyQualifiedName>[] $items */
    private function hasElementWithName(array $items, string $name): bool
    {
        $filteredList = $this->filterElementsByName($items, $name);

        return count($filteredList) === 1;
    }

    /**
     * @param T[] $items
     *
     * @return T[]
     *
     * @template T of AbstractAsset<OptionallyQualifiedName>
     */
    private function filterElementsByName(array $items, string $name): array
    {
        return array_filter(
            $items,
            static function (AbstractAsset $item) use ($name): bool {
                return strtolower($item->getName()) === $name;
            },
        );
    }

    public function testListSequences(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform->supportsSequences()) {
            self::markTestSkipped('The platform does not support sequences.');
        }

        $this->schemaManager->createSequence(
            new Sequence('list_sequences_test_seq', 20, 10),
        );

        foreach ($this->schemaManager->listSequences() as $sequence) {
            if (strtolower($sequence->getName()) === 'list_sequences_test_seq') {
                self::assertSame(20, $sequence->getAllocationSize());
                self::assertSame(10, $sequence->getInitialValue());

                return;
            }
        }

        self::fail('Sequence was not found.');
    }

    public function testListDatabases(): void
    {
        try {
            $this->schemaManager->dropDatabase('test_create_database');
        } catch (DatabaseObjectNotFoundException) {
        }

        $this->schemaManager->createDatabase('test_create_database');

        $databases = $this->schemaManager->listDatabases();

        $databases = array_map('strtolower', $databases);

        self::assertContains('test_create_database', $databases);
    }

    public function testListSchemaNames(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform->supportsSchemas()) {
            self::markTestSkipped('Platform does not support schemas.');
        }

        try {
            $this->schemaManager->dropSchema('test_create_schema');
        } catch (DatabaseObjectNotFoundException) {
        }

        self::assertNotContains('test_create_schema', $this->schemaManager->listSchemaNames());

        $this->connection->executeStatement(
            $platform->getCreateSchemaSQL('test_create_schema'),
        );

        self::assertContains('test_create_schema', $this->schemaManager->listSchemaNames());
    }

    public function testListTables(): void
    {
        $this->createTestTable('list_tables_test');
        $tables = $this->schemaManager->listTables();

        $table = $this->findTableByName($tables, 'list_tables_test');
        self::assertNotNull($table);

        self::assertTrue($table->hasColumn('id'));
        self::assertTrue($table->hasColumn('test'));
        self::assertTrue($table->hasColumn('foreign_key_test'));
    }

    public function testListTablesDoesNotIncludeViews(): void
    {
        $this->createTestTable('test_table_for_view');

        $sql = 'SELECT * FROM test_table_for_view';

        $view = new View('test_view', $sql);
        $this->schemaManager->createView($view);

        $tables = $this->schemaManager->listTables();
        $view   = $this->findTableByName($tables, 'test_view');
        self::assertNull($view);
    }

    #[DataProvider('tableFilterProvider')]
    public function testListTablesWithFilter(string $prefix, int $expectedCount): void
    {
        $this->createTestTable('filter_test_1');
        $this->createTestTable('filter_test_2');

        $this->markConnectionNotReusable();

        $this->connection->getConfiguration()->setSchemaAssetsFilter(
            static function (string $name) use ($prefix): bool {
                return str_starts_with(strtolower($name), $prefix);
            },
        );

        self::assertCount($expectedCount, $this->schemaManager->listTableNames());
        self::assertCount($expectedCount, $this->schemaManager->listTables());
    }

    /** @return iterable<string, array{string, int}> */
    public static function tableFilterProvider(): iterable
    {
        yield 'One table' => ['filter_test_1', 1];
        yield 'Two tables' => ['filter_test_', 2];
    }

    public function testRenameTable(): void
    {
        $this->createTestTable('old_name');
        $this->schemaManager->renameTable('old_name', 'new_name');

        self::assertFalse($this->schemaManager->tablesExist(['old_name']));
        self::assertTrue($this->schemaManager->tablesExist(['new_name']));
    }

    public function createListTableColumns(): Table
    {
        $table = new Table('list_table_columns', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('test')
                ->setTypeName(Types::STRING)
                ->setLength(255)
                ->setNotNull(false)
                ->setDefaultValue('expected default')
                ->create(),
            Column::editor()
                ->setUnquotedName('foo')
                ->setTypeName(Types::TEXT)
                ->create(),
            Column::editor()
                ->setUnquotedName('bar')
                ->setTypeName(Types::DECIMAL)
                ->setPrecision(10)
                ->setScale(4)
                ->setNotNull(false)
                ->create(),
            Column::editor()
                ->setUnquotedName('baz1')
                ->setTypeName(Types::DATETIME_MUTABLE)
                ->create(),
            Column::editor()
                ->setUnquotedName('baz2')
                ->setTypeName(Types::TIME_MUTABLE)
                ->create(),
            Column::editor()
                ->setUnquotedName('baz3')
                ->setTypeName(Types::DATE_MUTABLE)
                ->create(),
        ]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );

        return $table;
    }

    public function testListTableColumns(): void
    {
        $table = $this->createListTableColumns();

        $this->dropAndCreateTable($table);

        $columns     = $this->schemaManager->listTableColumns('list_table_columns');
        $columnsKeys = array_keys($columns);

        self::assertArrayHasKey('id', $columns);
        self::assertEquals(0, array_search('id', $columnsKeys, true));
        self::assertEquals('id', strtolower($columns['id']->getName()));
        self::assertInstanceOf(IntegerType::class, $columns['id']->getType());
        self::assertEquals(false, $columns['id']->getUnsigned());
        self::assertEquals(true, $columns['id']->getNotnull());
        self::assertEquals(null, $columns['id']->getDefault());

        self::assertArrayHasKey('test', $columns);
        self::assertEquals(1, array_search('test', $columnsKeys, true));
        self::assertEquals('test', strtolower($columns['test']->getName()));
        self::assertInstanceOf(StringType::class, $columns['test']->getType());
        self::assertEquals(255, $columns['test']->getLength());
        self::assertEquals(false, $columns['test']->getFixed());
        self::assertEquals(false, $columns['test']->getNotnull());
        self::assertEquals('expected default', $columns['test']->getDefault());

        self::assertEquals('foo', strtolower($columns['foo']->getName()));
        self::assertEquals(2, array_search('foo', $columnsKeys, true));
        self::assertInstanceOf(TextType::class, $columns['foo']->getType());
        self::assertEquals(false, $columns['foo']->getUnsigned());
        self::assertEquals(false, $columns['foo']->getFixed());
        self::assertEquals(true, $columns['foo']->getNotnull());
        self::assertEquals(null, $columns['foo']->getDefault());

        self::assertEquals('bar', strtolower($columns['bar']->getName()));
        self::assertEquals(3, array_search('bar', $columnsKeys, true));
        self::assertInstanceOf(DecimalType::class, $columns['bar']->getType());
        self::assertEquals(null, $columns['bar']->getLength());
        self::assertEquals(10, $columns['bar']->getPrecision());
        self::assertEquals(4, $columns['bar']->getScale());
        self::assertEquals(false, $columns['bar']->getUnsigned());
        self::assertEquals(false, $columns['bar']->getFixed());
        self::assertEquals(false, $columns['bar']->getNotnull());
        self::assertEquals(null, $columns['bar']->getDefault());

        self::assertEquals('baz1', strtolower($columns['baz1']->getName()));
        self::assertEquals(4, array_search('baz1', $columnsKeys, true));
        self::assertInstanceOf(DateTimeType::class, $columns['baz1']->getType());
        self::assertEquals(true, $columns['baz1']->getNotnull());
        self::assertEquals(null, $columns['baz1']->getDefault());

        self::assertEquals('baz2', strtolower($columns['baz2']->getName()));
        self::assertEquals(5, array_search('baz2', $columnsKeys, true));
        self::assertContains(
            $columns['baz2']->getType()::class,
            [TimeType::class, DateType::class, DateTimeType::class],
        );
        self::assertEquals(true, $columns['baz2']->getNotnull());
        self::assertEquals(null, $columns['baz2']->getDefault());

        self::assertEquals('baz3', strtolower($columns['baz3']->getName()));
        self::assertEquals(6, array_search('baz3', $columnsKeys, true));
        self::assertContains(
            $columns['baz3']->getType()::class,
            [TimeType::class, DateType::class, DateTimeType::class],
        );
        self::assertEquals(true, $columns['baz3']->getNotnull());
        self::assertEquals(null, $columns['baz3']->getDefault());
    }

    public function testListTableColumnsWithFixedStringColumn(): void
    {
        $tableName = 'test_list_table_fixed_string';

        $table = new Table($tableName, [
            Column::editor()
                ->setUnquotedName('column_char')
                ->setTypeName(Types::STRING)
                ->setFixed(true)
                ->setLength(2)
                ->create(),
        ]);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertArrayHasKey('column_char', $columns);
        self::assertInstanceOf(StringType::class, $columns['column_char']->getType());
        self::assertTrue($columns['column_char']->getFixed());
        self::assertSame(2, $columns['column_char']->getLength());
    }

    public function testDiffListTableColumns(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof OraclePlatform) {
            self::markTestSkipped(
                'Does not work with Oracle,'
                . ' since it cannot detect DateTime, Date and Time differences (at the moment).',
            );
        }

        $offlineTable = $this->createListTableColumns();
        $this->dropAndCreateTable($offlineTable);
        $onlineTable = $this->schemaManager->introspectTable('list_table_columns');

        self::assertTrue(
            $this->schemaManager->createComparator()
                ->compareTables($onlineTable, $offlineTable)
                ->isEmpty(),
        );
    }

    public function testListTableIndexes(): void
    {
        $table = $this->getTestCompositeTable('list_table_indexes_test');
        $table->addUniqueIndex(['test'], 'test_index_name');
        $table->addIndex(['id', 'test'], 'test_composite_idx');

        $this->dropAndCreateTable($table);

        $this->assertIndexListEquals([
            Index::editor()
                ->setUnquotedName('test_index_name')
                ->setUnquotedColumnNames('test')
                ->setType(IndexType::UNIQUE)
                ->create(),
            Index::editor()
                ->setUnquotedName('test_composite_idx')
                ->setUnquotedColumnNames('id', 'test')
                ->create(),
        ], $this->schemaManager->listTableIndexes('list_table_indexes_test'));
    }

    public function testDropAndCreateIndex(): void
    {
        $table = $this->getTestTable('test_create_index');
        $table->addUniqueIndex(['test'], 'test');
        $this->dropAndCreateTable($table);

        $index = $table->getIndex('test');
        $this->schemaManager->dropIndex($index->getName(), $table->getName());
        $this->schemaManager->createIndex($index, $table->getName());

        $this->assertIndexListEquals([
            Index::editor()
                ->setUnquotedName('test')
                ->setUnquotedColumnNames('test')
                ->setType(IndexType::UNIQUE)
                ->create(),
        ], $this->schemaManager->listTableIndexes('test_create_index'));
    }

    public function testDropAndCreateUniqueConstraint(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::markTestSkipped('SQLite does not support adding constraints to a table');
        }

        $table = new Table('test_unique_constraint', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $this->dropAndCreateTable($table);

        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedName('uniq_id')
            ->setUnquotedColumnNames('id')
            ->create();

        $this->schemaManager->createUniqueConstraint($uniqueConstraint, $table->getName());

        // there's currently no API for introspecting unique constraints,
        // so introspect the underlying indexes instead
        $this->assertIndexListEquals(
            [
                Index::editor()
                    ->setUnquotedName('uniq_id')
                    ->setUnquotedColumnNames('id')
                    ->setType(IndexType::UNIQUE)
                    ->create(),
            ],
            $this->schemaManager->listTableIndexes('test_unique_constraint'),
        );

        $this->schemaManager->dropUniqueConstraint($uniqueConstraint->getName(), $table->getName());

        $indexes = $this->schemaManager->listTableIndexes('test_unique_constraint');
        self::assertEmpty($indexes);
    }

    public function testSchemaIntrospection(): void
    {
        $this->createTestTable('test_table');

        $schema = $this->schemaManager->introspectSchema();
        self::assertTrue($schema->hasTable('test_table'));
    }

    public function testMigrateSchema(): void
    {
        $this->createTestTable('table_to_alter');
        $this->createTestTable('table_to_drop');

        $schema = $this->schemaManager->introspectSchema();

        $tableToAlter = $schema->getTable('table_to_alter');
        $tableToAlter->dropColumn('foreign_key_test');
        $tableToAlter->addColumn('number', Types::INTEGER);

        $schema->dropTable('table_to_drop');

        $tableToCreate = $schema->createTable('table_to_create');
        $tableToCreate->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $tableToCreate->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );

        $this->schemaManager->migrateSchema($schema);

        $schema = $this->schemaManager->introspectSchema();

        self::assertTrue($schema->hasTable('table_to_alter'));
        self::assertFalse($schema->getTable('table_to_alter')->hasColumn('foreign_key_test'));
        self::assertTrue($schema->getTable('table_to_alter')->hasColumn('number'));
        self::assertFalse($schema->hasTable('table_to_drop'));
        self::assertTrue($schema->hasTable('table_to_create'));
    }

    /** @throws Exception */
    public function testAlterTableScenario(): void
    {
        $this->createTestTable('alter_table');
        $this->createTestTable('alter_table_foreign');

        $table = $this->schemaManager->introspectTable('alter_table');
        self::assertTrue($table->hasColumn('id'));
        self::assertTrue($table->hasColumn('test'));
        self::assertTrue($table->hasColumn('foreign_key_test'));
        self::assertCount(0, $table->getForeignKeys());
        self::assertCount(0, $table->getIndexes());

        $newTable = clone $table;
        $newTable->addColumn('foo', Types::INTEGER);
        $newTable->dropColumn('test');

        $comparator = $this->schemaManager->createComparator();

        $diff = $comparator->compareTables($table, $newTable);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');
        self::assertFalse($table->hasColumn('test'));
        self::assertTrue($table->hasColumn('foo'));

        $newTable = clone $table;
        $newTable->addIndex(['foo'], 'foo_idx');

        $diff = $comparator->compareTables($table, $newTable);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');
        self::assertCount(1, $table->getIndexes());
        self::assertTrue($table->hasIndex('foo_idx'));

        $this->assertIndexEquals(
            Index::editor()
                ->setUnquotedName('foo_idx')
                ->setUnquotedColumnNames('foo')
                ->create(),
            $table->getIndex('foo_idx'),
        );

        $newTable = clone $table;
        $newTable->dropIndex('foo_idx');
        $newTable->addIndex(['foo', 'foreign_key_test'], 'foo_idx');

        $diff = $comparator->compareTables($table, $newTable);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');
        self::assertCount(1, $table->getIndexes());
        self::assertTrue($table->hasIndex('foo_idx'));

        $this->assertIndexEquals(
            Index::editor()
                ->setUnquotedName('foo_idx')
                ->setUnquotedColumnNames(
                    'foo',
                    'foreign_key_test',
                )
                ->create(),
            $table->getIndex('foo_idx'),
        );

        $newTable = clone $table;
        $newTable->dropIndex('foo_idx');
        $newTable->addIndex(['foo', 'foreign_key_test'], 'bar_idx');

        $diff = $comparator->compareTables($table, $newTable);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');
        self::assertCount(1, $table->getIndexes());
        self::assertTrue($table->hasIndex('bar_idx'));
        self::assertFalse($table->hasIndex('foo_idx'));

        $this->assertIndexEquals(
            Index::editor()
                ->setUnquotedName('bar_idx')
                ->setUnquotedColumnNames(
                    'foo',
                    'foreign_key_test',
                )
                ->create(),
            $table->getIndex('bar_idx'),
        );

        $newTable = clone $table;
        $newTable->dropIndex('bar_idx');
        $newTable->addForeignKeyConstraint('alter_table_foreign', ['foreign_key_test'], ['id']);

        $diff = $comparator->compareTables($table, $newTable);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');

        // don't check for index size here, some platforms automatically add indexes for foreign keys.
        self::assertFalse($table->hasIndex('bar_idx'));

        /** @var list<ForeignKeyConstraint> $fks */
        $fks = array_values($table->getForeignKeys());
        self::assertCount(1, $fks);
        $foreignKey = $fks[0];

        $this->assertOptionallyQualifiedNameEquals(
            OptionallyQualifiedName::unquoted('alter_table_foreign'),
            $foreignKey->getReferencedTableName(),
        );

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted('foreign_key_test'),
        ], $foreignKey->getReferencingColumnNames());

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted('id'),
        ], $foreignKey->getReferencedColumnNames());
    }

    public function testTableInNamespace(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform->supportsSchemas()) {
            self::markTestSkipped('Schema definition is not supported by this platform.');
        }

        $diff = new SchemaDiff(['testschema'], [], [], [], [], [], [], []);

        foreach ($platform->getAlterSchemaSQL($diff) as $sql) {
            $this->connection->executeStatement($sql);
        }

        //test if table is create in namespace
        $this->createTestTable('testschema.my_table_in_namespace');
        self::assertContains('testschema.my_table_in_namespace', $this->schemaManager->listTableNames());

        //tables without namespace should be created in default namespace
        //default namespaces are ignored in table listings
        $this->createTestTable('my_table_not_in_namespace');
        self::assertContains('my_table_not_in_namespace', $this->schemaManager->listTableNames());
    }

    public function testCreateAndListViews(): void
    {
        $this->createTestTable('view_test_table');

        $name = 'doctrine_test_view';
        $sql  = 'SELECT * FROM view_test_table';

        $view = new View($name, $sql);

        $this->schemaManager->createView($view);

        $views = $this->schemaManager->listViews();

        $filtered = array_values($this->filterElementsByName($views, $name));
        self::assertCount(1, $filtered);

        self::assertStringContainsString('view_test_table', $filtered[0]->getSql());
    }

    public function testUpdateSchemaWithForeignKeyRenaming(): void
    {
        $table = new Table('test_fk_base', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);

        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );

        $tableFK = new Table('test_fk_rename', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('fk_id')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ], [], [], [], [], $this->schemaManager->createSchemaConfig()->toTableConfiguration());
        $tableFK->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );
        $tableFK->addIndex(['fk_id'], 'fk_idx');
        $tableFK->addForeignKeyConstraint('test_fk_base', ['fk_id'], ['id']);

        $this->dropTableIfExists($tableFK->getName());
        $this->dropTableIfExists($table->getName());

        $this->schemaManager->createTable($table);
        $this->schemaManager->createTable($tableFK);

        $tableFKNew = new Table('test_fk_rename', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('rename_fk_id')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ], [], [], [], [], $this->schemaManager->createSchemaConfig()->toTableConfiguration());
        $tableFKNew->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );
        $tableFKNew->addIndex(['rename_fk_id'], 'fk_idx');
        $tableFKNew->addForeignKeyConstraint('test_fk_base', ['rename_fk_id'], ['id']);

        $diff = $this->schemaManager->createComparator()
            ->compareTables($tableFK, $tableFKNew);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('test_fk_rename');
        self::assertTrue($table->hasColumn('rename_fk_id'));

        /** @var list<ForeignKeyConstraint> $foreignKeys */
        $foreignKeys = array_values($table->getForeignKeys());
        self::assertCount(1, $foreignKeys);
        $foreignKey = $foreignKeys[0];

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted('rename_fk_id'),
        ], $foreignKey->getReferencingColumnNames());
    }

    public function testRenameIndexUsedInForeignKeyConstraint(): void
    {
        $primaryTable = new Table('test_rename_index_primary', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $primaryTable->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );

        $foreignTable = new Table('test_rename_index_foreign', [
            Column::editor()
                ->setUnquotedName('fk')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $foreignTable->addIndex(['fk'], 'rename_index_fk_idx');
        $foreignTable->addForeignKeyConstraint(
            'test_rename_index_primary',
            ['fk'],
            ['id'],
            [],
            'fk_constraint',
        );

        $this->dropTableIfExists($foreignTable->getName());
        $this->dropTableIfExists($primaryTable->getName());

        $this->schemaManager->createTable($primaryTable);
        $this->schemaManager->createTable($foreignTable);

        $foreignTable2 = clone $foreignTable;
        $foreignTable2->renameIndex('rename_index_fk_idx', 'renamed_index_fk_idx');

        $diff = $this->schemaManager->createComparator()
            ->compareTables($foreignTable, $foreignTable2);

        $this->schemaManager->alterTable($diff);

        $foreignTable = $this->schemaManager->introspectTable('test_rename_index_foreign');

        self::assertFalse($foreignTable->hasIndex('rename_index_fk_idx'));
        self::assertTrue($foreignTable->hasIndex('renamed_index_fk_idx'));
        self::assertTrue($foreignTable->hasForeignKey('fk_constraint'));
    }

    public function testChangeColumnsTypeWithDefaultValue(): void
    {
        $oldTable = new Table('column_def_change_type', [
            Column::editor()
                ->setUnquotedName('col_int')
                ->setTypeName('smallint')
                ->setDefaultValue(666)
                ->create(),
            Column::editor()
                ->setUnquotedName('col_string')
                ->setTypeName('string')
                ->setLength(3)
                ->setDefaultValue('foo')
                ->create(),
        ]);

        $this->dropAndCreateTable($oldTable);

        $newTable = clone $oldTable;

        $newTable->getColumn('col_int')
            ->setType(Type::getType(Types::INTEGER));

        $newTable->getColumn('col_string')
            ->setFixed(true);

        $diff = $this->schemaManager->createComparator()
            ->compareTables(
                $this->schemaManager->introspectTable('column_def_change_type'),
                $newTable,
            );

        $this->schemaManager->alterTable($diff);

        $columns = $this->schemaManager->listTableColumns('column_def_change_type');

        self::assertInstanceOf(IntegerType::class, $columns['col_int']->getType());
        self::assertEquals(666, $columns['col_int']->getDefault());

        self::assertInstanceOf(StringType::class, $columns['col_string']->getType());
        self::assertEquals('foo', $columns['col_string']->getDefault());
    }

    public function testListTableWithBlob(): void
    {
        $table = new Table('test_blob_table', [
            Column::editor()
                ->setUnquotedName('binarydata')
                ->setTypeName(Types::BLOB)
                ->create(),
        ]);

        $this->schemaManager->createTable($table);

        $created = $this->schemaManager->introspectTable('test_blob_table');

        self::assertTrue($created->hasColumn('binarydata'));
        self::assertInstanceOf(BlobType::class, $created->getColumn('binarydata')->getType());
    }

    public function testListTableFloatTypeColumns(): void
    {
        $tableName = 'test_float_columns';
        $table     = new Table($tableName, [
            Column::editor()
                ->setUnquotedName('col_float')
                ->setTypeName(Types::FLOAT)
                ->create(),
            Column::editor()
                ->setUnquotedName('col_smallfloat')
                ->setTypeName(Types::SMALLFLOAT)
                ->create(),
        ]);

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertInstanceOf(FloatType::class, $columns['col_float']->getType());
        self::assertInstanceOf(SmallFloatType::class, $columns['col_smallfloat']->getType());
        self::assertFalse($columns['col_float']->getUnsigned());
        self::assertFalse($columns['col_smallfloat']->getUnsigned());
    }

    /** @param mixed[] $data */
    protected function createTestTable(string $name = 'test_table', array $data = []): Table
    {
        $options = $data['options'] ?? [];

        $table = $this->getTestTable($name, $options);

        $this->dropAndCreateTable($table);

        return $table;
    }

    /** @param mixed[] $options */
    protected function getTestTable(string $name, array $options = []): Table
    {
        $table = new Table($name, [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('test')
                ->setTypeName(Types::STRING)
                ->setLength(255)
                ->create(),
            Column::editor()
                ->setUnquotedName('foreign_key_test')
                ->setTypeName(Types::INTEGER)
                ->setNotNull(false)
                ->create(),
        ], [], [], [], $options, $this->schemaManager->createSchemaConfig()->toTableConfiguration());

        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );

        return $table;
    }

    protected function getTestCompositeTable(string $name): Table
    {
        $table = new Table($name, [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('other_id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('test')
                ->setTypeName(Types::STRING)
                ->setLength(255)
                ->create(),
        ], [], [], [], [], $this->schemaManager->createSchemaConfig()->toTableConfiguration());
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id', 'other_id')
                ->create(),
        );

        return $table;
    }

    public function testColumnDefaultLifecycle(): void
    {
        $oldTable = new Table('col_def_lifecycle', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setAutoincrement(true)
                ->create(),
            Column::editor()
                ->setUnquotedName('column1')
                ->setTypeName(Types::STRING)
                ->setLength(1)
                ->create(),
            Column::editor()
                ->setUnquotedName('column2')
                ->setTypeName(Types::STRING)
                ->setLength(1)
                ->setDefaultValue('')
                ->create(),
            Column::editor()
                ->setUnquotedName('column3')
                ->setTypeName(Types::STRING)
                ->setLength(8)
                ->setDefaultValue('default1')
                ->create(),
            Column::editor()
                ->setUnquotedName('column4')
                ->setTypeName(Types::INTEGER)
                ->setDefaultValue(0)
                ->create(),
        ]);
        $oldTable->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );

        $this->dropAndCreateTable($oldTable);

        $oldTable = $this->schemaManager->introspectTable('col_def_lifecycle');

        self::assertNull($oldTable->getColumn('id')->getDefault());
        self::assertNull($oldTable->getColumn('column1')->getDefault());
        self::assertSame('', $oldTable->getColumn('column2')->getDefault());
        self::assertSame('default1', $oldTable->getColumn('column3')->getDefault());
        self::assertSame('0', $oldTable->getColumn('column4')->getDefault());

        $newTable = clone $oldTable;

        $newTable->modifyColumn('column1', ['default' => '']);
        $newTable->modifyColumn('column2', ['default' => null]);
        $newTable->modifyColumn('column3', ['default' => 'default2']);
        $newTable->modifyColumn('column4', ['default' => null]);

        $diff = $this->schemaManager->createComparator()
            ->compareTables(
                $this->schemaManager->introspectTable('col_def_lifecycle'),
                $newTable,
            );

        $this->schemaManager->alterTable($diff);

        $columns = $this->schemaManager->listTableColumns('col_def_lifecycle');

        self::assertSame('', $columns['column1']->getDefault());
        self::assertNull($columns['column2']->getDefault());
        self::assertSame('default2', $columns['column3']->getDefault());
        self::assertNull($columns['column4']->getDefault());
    }

    public function testListTableWithBinary(): void
    {
        $tableName = 'test_binary_table';

        $table = new Table($tableName, [
            Column::editor()
                ->setUnquotedName('column_binary')
                ->setTypeName(Types::BINARY)
                ->setLength(16)
                ->setFixed(true)
                ->create(),
            Column::editor()
                ->setUnquotedName('column_varbinary')
                ->setTypeName(Types::BINARY)
                ->setLength(32)
                ->create(),
        ]);

        $this->schemaManager->createTable($table);

        $table = $this->schemaManager->introspectTable($tableName);
        $this->assertBinaryColumnIsValid($table, 'column_binary', 16);
        $this->assertVarBinaryColumnIsValid($table, 'column_varbinary', 32);
    }

    protected function assertBinaryColumnIsValid(Table $table, string $columnName, int $expectedLength): void
    {
        $column = $table->getColumn($columnName);
        self::assertInstanceOf(BinaryType::class, $column->getType());
        self::assertSame($expectedLength, $column->getLength());
        self::assertTrue($column->getFixed());
    }

    protected function assertVarBinaryColumnIsValid(Table $table, string $columnName, int $expectedLength): void
    {
        $column = $table->getColumn($columnName);
        self::assertInstanceOf(BinaryType::class, $column->getType());
        self::assertSame($expectedLength, $column->getLength());
        self::assertFalse($column->getFixed());
    }

    public function testGetNonExistingTable(): void
    {
        $this->expectException(SchemaException::class);
        $this->schemaManager->introspectTable('non_existing');
    }

    public function testListTableDetailsWithFullQualifiedTableName(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSchemas()) {
            self::markTestSkipped('Test only works on platforms that support schemas.');
        }

        $schemaConfig = $this->schemaManager->createSchemaConfig();

        $defaultSchemaName = $schemaConfig->getName();

        self::assertNotNull($defaultSchemaName);

        $primaryTableName = 'primary_table';
        $foreignTableName = 'foreign_table';

        $table = new Table($foreignTableName, [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setAutoincrement(true)
                ->create(),
        ]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );

        $this->dropAndCreateTable($table);

        $table = new Table($primaryTableName, [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setAutoincrement(true)
                ->create(),
            Column::editor()
                ->setUnquotedName('foo')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('bar')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->create(),
        ]);
        $table->addForeignKeyConstraint($foreignTableName, ['foo'], ['id']);
        $table->addIndex(['bar']);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );

        $this->dropAndCreateTable($table);

        self::assertEquals(
            $this->schemaManager->listTableColumns($primaryTableName),
            $this->schemaManager->listTableColumns($defaultSchemaName . '.' . $primaryTableName),
        );
        self::assertEquals(
            $this->schemaManager->listTableIndexes($primaryTableName),
            $this->schemaManager->listTableIndexes($defaultSchemaName . '.' . $primaryTableName),
        );
        self::assertEquals(
            $this->schemaManager->listTableForeignKeys($primaryTableName),
            $this->schemaManager->listTableForeignKeys($defaultSchemaName . '.' . $primaryTableName),
        );
    }

    public function testDoesNotListIndexesImplicitlyCreatedByForeignKeys(): void
    {
        $primaryTable = new Table('test_list_index_impl_primary', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $primaryTable->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );

        $foreignTable = new Table('test_list_index_impl_foreign', [
            Column::editor()
                ->setUnquotedName('fk1')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('fk2')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $foreignTable->addIndex(['fk1'], 'explicit_fk1_idx');
        $foreignTable->addForeignKeyConstraint('test_list_index_impl_primary', ['fk1'], ['id']);
        $foreignTable->addForeignKeyConstraint('test_list_index_impl_primary', ['fk2'], ['id']);

        $this->dropAndCreateTable($primaryTable);
        $this->dropAndCreateTable($foreignTable);

        $indexes = $this->schemaManager->listTableIndexes('test_list_index_impl_foreign');

        self::assertCount(2, $indexes);
        self::assertArrayHasKey('explicit_fk1_idx', $indexes);
        self::assertArrayHasKey('idx_3d6c147fdc58d6c', $indexes);
    }

    public function testCreateAndListSequences(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSequences()) {
            self::markTestSkipped('This test is only supported on platforms that support sequences.');
        }

        $sequence1Name           = 'sequence_1';
        $sequence1AllocationSize = 1;
        $sequence1InitialValue   = 2;
        $sequence2Name           = 'sequence_2';
        $sequence2AllocationSize = 3;
        $sequence2InitialValue   = 4;
        $sequence1               = new Sequence($sequence1Name, $sequence1AllocationSize, $sequence1InitialValue);
        $sequence2               = new Sequence($sequence2Name, $sequence2AllocationSize, $sequence2InitialValue);

        $this->schemaManager->createSequence($sequence1);
        $this->schemaManager->createSequence($sequence2);

        /** @var Sequence[] $actualSequences */
        $actualSequences = [];
        foreach ($this->schemaManager->listSequences() as $sequence) {
            $actualSequences[$sequence->getName()] = $sequence;
        }

        $actualSequence1 = $actualSequences[$sequence1Name];
        $actualSequence2 = $actualSequences[$sequence2Name];

        self::assertSame($sequence1Name, $actualSequence1->getName());
        self::assertEquals($sequence1AllocationSize, $actualSequence1->getAllocationSize());
        self::assertEquals($sequence1InitialValue, $actualSequence1->getInitialValue());

        self::assertSame($sequence2Name, $actualSequence2->getName());
        self::assertEquals($sequence2AllocationSize, $actualSequence2->getAllocationSize());
        self::assertEquals($sequence2InitialValue, $actualSequence2->getInitialValue());
    }

    public function testComparisonWithAutoDetectedSequenceDefinition(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform->supportsSequences()) {
            self::markTestSkipped('This test is only supported on platforms that support sequences.');
        }

        $sequenceName           = 'sequence_auto_detect_test';
        $sequenceAllocationSize = 5;
        $sequenceInitialValue   = 10;
        $sequence               = new Sequence($sequenceName, $sequenceAllocationSize, $sequenceInitialValue);

        try {
            $this->schemaManager->dropSequence($sequence->getName());
        } catch (DatabaseObjectNotFoundException) {
        }

        $this->schemaManager->createSequence($sequence);

        $createdSequence = array_values(
            array_filter(
                $this->schemaManager->listSequences(),
                static function (Sequence $sequence) use ($sequenceName): bool {
                    return strcasecmp($sequence->getName(), $sequenceName) === 0;
                },
            ),
        )[0] ?? null;

        self::assertNotNull($createdSequence);

        $tableDiff = $this->schemaManager->createComparator()
            ->diffSequence($createdSequence, $sequence);

        self::assertFalse($tableDiff);
    }

    public function testPrimaryKeyAutoIncrement(): void
    {
        $table = new Table('test_pk_auto_increment', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setAutoincrement(true)
                ->create(),
            Column::editor()
                ->setUnquotedName('text')
                ->setTypeName(Types::STRING)
                ->setLength(1)
                ->create(),
        ]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );
        $this->dropAndCreateTable($table);

        $this->connection->insert('test_pk_auto_increment', ['text' => '1']);

        $lastUsedIdBeforeDelete = (int) $this->connection->fetchOne(
            "SELECT id FROM test_pk_auto_increment WHERE text = '1'",
        );

        $this->connection->executeStatement('DELETE FROM test_pk_auto_increment');

        $this->connection->insert('test_pk_auto_increment', ['text' => '2']);

        $lastUsedIdAfterDelete = (int) $this->connection->fetchOne(
            "SELECT id FROM test_pk_auto_increment WHERE text = '2'",
        );

        self::assertGreaterThan($lastUsedIdBeforeDelete, $lastUsedIdAfterDelete);
    }

    public function testGenerateAnIndexWithPartialColumnLength(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped(
                'This test is only supported on platforms that support indexes with column length definitions.',
            );
        }

        $table = new Table('test_partial_column_index', [
            Column::editor()
                ->setUnquotedName('long_column')
                ->setTypeName(Types::STRING)
                ->setLength(40)
                ->create(),
            Column::editor()
                ->setUnquotedName('standard_column')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $table->addIndex(['long_column'], 'partial_long_column_idx', [], ['lengths' => [4]]);
        $table->addIndex(['standard_column', 'long_column'], 'standard_and_partial_idx', [], ['lengths' => [null, 2]]);

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTable('test_partial_column_index');
        $this->assertIndexListEquals($table->getIndexes(), $onlineTable->getIndexes());
    }

    public function testCommentInTable(): void
    {
        $table = new Table('table_with_comment', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $table->setComment('\'\\ Foo with control characters \'\\');
        $this->dropAndCreateTable($table);

        $table = $this->schemaManager->introspectTable('table_with_comment');
        self::assertSame('\'\\ Foo with control characters \'\\', $table->getComment());
    }

    public function testIntrospectReservedKeywordTableViaListTableDetails(): void
    {
        $this->createReservedKeywordTables();

        $user = $this->schemaManager->introspectTable('user');
        self::assertCount(2, $user->getColumns());
        self::assertCount(1, $user->getIndexes());
        self::assertCount(1, $user->getForeignKeys());
    }

    public function testIntrospectReservedKeywordTableViaListTables(): void
    {
        $this->createReservedKeywordTables();

        $tables = $this->schemaManager->listTables();

        $user = $this->findTableByName($tables, 'user');
        self::assertNotNull($user);
        self::assertCount(2, $user->getColumns());
        self::assertCount(1, $user->getIndexes());
        self::assertCount(1, $user->getForeignKeys());
    }

    private function createReservedKeywordTables(): void
    {
        $user = new Table('user');
        $user->addColumn('id', Types::INTEGER);
        $user->addColumn('group_id', Types::INTEGER);
        $user->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );
        $user->addForeignKeyConstraint('group', ['group_id'], ['id']);

        $group = new Table('group');
        $group->addColumn('id', Types::INTEGER);
        $group->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );

        $platform = $this->connection->getDatabasePlatform();

        $this->dropTableIfExists($user->getObjectName()->toSQL($platform));
        $this->dropTableIfExists($group->getObjectName()->toSQL($platform));

        $schema = new Schema([$user, $group]);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createSchemaObjects($schema);
    }

    /** @throws Exception */
    public function testQuotedIdentifiers(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof DB2Platform) {
            self::markTestIncomplete(
                'Introspection of lower-case identifiers as quoted is currently not implemented on IBM DB2.',
            );
        }

        $artists = new Table('"Artists"', [
            Column::editor()
                ->setQuotedName('Id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setQuotedName('Name')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $artists->addIndex(['"Name"'], '"Idx_Artist_Name"');
        $artists->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setQuotedColumnNames('Id')
                ->create(),
        );
        $artists->setComment('"Artists" table');

        $tracks = new Table('"Tracks"', [
            Column::editor()
                ->setQuotedName('Id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setQuotedName('Artist_Id')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $tracks->addIndex(['"Artist_Id"'], '"Idx_Artist_Id"');
        $tracks->addForeignKeyConstraint(
            '"Artists"',
            ['"Artist_Id"'],
            ['"Id"'],
            [],
            '"Artists_Fk"',
        );
        $tracks->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setQuotedColumnNames('Id')
                ->create(),
        );
        $tracks->setComment('"Tracks" table');

        $this->dropTableIfExists($tracks->getObjectName()->toSQL($platform));
        $this->dropTableIfExists($artists->getObjectName()->toSQL($platform));

        $this->schemaManager->createTable($artists);
        $this->schemaManager->createTable($tracks);

        $artists = $this->schemaManager->introspectTable('"Artists"');
        $tracks  = $this->schemaManager->introspectTable('"Tracks"');

        // Primary table assertions
        $this->assertOptionallyQualifiedNameEquals(
            OptionallyQualifiedName::quoted('Artists'),
            $artists->getObjectName(),
        );

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::quoted('Id'),
            $artists->getColumn('"Id"')->getObjectName(),
        );

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::quoted('Name'),
            $artists->getColumn('"Name"')->getObjectName(),
        );

        $this->assertIndexListEquals([
            Index::editor()
                ->setQuotedName('Idx_Artist_Name')
                ->setQuotedColumnNames('Name')
                ->create(),
        ], $artists->getIndexes());

        $primaryKey = $artists->getPrimaryKeyConstraint();
        self::assertNotNull($primaryKey);
        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::quoted('Id'),
        ], $primaryKey->getColumnNames());

        self::assertSame('"Artists" table', $artists->getComment());

        // Foreign table assertions
        self::assertUnqualifiedNameEquals(
            UnqualifiedName::quoted('Id'),
            $tracks->getColumn('"Id"')->getObjectName(),
        );

        $primaryKey = $tracks->getPrimaryKeyConstraint();
        self::assertNotNull($primaryKey);
        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::quoted('Id'),
        ], $primaryKey->getColumnNames());

        self::assertUnqualifiedNameEquals(
            UnqualifiedName::quoted('Artist_Id'),
            $tracks->getColumn('"Artist_Id"')->getObjectName(),
        );

        self::assertTrue($tracks->hasIndex('"Idx_Artist_Id"'));
        $this->assertIndexedColumnListEquals([
            new IndexedColumn(UnqualifiedName::quoted('Artist_Id'), null),
        ], $tracks->getIndex('"Idx_Artist_Id"')->getIndexedColumns());

        $constraint = $tracks->getForeignKey('"Artists_Fk"');

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::quoted('Artist_Id'),
        ], $constraint->getReferencingColumnNames());

        self::assertOptionallyQualifiedNameEquals(
            OptionallyQualifiedName::quoted('Artists'),
            $constraint->getReferencedTableName(),
        );

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::quoted('Id'),
        ], $constraint->getReferencedColumnNames());

        self::assertSame('"Tracks" table', $tracks->getComment());
    }

    public function testChangeIndexWithForeignKeys(): void
    {
        $this->dropTableIfExists('child');
        $this->dropTableIfExists('parent');

        $parent = new Table('parent');
        $parent->addColumn('id', Types::INTEGER);
        $parent->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );

        $child = new Table('child');
        $child->addColumn('id', Types::INTEGER);
        $child->addColumn('parent_id', Types::INTEGER);
        $child->addIndex(['parent_id'], 'idx_1');
        $child->addForeignKeyConstraint('parent', ['parent_id'], ['id']);

        $schema = new Schema([$parent, $child]);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createSchemaObjects($schema);

        $child->dropIndex('idx_1');
        $child->addIndex(['parent_id'], 'idx_2');

        $diff = $schemaManager->createComparator(
            (new ComparatorConfig())->withDetectRenamedIndexes(false),
        )->compareTables(
            $schemaManager->introspectTable('child'),
            $child,
        );

        $schemaManager->alterTable($diff);

        $child = $schemaManager->introspectTable('child');

        self::assertFalse($child->hasIndex('idx_1'));
        self::assertTrue($child->hasIndex('idx_2'));
    }

    public function testSwitchPrimaryKeyOrder(): void
    {
        $prototype = new Table('test_switch_pk_order', [
            Column::editor()
                ->setUnquotedName('foo_id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('bar_id')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);

        $oldPrimaryKeyConstraint = PrimaryKeyConstraint::editor()
            ->setUnquotedColumnNames('foo_id', 'bar_id')
            ->create();

        $table = $prototype->edit()
            ->setPrimaryKeyConstraint($oldPrimaryKeyConstraint)
            ->create();
        $this->dropAndCreateTable($table);

        $newPrimaryKeyConstraint = PrimaryKeyConstraint::editor()
            ->setUnquotedColumnNames('bar_id', 'foo_id')
            ->create();

        $table = $prototype->edit()
            ->setPrimaryKeyConstraint($newPrimaryKeyConstraint)
            ->create();

        $schemaManager = $this->connection->createSchemaManager();

        $diff = $schemaManager->createComparator()->compareTables(
            $schemaManager->introspectTable('test_switch_pk_order'),
            $table,
        );
        self::assertFalse($diff->isEmpty());
        $schemaManager->alterTable($diff);

        $table = $schemaManager->introspectTable('test_switch_pk_order');

        $this->assertPrimaryKeyConstraintEquals($newPrimaryKeyConstraint, $table->getPrimaryKeyConstraint());
    }

    public function testDropColumnWithDefault(): void
    {
        $table = new Table('drop_column_with_default', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('todrop')
                ->setTypeName(Types::INTEGER)
                ->setDefaultValue(10)
                ->create(),
        ]);

        $this->dropAndCreateTable($table);

        $table->dropColumn('todrop');

        $diff = $this->schemaManager->createComparator()
            ->compareTables(
                $this->schemaManager->introspectTable('drop_column_with_default'),
                $table,
            );

        $this->schemaManager->alterTable($diff);

        $columns = $this->schemaManager->listTableColumns('drop_column_with_default');
        self::assertCount(1, $columns);
    }

    /** @param list<Table> $tables */
    protected function findTableByName(array $tables, string $name): ?Table
    {
        foreach ($tables as $table) {
            if (strtolower($table->getName()) === $name) {
                return $table;
            }
        }

        return null;
    }

    /** @throws Exception */
    public function testDefaultSchemaName(): void
    {
        self::assertSame(
            $this->getExpectedDefaultSchemaName(),
            $this->schemaManager->createSchemaConfig()->getName(),
        );
    }

    abstract public function getExpectedDefaultSchemaName(): ?string;

    public function testTableWithSchema(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSchemas()) {
            self::markTestSkipped('The currently used database platform does not support schemas.');
        }

        $this->connection->executeStatement('CREATE SCHEMA nested');

        $primaryKeyConstraint = PrimaryKeyConstraint::editor()
            ->setUnquotedColumnNames('id')
            ->create();

        $nestedRelatedTable = new Table('nested.schemarelated', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setAutoincrement(true)
                ->create(),
        ]);
        $nestedRelatedTable->addPrimaryKeyConstraint($primaryKeyConstraint);

        $nestedSchemaTable = new Table('nested.schematable', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setAutoincrement(true)
                ->create(),
        ]);
        $nestedSchemaTable->addPrimaryKeyConstraint($primaryKeyConstraint);
        $nestedSchemaTable->addForeignKeyConstraint($nestedRelatedTable->getName(), ['id'], ['id']);
        $nestedSchemaTable->setComment('This is a comment');

        $this->schemaManager->createTable($nestedRelatedTable);
        $this->schemaManager->createTable($nestedSchemaTable);

        $tableNames = $this->schemaManager->listTableNames();
        self::assertContains('nested.schematable', $tableNames);

        $tables = $this->schemaManager->listTables();
        self::assertNotNull($this->findTableByName($tables, 'nested.schematable'));

        $nestedSchemaTable = $this->schemaManager->introspectTable('nested.schematable');
        self::assertTrue($nestedSchemaTable->hasColumn('id'));

        $this->assertPrimaryKeyConstraintEquals($primaryKeyConstraint, $nestedSchemaTable->getPrimaryKeyConstraint());

        $relatedFks = array_values($nestedSchemaTable->getForeignKeys());
        self::assertCount(1, $relatedFks);
        $relatedFk = $relatedFks[0];

        self::assertOptionallyQualifiedNameEquals(
            OptionallyQualifiedName::unquoted('schemarelated', 'nested'),
            $relatedFk->getReferencedTableName(),
        );

        self::assertEquals('This is a comment', $nestedSchemaTable->getComment());
    }
}
