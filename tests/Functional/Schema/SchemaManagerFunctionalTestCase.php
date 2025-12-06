<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\AbstractNamedObject;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
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
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_keys;
use function array_map;
use function array_values;
use function get_debug_type;
use function sprintf;
use function str_starts_with;
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

        $this->schemaManager->createSequence(
            Sequence::editor()
                ->setUnquotedName($name)
                ->create(),
        );

        self::assertNotNull($this->findObjectByShortestName($this->schemaManager->introspectSequences(), $name));
    }

    /**
     * @param list<T> $objects
     *
     * @return ?T
     *
     * @template T of AbstractNamedObject<OptionallyQualifiedName>
     */
    protected function findObjectByName(array $objects, string $name): ?AbstractNamedObject
    {
        foreach ($objects as $object) {
            if (strtolower($object->getName()) === $name) {
                return $object;
            }
        }

        return null;
    }

    /**
     * @param list<T> $objects
     *
     * @return ?T
     *
     * @template T of AbstractNamedObject<OptionallyQualifiedName>
     */
    protected function findObjectByShortestName(array $objects, string $name): ?AbstractNamedObject
    {
        foreach ($objects as $object) {
            if ($object->getShortestName($object->getNamespaceName()) === $name) {
                return $object;
            }
        }

        return null;
    }

    public function testListSequences(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform->supportsSequences()) {
            self::markTestSkipped('The platform does not support sequences.');
        }

        $this->schemaManager->createSequence(
            Sequence::editor()
                ->setUnquotedName('list_sequences_test_seq')
                ->setAllocationSize(20)
                ->setInitialValue(10)
                ->create(),
        );

        $createdSequence = $this->findObjectByShortestName(
            $this->schemaManager->introspectSequences(),
            'list_sequences_test_seq',
        );

        self::assertNotNull($createdSequence);
        self::assertSame(20, $createdSequence->getAllocationSize());
        self::assertSame(10, $createdSequence->getInitialValue());
    }

    public function testIntrospectDatabaseNames(): void
    {
        try {
            $this->schemaManager->dropDatabase('test_create_database');
        } catch (DatabaseObjectNotFoundException) {
        }

        $this->assertUnqualifiedNameListNotContainsUnquotedName(
            'test_create_database',
            $this->schemaManager->introspectDatabaseNames(),
        );

        $this->schemaManager->createDatabase('test_create_database');

        $this->assertUnqualifiedNameListContainsUnquotedName(
            'test_create_database',
            $this->schemaManager->introspectDatabaseNames(),
        );
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

        $this->assertUnqualifiedNameListNotContainsUnquotedName(
            'test_create_schema',
            $this->schemaManager->introspectSchemaNames(),
        );

        $this->connection->executeStatement(
            $platform->getCreateSchemaSQL('test_create_schema'),
        );

        $this->assertUnqualifiedNameListContainsUnquotedName(
            'test_create_schema',
            $this->schemaManager->introspectSchemaNames(),
        );
    }

    public function testListTables(): void
    {
        $this->createTestTable('list_tables_test');
        $tables = $this->schemaManager->listTables();

        $table = $this->findObjectByShortestName($tables, 'list_tables_test');
        self::assertNotNull($table);

        self::assertTrue($table->hasColumn('id'));
        self::assertTrue($table->hasColumn('test'));
        self::assertTrue($table->hasColumn('foreign_key_test'));
    }

    public function testListTablesDoesNotIncludeViews(): void
    {
        $this->createTestTable('test_table_for_view');

        $sql = 'SELECT * FROM test_table_for_view';

        $view = View::editor()
            ->setUnquotedName('test_view')
            ->setSQL($sql)
            ->create();

        $this->schemaManager->createView($view);

        $tables = $this->schemaManager->listTables();
        $view   = $this->findObjectByShortestName($tables, 'test_view');
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

        self::assertCount($expectedCount, $this->schemaManager->introspectTableNames());
        self::assertCount($expectedCount, $this->schemaManager->introspectTables());
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
        return Table::editor()
            ->setUnquotedName('list_table_columns')
            ->setColumns(
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
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();
    }

    public function testListTableColumns(): void
    {
        $table = $this->createListTableColumns();

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->introspectTableColumnsByUnquotedName('list_table_columns');

        self::assertCount(7, $columns);

        [$id, $test, $foo, $bar, $baz1, $baz2, $baz3] = $columns;

        $columnsKeys = array_keys($columns);

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::unquoted('id'),
            $id->getObjectName(),
        );
        self::assertInstanceOf(IntegerType::class, $id->getType());
        self::assertFalse($id->getUnsigned());
        self::assertTrue($id->getNotnull());
        self::assertNull($id->getDefault());

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::unquoted('test'),
            $test->getObjectName(),
        );
        self::assertInstanceOf(StringType::class, $test->getType());
        self::assertEquals(255, $test->getLength());
        self::assertFalse($test->getFixed());
        self::assertFalse($test->getNotnull());
        self::assertEquals('expected default', $test->getDefault());

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::unquoted('foo'),
            $foo->getObjectName(),
        );
        self::assertInstanceOf(TextType::class, $foo->getType());
        self::assertFalse($foo->getUnsigned());
        self::assertFalse($foo->getFixed());
        self::assertTrue($foo->getNotnull());
        self::assertNull($foo->getDefault());

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::unquoted('bar'),
            $bar->getObjectName(),
        );
        self::assertInstanceOf(DecimalType::class, $bar->getType());
        self::assertNull($bar->getLength());
        self::assertEquals(10, $bar->getPrecision());
        self::assertEquals(4, $bar->getScale());
        self::assertFalse($bar->getUnsigned());
        self::assertFalse($bar->getFixed());
        self::assertFalse($bar->getNotnull());
        self::assertNull($bar->getDefault());

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::unquoted('baz1'),
            $baz1->getObjectName(),
        );
        self::assertInstanceOf(DateTimeType::class, $baz1->getType());
        self::assertTrue($baz1->getNotnull());
        self::assertNull($baz1->getDefault());

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::unquoted('baz2'),
            $baz2->getObjectName(),
        );
        self::assertContains(
            $baz2->getType()::class,
            [TimeType::class, DateType::class, DateTimeType::class],
        );
        self::assertTrue($baz2->getNotnull());
        self::assertNull($baz2->getDefault());

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::unquoted('baz3'),
            $baz3->getObjectName(),
        );
        self::assertContains(
            $baz3->getType()::class,
            [TimeType::class, DateType::class, DateTimeType::class],
        );
        self::assertTrue($baz3->getNotnull());
        self::assertNull($baz3->getDefault());
    }

    public function testListTableColumnsWithFixedStringColumn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_list_table_fixed_string')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('column_char')
                    ->setTypeName(Types::STRING)
                    ->setFixed(true)
                    ->setLength(2)
                    ->create(),
            )
            ->create();

        $this->schemaManager->createTable($table);

        [$column] = $this->schemaManager->introspectTableColumnsByUnquotedName('test_list_table_fixed_string');

        self::assertInstanceOf(StringType::class, $column->getType());
        self::assertTrue($column->getFixed());
        self::assertSame(2, $column->getLength());
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
        $onlineTable = $this->schemaManager->introspectTableByUnquotedName('list_table_columns');

        self::assertTrue(
            $this->schemaManager->createComparator()
                ->compareTables($onlineTable, $offlineTable)
                ->isEmpty(),
        );
    }

    public function testListTableIndexes(): void
    {
        $table = $this->getTestCompositeTable('list_table_indexes_test')
            ->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('test_index_name')
                    ->setUnquotedColumnNames('test')
                    ->setType(IndexType::UNIQUE)
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('test_composite_idx')
                    ->setUnquotedColumnNames('id', 'test')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $tableIndexes = $this->schemaManager->listTableIndexes('list_table_indexes_test');

        self::assertCount(3, $tableIndexes);

        self::assertArrayHasKey('primary', $tableIndexes, 'listTableIndexes() has to return a "primary" array key.');
        self::assertEquals(['id', 'other_id'], array_map('strtolower', $tableIndexes['primary']->getColumns()));
        self::assertTrue($tableIndexes['primary']->isUnique());
        self::assertTrue($tableIndexes['primary']->isPrimary());

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::unquoted('test_index_name'),
            $tableIndexes['test_index_name']->getObjectName(),
        );
        self::assertEquals(['test'], array_map('strtolower', $tableIndexes['test_index_name']->getColumns()));
        self::assertTrue($tableIndexes['test_index_name']->isUnique());
        self::assertFalse($tableIndexes['test_index_name']->isPrimary());

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::unquoted('test_composite_idx'),
            $tableIndexes['test_composite_idx']->getObjectName(),
        );
        self::assertEquals(['id', 'test'], array_map('strtolower', $tableIndexes['test_composite_idx']->getColumns()));
        self::assertFalse($tableIndexes['test_composite_idx']->isUnique());
        self::assertFalse($tableIndexes['test_composite_idx']->isPrimary());
    }

    public function testDropAndCreateIndex(): void
    {
        $table = $this->getTestTable('test_create_index')
            ->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('test')
                    ->setUnquotedColumnNames('test')
                    ->setType(IndexType::UNIQUE)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $platform = $this->connection->getDatabasePlatform();

        $index = $table->getIndex('test');
        $this->schemaManager->dropIndex(
            $index->getObjectName()->toSQL($platform),
            $table->getObjectName()->toSQL($platform),
        );

        $this->schemaManager->createIndex(
            $index,
            $table->getObjectName()->toSQL($platform),
        );
        $tableIndexes = $this->schemaManager->listTableIndexes('test_create_index');

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::unquoted('test'),
            $tableIndexes['test']->getObjectName(),
        );
        self::assertEquals(['test'], array_map('strtolower', $tableIndexes['test']->getColumns()));
        self::assertTrue($tableIndexes['test']->isUnique());
        self::assertFalse($tableIndexes['test']->isPrimary());
    }

    public function testDropAndCreateUniqueConstraint(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::markTestSkipped('SQLite does not support adding constraints to a table');
        }

        $table = Table::editor()
            ->setUnquotedName('test_unique_constraint')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $constraintName = UnqualifiedName::quoted('uniq_id');

        $uniqueConstraint = UniqueConstraint::editor()
            ->setName($constraintName)
            ->setUnquotedColumnNames('id')
            ->create();

        $platform = $this->connection->getDatabasePlatform();

        $this->schemaManager->createUniqueConstraint(
            $uniqueConstraint,
            $table->getObjectName()->toSQL($platform),
        );

        // there's currently no API for introspecting unique constraints,
        // so introspect the underlying indexes instead
        $indexes = $this->schemaManager->introspectTableIndexesByUnquotedName('test_unique_constraint');
        self::assertCount(1, $indexes);

        $index = $indexes[0];

        self::assertEqualsIgnoringCase('uniq_id', $index->getName());
        self::assertTrue($index->isUnique());

        $this->schemaManager->dropUniqueConstraint(
            $constraintName->toSQL($platform),
            $table->getObjectName()->toSQL($platform),
        );

        $indexes = $this->schemaManager->introspectTableIndexesByUnquotedName('test_unique_constraint');
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
        $tableToCreate->setPrimaryKey(['id']);

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
        self::assertCount(1, $table->getIndexes());

        $newTable = $table->edit()
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->dropColumnByUnquotedName('test')
            ->create();

        $comparator = $this->schemaManager->createComparator();

        $diff = $comparator->compareTables($table, $newTable);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');
        self::assertFalse($table->hasColumn('test'));
        self::assertTrue($table->hasColumn('foo'));

        $newTable = $table->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('foo_idx')
                    ->setUnquotedColumnNames('foo')
                    ->create(),
            )
            ->create();

        $diff = $comparator->compareTables($table, $newTable);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');
        self::assertCount(2, $table->getIndexes());
        self::assertTrue($table->hasIndex('foo_idx'));
        self::assertEquals(['foo'], array_map('strtolower', $table->getIndex('foo_idx')->getColumns()));
        self::assertFalse($table->getIndex('foo_idx')->isPrimary());
        self::assertFalse($table->getIndex('foo_idx')->isUnique());

        $fooIndex = Index::editor()
            ->setUnquotedName('foo_idx')
            ->setUnquotedColumnNames('foo', 'foreign_key_test')
            ->create();

        $newTable = $table->edit()
            ->dropIndexByUnquotedName('foo_idx')
            ->addIndex($fooIndex)
            ->create();

        $diff = $comparator->compareTables($table, $newTable);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');
        self::assertCount(2, $table->getIndexes());
        self::assertTrue($table->hasIndex('foo_idx'));

        $this->assertIndexEquals($fooIndex, $table->getIndex('foo_idx'));

        $barIndex = Index::editor()
            ->setUnquotedName('bar_idx')
            ->setUnquotedColumnNames('foo', 'foreign_key_test')
            ->create();

        $newTable = $table->edit()
            ->dropIndexByUnquotedName('foo_idx')
            ->addIndex($barIndex)
            ->create();

        $diff = $comparator->compareTables($table, $newTable);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');
        self::assertCount(2, $table->getIndexes());
        self::assertTrue($table->hasIndex('bar_idx'));
        self::assertFalse($table->hasIndex('foo_idx'));

        $this->assertIndexEquals($barIndex, $table->getIndex('bar_idx'));

        $newTable = $table->edit()
            ->dropIndexByUnquotedName('bar_idx')
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('foreign_key_test')
                    ->setUnquotedReferencedTableName('alter_table_foreign')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $diff = $comparator->compareTables($table, $newTable);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');

        // don't check for index size here, some platforms automatically add indexes for foreign keys.
        self::assertFalse($table->hasIndex('bar_idx'));

        /** @var list<ForeignKeyConstraint> $fks */
        $fks = array_values($table->getForeignKeys());
        self::assertCount(1, $fks);
        $foreignKey = $fks[0];

        self::assertEquals('alter_table_foreign', strtolower($foreignKey->getForeignTableName()));
        self::assertEquals(['foreign_key_test'], array_map('strtolower', $foreignKey->getLocalColumns()));
        self::assertEquals(['id'], array_map('strtolower', $foreignKey->getForeignColumns()));
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

        // test table created in a namespace
        $this->createTestTable('testschema.my_table_in_namespace');
        $this->assertOptionallyQualifiedNameListContainsUnquotedName(
            'my_table_in_namespace',
            'testschema',
            $this->schemaManager->introspectTableNames(),
        );

        // tables without a namespace should be created in the default namespace
        // the default namespace is omitted from the names during schema introspection
        $this->createTestTable('my_table_not_in_namespace');
        $this->assertOptionallyQualifiedNameListContainsUnquotedName(
            'my_table_not_in_namespace',
            null,
            $this->schemaManager->introspectTableNames(),
        );
    }

    public function testCreateAndListViews(): void
    {
        $this->createTestTable('view_test_table');

        $name = 'doctrine_test_view';
        $sql  = 'SELECT * FROM view_test_table';

        $view = View::editor()
            ->setUnquotedName($name)
            ->setSQL($sql)
            ->create();

        $this->schemaManager->createView($view);

        $views = $this->schemaManager->listViews();

        $found = $this->findObjectByShortestName($views, $name);
        self::assertNotNull($found);

        self::assertStringContainsString('view_test_table', $found->getSql());
    }

    public function testUpdateSchemaWithForeignKeyRenaming(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_fk_base')
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

        $tableFK = Table::editor()
            ->setUnquotedName('test_fk_rename')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('fk_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('fk_idx')
                    ->setUnquotedColumnNames('fk_id')
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('fk_id')
                    ->setUnquotedReferencedTableName('test_fk_base')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->setConfiguration(
                $this->schemaManager->createSchemaConfig()->toTableConfiguration(),
            )
            ->create();

        $platform = $this->connection->getDatabasePlatform();

        $this->dropTableIfExists($tableFK->getObjectName()->toSQL($platform));
        $this->dropTableIfExists($table->getObjectName()->toSQL($platform));

        $this->schemaManager->createTable($table);
        $this->schemaManager->createTable($tableFK);

        $tableFKNew = Table::editor()
            ->setUnquotedName('test_fk_rename')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('rename_fk_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('fk_idx')
                    ->setUnquotedColumnNames('rename_fk_id')
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rename_fk_id')
                    ->setUnquotedReferencedTableName('test_fk_base')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->setConfiguration(
                $this->schemaManager->createSchemaConfig()->toTableConfiguration(),
            )
            ->create();

        $diff = $this->schemaManager->createComparator()
            ->compareTables(
                $this->schemaManager->introspectTable($tableFK->getObjectName()->toString()),
                $tableFKNew,
            );

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('test_fk_rename');
        self::assertTrue($table->hasColumn('rename_fk_id'));

        /** @var list<ForeignKeyConstraint> $foreignKeys */
        $foreignKeys = array_values($table->getForeignKeys());
        self::assertCount(1, $foreignKeys);
        $foreignKey = $foreignKeys[0];

        self::assertSame(['rename_fk_id'], array_map('strtolower', $foreignKey->getLocalColumns()));
    }

    public function testRenameIndexUsedInForeignKeyConstraint(): void
    {
        $primaryTable = Table::editor()
            ->setUnquotedName('test_rename_index_primary')
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

        $foreignTable = Table::editor()
            ->setUnquotedName('test_rename_index_foreign')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('fk')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('rename_index_fk_idx')
                    ->setUnquotedColumnNames('fk')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_constraint')
                    ->setUnquotedReferencingColumnNames('fk')
                    ->setUnquotedReferencedTableName('test_rename_index_primary')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $platform = $this->connection->getDatabasePlatform();

        $this->dropTableIfExists($foreignTable->getObjectName()->toSQL($platform));
        $this->dropTableIfExists($primaryTable->getObjectName()->toSQL($platform));

        $this->schemaManager->createTable($primaryTable);
        $this->schemaManager->createTable($foreignTable);

        $foreignTable2 = $foreignTable->edit()
            ->renameIndexByUnquotedName('rename_index_fk_idx', 'renamed_index_fk_idx')
            ->create();

        $diff = $this->schemaManager->createComparator()
            ->compareTables($foreignTable, $foreignTable2);

        $this->schemaManager->alterTable($diff);

        $foreignTable = $this->schemaManager->introspectTableByUnquotedName('test_rename_index_foreign');

        self::assertFalse($foreignTable->hasIndex('rename_index_fk_idx'));
        self::assertTrue($foreignTable->hasIndex('renamed_index_fk_idx'));
        self::assertTrue($foreignTable->hasForeignKey('fk_constraint'));
    }

    public function testChangeColumnsTypeWithDefaultValue(): void
    {
        $oldTable = Table::editor()
            ->setUnquotedName('column_def_change_type')
            ->setColumns(
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
            )
            ->create();

        $this->dropAndCreateTable($oldTable);

        $newTable = $oldTable->edit()
            ->modifyColumnByUnquotedName('col_int', static function (ColumnEditor $editor): void {
                $editor->setTypeName(Types::INTEGER);
            })
            ->modifyColumnByUnquotedName('col_string', static function (ColumnEditor $editor): void {
                $editor->setFixed(true);
            })
            ->create();

        $diff = $this->schemaManager->createComparator()
            ->compareTables(
                $this->schemaManager->introspectTableByUnquotedName('column_def_change_type'),
                $newTable,
            );

        $this->schemaManager->alterTable($diff);

        $columns = $this->schemaManager->introspectTableColumnsByUnquotedName('column_def_change_type');

        self::assertInstanceOf(IntegerType::class, $columns[0]->getType());
        self::assertEquals(666, $columns[0]->getDefault());

        self::assertInstanceOf(StringType::class, $columns[1]->getType());
        self::assertEquals('foo', $columns[1]->getDefault());
    }

    public function testListTableWithBlob(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_blob_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('binarydata')
                    ->setTypeName(Types::BLOB)
                    ->create(),
            )
            ->create();

        $this->schemaManager->createTable($table);

        $created = $this->schemaManager->introspectTableByUnquotedName('test_blob_table');

        self::assertTrue($created->hasColumn('binarydata'));
        self::assertInstanceOf(BlobType::class, $created->getColumn('binarydata')->getType());
    }

    public function testListTableFloatTypeColumns(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_float_columns')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col_float')
                    ->setTypeName(Types::FLOAT)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_smallfloat')
                    ->setTypeName(Types::SMALLFLOAT)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->introspectTableColumnsByUnquotedName('test_float_columns');

        self::assertInstanceOf(FloatType::class, $columns[0]->getType());
        self::assertInstanceOf(SmallFloatType::class, $columns[1]->getType());
        self::assertFalse($columns[0]->getUnsigned());
        self::assertFalse($columns[1]->getUnsigned());
    }

    /**
     * @param non-empty-string $name
     * @param mixed[]          $data
     */
    protected function createTestTable(string $name, array $data = []): Table
    {
        $options = $data['options'] ?? [];
        self::assertIsArray($options);

        $table = $this->getTestTable($name, $options);

        $this->dropAndCreateTable($table);

        return $table;
    }

    /**
     * @param non-empty-string $unquotedName
     * @param mixed[]          $options
     */
    protected function getTestTable(string $unquotedName, array $options = []): Table
    {
        return Table::editor()
            ->setUnquotedName($unquotedName)
            ->setColumns(
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
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setOptions($options)
            ->setConfiguration(
                $this->schemaManager->createSchemaConfig()->toTableConfiguration(),
            )
            ->create();
    }

    /** @param non-empty-string $unquotedName */
    protected function getTestCompositeTable(string $unquotedName): Table
    {
        return Table::editor()
            ->setUnquotedName($unquotedName)
            ->setColumns(
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
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id', 'other_id')
                    ->create(),
            )
            ->setConfiguration(
                $this->schemaManager->createSchemaConfig()->toTableConfiguration(),
            )
            ->create();
    }

    public function testColumnDefaultLifecycle(): void
    {
        $oldTable = Table::editor()
            ->setUnquotedName('col_def_lifecycle')
            ->setColumns(
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
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($oldTable);

        $oldTable = $this->schemaManager->introspectTableByUnquotedName('col_def_lifecycle');

        self::assertNull($oldTable->getColumn('id')->getDefault());
        self::assertNull($oldTable->getColumn('column1')->getDefault());
        self::assertSame('', $oldTable->getColumn('column2')->getDefault());
        self::assertSame('default1', $oldTable->getColumn('column3')->getDefault());
        self::assertSame('0', $oldTable->getColumn('column4')->getDefault());

        $newTable = $oldTable->edit()
            ->modifyColumnByUnquotedName('column1', static function (ColumnEditor $editor): void {
                $editor->setDefaultValue('');
            })
            ->modifyColumnByUnquotedName('column2', static function (ColumnEditor $editor): void {
                $editor->setDefaultValue(null);
            })
            ->modifyColumnByUnquotedName('column3', static function (ColumnEditor $editor): void {
                $editor->setDefaultValue('default2');
            })
            ->modifyColumnByUnquotedName('column4', static function (ColumnEditor $editor): void {
                $editor->setDefaultValue(null);
            })
            ->create();

        $diff = $this->schemaManager->createComparator()
            ->compareTables(
                $this->schemaManager->introspectTableByUnquotedName('col_def_lifecycle'),
                $newTable,
            );

        $this->schemaManager->alterTable($diff);

        [, $column1, $column2, $column3, $column4]
            = $this->schemaManager->introspectTableColumnsByUnquotedName('col_def_lifecycle');

        self::assertSame('', $column1->getDefault());
        self::assertNull($column2->getDefault());
        self::assertSame('default2', $column3->getDefault());
        self::assertNull($column4->getDefault());
    }

    public function testListTableWithBinary(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_binary_table')
            ->setColumns(
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
            )
            ->create();

        $this->schemaManager->createTable($table);

        $table = $this->schemaManager->introspectTableByUnquotedName('test_binary_table');
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
        $this->schemaManager->introspectTableByUnquotedName('non_existing');
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

        $table = Table::editor()
            ->setUnquotedName($foreignTableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $table = Table::editor()
            ->setUnquotedName($primaryTableName)
            ->setColumns(
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
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('foo')
                    ->setUnquotedReferencedTableName($foreignTableName)
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $table->addIndex(['bar']);

        $this->dropAndCreateTable($table);

        self::assertEquals(
            $this->schemaManager->introspectTableColumnsByUnquotedName($primaryTableName),
            $this->schemaManager->introspectTableColumnsByUnquotedName($primaryTableName, $defaultSchemaName),
        );
        self::assertEquals(
            $this->schemaManager->introspectTableIndexesByUnquotedName($primaryTableName),
            $this->schemaManager->introspectTableIndexesByUnquotedName($primaryTableName, $defaultSchemaName),
        );
        self::assertEquals(
            $this->schemaManager->introspectTableForeignKeyConstraintsByUnquotedName($primaryTableName),
            $this->schemaManager->introspectTableForeignKeyConstraintsByUnquotedName(
                $primaryTableName,
                $defaultSchemaName,
            ),
        );
    }

    public function testDoesNotListIndexesImplicitlyCreatedByForeignKeys(): void
    {
        $primaryTable = Table::editor()
            ->setUnquotedName('test_list_index_impl_primary')
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

        $foreignTable = Table::editor()
            ->setUnquotedName('test_list_index_impl_foreign')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('fk1')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('fk2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('explicit_fk1_idx')
                    ->setUnquotedColumnNames('fk1')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('fk1')
                    ->setUnquotedReferencedTableName('test_list_index_impl_primary')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('fk2')
                    ->setUnquotedReferencedTableName('test_list_index_impl_primary')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($primaryTable);
        $this->dropAndCreateTable($foreignTable);

        $this->assertIndexListEquals([
            Index::editor()
                ->setName(UnqualifiedName::unquoted('IDX_3D6C147FDC58D6C'))
                ->setColumnNames(
                    UnqualifiedName::unquoted('fk2'),
                )
                ->create(),
            Index::editor()
                ->setName(UnqualifiedName::unquoted('explicit_fk1_idx'))
                ->setColumnNames(UnqualifiedName::unquoted('fk1'))
                ->create(),
        ], $this->schemaManager->introspectTableIndexesByUnquotedName('test_list_index_impl_foreign'));
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

        $sequence1 = Sequence::editor()
            ->setUnquotedName($sequence1Name)
            ->setAllocationSize($sequence1AllocationSize)
            ->setInitialValue($sequence1InitialValue)
            ->create();

        $sequence2 = Sequence::editor()
            ->setUnquotedName($sequence2Name)
            ->setAllocationSize($sequence2AllocationSize)
            ->setInitialValue($sequence2InitialValue)
            ->create();

        $this->schemaManager->createSequence($sequence1);
        $this->schemaManager->createSequence($sequence2);

        $actualSequences = $this->schemaManager->introspectSequences();
        $actualSequence1 = $this->findObjectByShortestName($actualSequences, $sequence1Name);
        $actualSequence2 = $this->findObjectByShortestName($actualSequences, $sequence2Name);

        self::assertNotNull($actualSequence1);
        self::assertEquals($sequence1AllocationSize, $actualSequence1->getAllocationSize());
        self::assertEquals($sequence1InitialValue, $actualSequence1->getInitialValue());

        self::assertNotNull($actualSequence2);
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

        $sequence = Sequence::editor()
            ->setUnquotedName($sequenceName)
            ->setAllocationSize($sequenceAllocationSize)
            ->setInitialValue($sequenceInitialValue)
            ->create();

        try {
            $this->schemaManager->dropSequence(
                $sequence->getObjectName()
                    ->toSQL($platform),
            );
        } catch (DatabaseObjectNotFoundException) {
        }

        $this->schemaManager->createSequence($sequence);

        $createdSequence = $this->findObjectByShortestName($this->schemaManager->introspectSequences(), $sequenceName);

        self::assertNotNull($createdSequence);

        $tableDiff = $this->schemaManager->createComparator()
            ->diffSequence($createdSequence, $sequence);

        self::assertFalse($tableDiff);
    }

    public function testPrimaryKeyAutoIncrement(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_pk_auto_increment')
            ->setColumns(
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
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->connection->insert('test_pk_auto_increment', ['text' => '1']);

        $lastUsedIdBeforeValue = $this->connection->fetchOne(
            "SELECT id FROM test_pk_auto_increment WHERE text = '1'",
        );
        self::assertIsNumeric($lastUsedIdBeforeValue);
        $lastUsedIdBeforeDelete = (int) $lastUsedIdBeforeValue;

        $this->connection->executeStatement('DELETE FROM test_pk_auto_increment');

        $this->connection->insert('test_pk_auto_increment', ['text' => '2']);

        $lastUsedIdAfterValue = $this->connection->fetchOne(
            "SELECT id FROM test_pk_auto_increment WHERE text = '2'",
        );
        self::assertIsNumeric($lastUsedIdAfterValue);
        $lastUsedIdAfterDelete = (int) $lastUsedIdAfterValue;

        self::assertGreaterThan($lastUsedIdBeforeDelete, $lastUsedIdAfterDelete);
    }

    public function testGenerateAnIndexWithPartialColumnLength(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsColumnLengthIndexes()) {
            self::markTestSkipped(
                'This test is only supported on platforms that support indexes with column length definitions.',
            );
        }

        $table = Table::editor()
            ->setUnquotedName('test_partial_column_index')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('long_column')
                    ->setTypeName(Types::STRING)
                    ->setLength(40)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('standard_column')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('partial_long_column_idx')
                    ->setColumns(
                        new IndexedColumn(UnqualifiedName::unquoted('long_column'), 4),
                    )
                    ->create(),
                Index::editor()
                    ->setUnquotedName('standard_and_partial_idx')
                    ->setColumns(
                        new IndexedColumn(UnqualifiedName::unquoted('standard_column'), null),
                        new IndexedColumn(UnqualifiedName::unquoted('long_column'), 2),
                    )
                    ->setUnquotedColumnNames('standard_column', 'long_column')
                    ->create(),
            )
            ->create();

        $expected = $table->getIndexes();

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTableByUnquotedName('test_partial_column_index');
        $this->assertIndexListEquals($expected, $onlineTable->getIndexes());
    }

    public function testCommentInTable(): void
    {
        $table = Table::editor()
            ->setUnquotedName('table_with_comment')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setComment('\'\\ Foo with control characters \'\\')
            ->create();

        $this->dropAndCreateTable($table);

        $table = $this->schemaManager->introspectTableByUnquotedName('table_with_comment');
        self::assertSame('\'\\ Foo with control characters \'\\', $table->getComment());
    }

    public function testIntrospectReservedKeywordTableViaListTableDetails(): void
    {
        $this->createReservedKeywordTables();

        $user = $this->schemaManager->introspectTableByQuotedName('user');
        self::assertCount(2, $user->getColumns());
        self::assertCount(2, $user->getIndexes());
        self::assertCount(1, $user->getForeignKeys());
    }

    public function testIntrospectReservedKeywordTableViaListTables(): void
    {
        $this->createReservedKeywordTables();

        $tables = $this->schemaManager->listTables();

        $user = $this->findObjectByShortestName($tables, 'user');
        self::assertNotNull($user);
        self::assertCount(2, $user->getColumns());
        self::assertCount(2, $user->getIndexes());
        self::assertCount(1, $user->getForeignKeys());
    }

    private function createReservedKeywordTables(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $this->dropTableIfExists($platform->quoteSingleIdentifier('user'));
        $this->dropTableIfExists($platform->quoteSingleIdentifier('group'));

        $user = Table::editor()
            ->setUnquotedName('user')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('group_id')
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
                    ->setUnquotedReferencingColumnNames('group_id')
                    ->setUnquotedReferencedTableName('group')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $group = Table::editor()
            ->setUnquotedName('group')
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
                'Introspection of lower-case identifiers as quoted is currently not implemented on Db2.',
            );
        }

        if (! $platform instanceof OraclePlatform && ! $platform instanceof PostgreSQLPlatform) {
            self::markTestSkipped('The current platform does not auto-quote introspected identifiers.');
        }

        $artists = Table::editor()
            ->setQuotedName('Artists')
            ->setColumns(
                Column::editor()
                    ->setQuotedName('Id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setQuotedName('Name')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setQuotedColumnNames('Id')
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setQuotedName('Idx_Name')
                    ->setQuotedColumnNames('Name')
                    ->create(),
            )
            ->create();

        $tracks = Table::editor()
            ->setQuotedName('Tracks')
            ->setColumns(
                Column::editor()
                    ->setQuotedName('Id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setQuotedName('Artist_Id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setQuotedName('Idx_Artist_Id')
                    ->setQuotedColumnNames('Artist_Id')
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setQuotedColumnNames('Id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setQuotedName('Artists_Fk')
                    ->setQuotedReferencingColumnNames('Artist_Id')
                    ->setQuotedReferencedTableName('Artists')
                    ->setQuotedReferencedColumnNames('Id')
                    ->create(),
            )
            ->create();

        $this->dropTableIfExists('"Tracks"');
        $this->dropTableIfExists('"Artists"');

        $this->schemaManager->createTable($artists);
        $this->schemaManager->createTable($tracks);

        $artists = $this->schemaManager->introspectTableByQuotedName('Artists');
        $tracks  = $this->schemaManager->introspectTableByQuotedName('Tracks');

        $platform = $this->connection->getDatabasePlatform();

        // Primary table assertions
        self::assertSame('"Artists"', $artists->getQuotedName($platform));
        self::assertSame('"Id"', $artists->getColumn('"Id"')->getQuotedName($platform));
        self::assertSame('"Name"', $artists->getColumn('"Name"')->getQuotedName($platform));
        self::assertSame(['"Name"'], $artists->getIndex('"Idx_Name"')->getQuotedColumns($platform));

        $primaryKey = $artists->getPrimaryKey();
        self::assertNotNull($primaryKey);
        self::assertSame(['"Id"'], $primaryKey->getQuotedColumns($platform));

        // Foreign table assertions
        self::assertTrue($tracks->hasColumn('"Id"'));
        self::assertSame('"Id"', $tracks->getColumn('"Id"')->getQuotedName($platform));

        $primaryKey = $tracks->getPrimaryKey();
        self::assertNotNull($primaryKey);
        self::assertSame(['"Id"'], $primaryKey->getQuotedColumns($platform));

        self::assertTrue($tracks->hasColumn('"Artist_Id"'));
        self::assertSame(
            '"Artist_Id"',
            $tracks->getColumn('"Artist_Id"')->getQuotedName($platform),
        );

        self::assertTrue($tracks->hasIndex('"Idx_Artist_Id"'));
        self::assertSame(
            ['"Artist_Id"'],
            $tracks->getIndex('"Idx_Artist_Id"')->getQuotedColumns($platform),
        );

        self::assertTrue($tracks->hasForeignKey('"Artists_Fk"'));
        self::assertSame(
            '"Artists"',
            $tracks->getForeignKey('"Artists_Fk"')->getQuotedForeignTableName($platform),
        );
        self::assertSame(
            ['"Artist_Id"'],
            $tracks->getForeignKey('"Artists_Fk"')->getQuotedLocalColumns($platform),
        );
        self::assertSame(
            ['"Id"'],
            $tracks->getForeignKey('"Artists_Fk"')->getQuotedForeignColumns($platform),
        );
    }

    public function testChangeIndexWithForeignKeys(): void
    {
        $this->dropTableIfExists('child');
        $this->dropTableIfExists('parent');

        $parent = Table::editor()
            ->setUnquotedName('parent')
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

        $child = Table::editor()
            ->setUnquotedName('child')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('parent_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('idx_1')
                    ->setUnquotedColumnNames('parent_id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('parent_id')
                    ->setUnquotedReferencedTableName('parent')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $schema = new Schema([$parent, $child]);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createSchemaObjects($schema);

        $child = $child->edit()
            ->dropIndexByUnquotedName('idx_1')
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_2')
                    ->setUnquotedColumnNames('parent_id')
                    ->create(),
            )
            ->create();

        $diff = $schemaManager->createComparator(
            (new ComparatorConfig())->withDetectRenamedIndexes(false),
        )->compareTables(
            $schemaManager->introspectTable('child'),
            $child,
        );

        $schemaManager->alterTable($diff);

        $child = $schemaManager->introspectTableByUnquotedName('child');

        self::assertFalse($child->hasIndex('idx_1'));
        self::assertTrue($child->hasIndex('idx_2'));
    }

    /** @throws Exception */
    public function testSwitchPrimaryKeyOrder(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (
            $platform instanceof DB2Platform
            || $platform instanceof OraclePlatform
            || $platform instanceof SQLServerPlatform
        ) {
            self::markTestIncomplete(
                'Dropping primary key constraint on the currently used database platform is not implemented.',
            );
        }

        $prototype = Table::editor()
            ->setUnquotedName('test_switch_pk_order')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('bar_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $table = clone $prototype;
        $table->setPrimaryKey(['foo_id', 'bar_id']);
        $this->dropAndCreateTable($table);

        $table = clone $prototype;
        $table->setPrimaryKey(['bar_id', 'foo_id']);

        $schemaManager = $this->connection->createSchemaManager();

        $diff = $schemaManager->createComparator()->compareTables(
            $schemaManager->introspectTable('test_switch_pk_order'),
            $table,
        );
        self::assertFalse($diff->isEmpty());
        $schemaManager->alterTable($diff);

        $table      = $schemaManager->introspectTable('test_switch_pk_order');
        $primaryKey = $table->getPrimaryKey();
        self::assertNotNull($primaryKey);
        self::assertSame(['bar_id', 'foo_id'], array_map('strtolower', $primaryKey->getColumns()));
    }

    public function testDropColumnWithDefault(): void
    {
        $table = Table::editor()
            ->setUnquotedName('drop_column_with_default')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('todrop')
                    ->setTypeName(Types::INTEGER)
                    ->setDefaultValue(10)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $table = $table->edit()
            ->dropColumnByUnquotedName('todrop')
            ->create();

        $diff = $this->schemaManager->createComparator()
            ->compareTables(
                $this->schemaManager->introspectTableByUnquotedName('drop_column_with_default'),
                $table,
            );

        $this->schemaManager->alterTable($diff);

        $columns = $this->schemaManager->introspectTableColumnsByUnquotedName('drop_column_with_default');
        self::assertCount(1, $columns);
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

        $nestedRelatedTable = Table::editor()
            ->setUnquotedName('schemarelated', 'nested')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->setPrimaryKeyConstraint($primaryKeyConstraint)
            ->create();

        $nestedSchemaTable = Table::editor()
            ->setUnquotedName('schematable', 'nested')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->setPrimaryKeyConstraint($primaryKeyConstraint)
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('id')
                    ->setUnquotedReferencedTableName('schemarelated', 'nested')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->setComment('This is a comment')
            ->create();

        $this->schemaManager->createTable($nestedRelatedTable);
        $this->schemaManager->createTable($nestedSchemaTable);

        $tableNames = $this->schemaManager->listTableNames();
        self::assertContains('nested.schematable', $tableNames);

        $tables = $this->schemaManager->listTables();
        self::assertNotNull($this->findObjectByName($tables, 'nested.schematable'));

        $nestedSchemaTable = $this->schemaManager->introspectTable('nested.schematable');
        self::assertTrue($nestedSchemaTable->hasColumn('id'));

        $primaryKey = $nestedSchemaTable->getPrimaryKey();
        self::assertNotNull($primaryKey);
        self::assertEquals(['id'], $primaryKey->getColumns());

        $relatedFks = array_values($nestedSchemaTable->getForeignKeys());
        self::assertCount(1, $relatedFks);
        $relatedFk = $relatedFks[0];
        self::assertEquals('nested.schemarelated', $relatedFk->getForeignTableName());
        self::assertEquals('This is a comment', $nestedSchemaTable->getComment());
    }
}
