<?php

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Schema\View;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\ArrayType;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\DateIntervalType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\ObjectType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_search;
use function array_values;
use function count;
use function current;
use function get_class;
use function sprintf;
use function strcasecmp;
use function strlen;
use function strtolower;
use function substr;

abstract class SchemaManagerFunctionalTestCase extends FunctionalTestCase
{
    protected AbstractSchemaManager $schemaManager;

    abstract protected function supportsPlatform(AbstractPlatform $platform): bool;

    protected function setUp(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $this->supportsPlatform($platform)) {
            self::markTestSkipped(sprintf('Skipping since connected to %s', get_class($platform)));
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
        } catch (Exception $e) {
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

    /** @param AbstractAsset[] $items */
    private function hasElementWithName(array $items, string $name): bool
    {
        $filteredList = $this->filterElementsByName($items, $name);

        return count($filteredList) === 1;
    }

    /**
     * @param AbstractAsset[] $items
     *
     * @return AbstractAsset[]
     */
    private function filterElementsByName(array $items, string $name): array
    {
        return array_filter(
            $items,
            static function (AbstractAsset $item) use ($name): bool {
                return $item->getShortestName($item->getNamespaceName()) === $name;
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

        $sequences = $this->schemaManager->listSequences();

        foreach ($sequences as $sequence) {
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
        if (! $this->connection->getDatabasePlatform()->supportsCreateDropDatabase()) {
            self::markTestSkipped('Cannot drop Database client side with this Driver.');
        }

        try {
            $this->schemaManager->dropDatabase('test_create_database');
        } catch (DatabaseObjectNotFoundException $e) {
        }

        $this->schemaManager->createDatabase('test_create_database');

        $databases = $this->schemaManager->listDatabases();

        $databases = array_map('strtolower', $databases);

        self::assertContains('test_create_database', $databases);
    }

    /** @dataProvider listSchemaNamesMethodProvider */
    public function testListSchemaNames(callable $method): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform->supportsSchemas()) {
            self::markTestSkipped('Platform does not support schemas.');
        }

        try {
            $this->schemaManager->dropSchema('test_create_schema');
        } catch (DatabaseObjectNotFoundException $e) {
        }

        self::assertNotContains('test_create_schema', $this->schemaManager->listSchemaNames());

        $this->connection->executeStatement(
            $platform->getCreateSchemaSQL('test_create_schema'),
        );

        self::assertContains('test_create_schema', $method($this->schemaManager));
    }

    /** @return iterable<list<mixed>> */
    public static function listSchemaNamesMethodProvider(): iterable
    {
        yield [
            static function (AbstractSchemaManager $schemaManager): array {
                return $schemaManager->listNamespaceNames();
            },
        ];

        yield [
            static function (AbstractSchemaManager $schemaManager): array {
                return $schemaManager->listSchemaNames();
            },
        ];
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

    /** @dataProvider tableFilterProvider */
    public function testListTablesWithFilter(string $prefix, int $expectedCount): void
    {
        $this->createTestTable('filter_test_1');
        $this->createTestTable('filter_test_2');

        $this->markConnectionNotReusable();

        $this->connection->getConfiguration()->setSchemaAssetsFilter(
            static function (string $name) use ($prefix): bool {
                return substr(strtolower($name), 0, strlen($prefix)) === $prefix;
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
        $table = new Table('list_table_columns');
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn(
            'test',
            Types::STRING,
            ['length' => 255, 'notnull' => false, 'default' => 'expected default'],
        );
        $table->addColumn('foo', Types::TEXT, ['notnull' => true]);
        $table->addColumn('bar', Types::DECIMAL, ['precision' => 10, 'scale' => 4, 'notnull' => false]);
        $table->addColumn('baz1', Types::DATETIME_MUTABLE);
        $table->addColumn('baz2', Types::TIME_MUTABLE);
        $table->addColumn('baz3', Types::DATE_MUTABLE);
        $table->setPrimaryKey(['id']);

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
        self::assertIsArray($columns['id']->getPlatformOptions());

        self::assertArrayHasKey('test', $columns);
        self::assertEquals(1, array_search('test', $columnsKeys, true));
        self::assertEquals('test', strtolower($columns['test']->getname()));
        self::assertInstanceOf(StringType::class, $columns['test']->gettype());
        self::assertEquals(255, $columns['test']->getlength());
        self::assertEquals(false, $columns['test']->getfixed());
        self::assertEquals(false, $columns['test']->getnotnull());
        self::assertEquals('expected default', $columns['test']->getdefault());
        self::assertIsArray($columns['test']->getPlatformOptions());

        self::assertEquals('foo', strtolower($columns['foo']->getname()));
        self::assertEquals(2, array_search('foo', $columnsKeys, true));
        self::assertInstanceOf(TextType::class, $columns['foo']->gettype());
        self::assertEquals(false, $columns['foo']->getunsigned());
        self::assertEquals(false, $columns['foo']->getfixed());
        self::assertEquals(true, $columns['foo']->getnotnull());
        self::assertEquals(null, $columns['foo']->getdefault());
        self::assertIsArray($columns['foo']->getPlatformOptions());

        self::assertEquals('bar', strtolower($columns['bar']->getname()));
        self::assertEquals(3, array_search('bar', $columnsKeys, true));
        self::assertInstanceOf(DecimalType::class, $columns['bar']->gettype());
        self::assertEquals(null, $columns['bar']->getlength());
        self::assertEquals(10, $columns['bar']->getprecision());
        self::assertEquals(4, $columns['bar']->getscale());
        self::assertEquals(false, $columns['bar']->getunsigned());
        self::assertEquals(false, $columns['bar']->getfixed());
        self::assertEquals(false, $columns['bar']->getnotnull());
        self::assertEquals(null, $columns['bar']->getdefault());
        self::assertIsArray($columns['bar']->getPlatformOptions());

        self::assertEquals('baz1', strtolower($columns['baz1']->getname()));
        self::assertEquals(4, array_search('baz1', $columnsKeys, true));
        self::assertInstanceOf(DateTimeType::class, $columns['baz1']->gettype());
        self::assertEquals(true, $columns['baz1']->getnotnull());
        self::assertEquals(null, $columns['baz1']->getdefault());
        self::assertIsArray($columns['baz1']->getPlatformOptions());

        self::assertEquals('baz2', strtolower($columns['baz2']->getname()));
        self::assertEquals(5, array_search('baz2', $columnsKeys, true));
        self::assertContains(
            $columns['baz2']->gettype()->getName(),
            [Types::TIME_MUTABLE, Types::DATE_MUTABLE, Types::DATETIME_MUTABLE],
        );
        self::assertEquals(true, $columns['baz2']->getnotnull());
        self::assertEquals(null, $columns['baz2']->getdefault());
        self::assertIsArray($columns['baz2']->getPlatformOptions());

        self::assertEquals('baz3', strtolower($columns['baz3']->getname()));
        self::assertEquals(6, array_search('baz3', $columnsKeys, true));
        self::assertContains(
            $columns['baz3']->gettype()->getName(),
            [Types::TIME_MUTABLE, Types::DATE_MUTABLE, Types::DATETIME_MUTABLE],
        );
        self::assertEquals(true, $columns['baz3']->getnotnull());
        self::assertEquals(null, $columns['baz3']->getdefault());
        self::assertIsArray($columns['baz3']->getPlatformOptions());
    }

    public function testListTableColumnsWithFixedStringColumn(): void
    {
        $tableName = 'test_list_table_fixed_string';

        $table = new Table($tableName);
        $table->addColumn('column_char', Types::STRING, ['fixed' => true, 'length' => 2]);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertArrayHasKey('column_char', $columns);
        self::assertInstanceOf(StringType::class, $columns['column_char']->getType());
        self::assertTrue($columns['column_char']->getFixed());
        self::assertSame(2, $columns['column_char']->getLength());
    }

    public function testListTableColumnsDispatchEvent(): void
    {
        $table = $this->createListTableColumns();

        $this->dropAndCreateTable($table);

        $listenerMock = $this->createMock(ListTableColumnsDispatchEventListener::class);
        $listenerMock
            ->expects(self::exactly(7))
            ->method('onSchemaColumnDefinition');

        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::onSchemaColumnDefinition], $listenerMock);

        $this->connection->getDatabasePlatform()->setEventManager($eventManager);

        $this->schemaManager->listTableColumns('list_table_columns');
    }

    public function testListTableIndexesDispatchEvent(): void
    {
        $table = $this->getTestTable('list_table_indexes_test');
        $table->addUniqueIndex(['test'], 'test_index_name');
        $table->addIndex(['id', 'test'], 'test_composite_idx');

        $this->dropAndCreateTable($table);

        $listenerMock = $this->createMock(ListTableIndexesDispatchEventListener::class);
        $listenerMock
            ->expects(self::exactly(3))
            ->method('onSchemaIndexDefinition');

        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::onSchemaIndexDefinition], $listenerMock);

        $this->connection->getDatabasePlatform()->setEventManager($eventManager);

        $this->schemaManager->listTableIndexes('list_table_indexes_test');
    }

    public function testDispatchEventWhenDatabasePlatformIsExplicitlyPassed(): void
    {
        $params             = $this->connection->getParams();
        $params['platform'] = $this->connection->getDriver()->getDatabasePlatform();

        $listenerMock = $this->createMock(CreateTableDispatchEventListener::class);
        $listenerMock
            ->expects(self::once())
            ->method('onSchemaCreateTable');

        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::onSchemaCreateTable], $listenerMock);

        // We need to work with a new connection because the shared one has an auto-detected platform already set.
        $connection = DriverManager::getConnection($params, null, $eventManager);

        $table = $this->getTestTable('explicit_db_platform_test');

        $schemaManager = $connection->createSchemaManager();
        $schemaManager->createTable($table);
    }

    /**
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     *
     * @dataProvider \Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils::comparatorProvider
     */
    public function testDiffListTableColumns(callable $comparatorFactory): void
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

        $diff = $comparatorFactory($this->schemaManager)->diffTable($onlineTable, $offlineTable);

        self::assertFalse($diff, 'No differences should be detected with the offline vs online schema.');
    }

    public function testListTableIndexes(): void
    {
        $table = $this->getTestCompositeTable('list_table_indexes_test');
        $table->addUniqueIndex(['test'], 'test_index_name');
        $table->addIndex(['id', 'test'], 'test_composite_idx');

        $this->dropAndCreateTable($table);

        $tableIndexes = $this->schemaManager->listTableIndexes('list_table_indexes_test');

        self::assertCount(3, $tableIndexes);

        self::assertArrayHasKey('primary', $tableIndexes, 'listTableIndexes() has to return a "primary" array key.');
        self::assertEquals(['id', 'other_id'], array_map('strtolower', $tableIndexes['primary']->getColumns()));
        self::assertTrue($tableIndexes['primary']->isUnique());
        self::assertTrue($tableIndexes['primary']->isPrimary());

        self::assertEquals('test_index_name', strtolower($tableIndexes['test_index_name']->getName()));
        self::assertEquals(['test'], array_map('strtolower', $tableIndexes['test_index_name']->getColumns()));
        self::assertTrue($tableIndexes['test_index_name']->isUnique());
        self::assertFalse($tableIndexes['test_index_name']->isPrimary());

        self::assertEquals('test_composite_idx', strtolower($tableIndexes['test_composite_idx']->getName()));
        self::assertEquals(['id', 'test'], array_map('strtolower', $tableIndexes['test_composite_idx']->getColumns()));
        self::assertFalse($tableIndexes['test_composite_idx']->isUnique());
        self::assertFalse($tableIndexes['test_composite_idx']->isPrimary());
    }

    public function testDropAndCreateIndex(): void
    {
        $table = $this->getTestTable('test_create_index');
        $table->addUniqueIndex(['test'], 'test');
        $this->dropAndCreateTable($table);

        $index = $table->getIndex('test');
        $this->schemaManager->dropIndex($index, $table);
        $this->schemaManager->createIndex($index, $table);
        $tableIndexes = $this->schemaManager->listTableIndexes('test_create_index');

        self::assertEquals('test', strtolower($tableIndexes['test']->getName()));
        self::assertEquals(['test'], array_map('strtolower', $tableIndexes['test']->getColumns()));
        self::assertTrue($tableIndexes['test']->isUnique());
        self::assertFalse($tableIndexes['test']->isPrimary());
    }

    public function testDropAndCreateUniqueConstraint(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            self::markTestSkipped('SQLite does not support adding constraints to a table');
        }

        $table = new Table('test_unique_constraint');
        $table->addColumn('id', Types::INTEGER);
        $this->dropAndCreateTable($table);

        $uniqueConstraint = new UniqueConstraint('uniq_id', ['id']);
        $this->schemaManager->createUniqueConstraint($uniqueConstraint, $table->getName());

        // there's currently no API for introspecting unique constraints,
        // so introspect the underlying indexes instead
        $indexes = $this->schemaManager->listTableIndexes('test_unique_constraint');
        self::assertCount(1, $indexes);

        $index = current($indexes);
        self::assertEqualsIgnoringCase('uniq_id', $index->getName());
        self::assertTrue($index->isUnique());

        $this->schemaManager->dropUniqueConstraint($uniqueConstraint->getName(), $table->getName());

        $indexes = $this->schemaManager->listTableIndexes('test_unique_constraint');
        self::assertEmpty($indexes);
    }

    public function testCreateTableWithForeignKeys(): void
    {
        $tableB = $this->getTestTable('test_foreign');

        $this->dropAndCreateTable($tableB);

        $tableA = $this->getTestTable('test_create_fk');
        $tableA->addForeignKeyConstraint('test_foreign', ['foreign_key_test'], ['id']);

        $this->dropAndCreateTable($tableA);

        $fkTable       = $this->schemaManager->introspectTable('test_create_fk');
        $fkConstraints = $fkTable->getForeignKeys();
        self::assertCount(1, $fkConstraints, "Table 'test_create_fk' has to have one foreign key.");

        $fkConstraint = current($fkConstraints);
        self::assertInstanceOf(ForeignKeyConstraint::class, $fkConstraint);
        self::assertEquals('test_foreign', strtolower($fkConstraint->getForeignTableName()));
        self::assertEquals(['foreign_key_test'], array_map('strtolower', $fkConstraint->getColumns()));
        self::assertEquals(['id'], array_map('strtolower', $fkConstraint->getForeignColumns()));

        self::assertTrue($fkTable->columnsAreIndexed($fkConstraint->getColumns()));
    }

    public function testListForeignKeys(): void
    {
        $this->createTestTable('test_create_fk1');
        $this->createTestTable('test_create_fk2');

        $foreignKey = new ForeignKeyConstraint(
            ['foreign_key_test'],
            'test_create_fk2',
            ['id'],
            'foreign_key_test_fk',
            ['onDelete' => 'CASCADE'],
        );

        $this->schemaManager->createForeignKey($foreignKey, 'test_create_fk1');

        $fkeys = $this->schemaManager->listTableForeignKeys('test_create_fk1');

        self::assertCount(1, $fkeys, "Table 'test_create_fk1' has to have one foreign key.");

        self::assertInstanceOf(ForeignKeyConstraint::class, $fkeys[0]);
        self::assertEquals(['foreign_key_test'], array_map('strtolower', $fkeys[0]->getLocalColumns()));
        self::assertEquals(['id'], array_map('strtolower', $fkeys[0]->getForeignColumns()));
        self::assertEquals('test_create_fk2', strtolower($fkeys[0]->getForeignTableName()));

        if (! $fkeys[0]->hasOption('onDelete')) {
            return;
        }

        self::assertEquals('CASCADE', $fkeys[0]->getOption('onDelete'));
    }

    public function testCreateForeignKeyWithTableObject(): void
    {
        $this->createTestTable('test_create_fk1');
        $this->createTestTable('test_create_fk2');

        $table = $this->schemaManager->introspectTable('test_create_fk1');
        $table->addForeignKeyConstraint(
            'test_create_fk2',
            ['foreign_key_test'],
            ['id'],
            [],
            'i',
        );
        $foreignKey = $table->getForeignKeys()['i'];
        $this->schemaManager->createForeignKey($foreignKey, $table);

        $fkeys = $this->schemaManager->listTableForeignKeys('test_create_fk1');

        self::assertCount(1, $fkeys, "Table 'test_create_fk1' has to have one foreign key.");

        self::assertInstanceOf(ForeignKeyConstraint::class, $fkeys[0]);
        self::assertEquals(['foreign_key_test'], array_map('strtolower', $fkeys[0]->getLocalColumns()));
        self::assertEquals(['id'], array_map('strtolower', $fkeys[0]->getForeignColumns()));
        self::assertEquals('test_create_fk2', strtolower($fkeys[0]->getForeignTableName()));
    }

    protected function getCreateExampleViewSql(): void
    {
        self::markTestSkipped('No Create Example View SQL was defined for this SchemaManager');
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

    public function testAlterTableScenario(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsAlterTable()) {
            self::markTestSkipped('Alter Table is not supported by this platform.');
        }

        $this->createTestTable('alter_table');
        $this->createTestTable('alter_table_foreign');

        $table = $this->schemaManager->introspectTable('alter_table');
        self::assertTrue($table->hasColumn('id'));
        self::assertTrue($table->hasColumn('test'));
        self::assertTrue($table->hasColumn('foreign_key_test'));
        self::assertCount(0, $table->getForeignKeys());
        self::assertCount(1, $table->getIndexes());

        $newTable = clone $table;
        $newTable->addColumn('foo', Types::INTEGER);
        $newTable->dropColumn('test');

        $comparator = $this->schemaManager->createComparator();

        $diff = $comparator->diffTable($table, $newTable);
        self::assertNotFalse($diff);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');
        self::assertFalse($table->hasColumn('test'));
        self::assertTrue($table->hasColumn('foo'));

        $newTable = clone $table;
        $newTable->addIndex(['foo'], 'foo_idx');

        $diff = $comparator->diffTable($table, $newTable);
        self::assertNotFalse($diff);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');
        self::assertCount(2, $table->getIndexes());
        self::assertTrue($table->hasIndex('foo_idx'));
        self::assertEquals(['foo'], array_map('strtolower', $table->getIndex('foo_idx')->getColumns()));
        self::assertFalse($table->getIndex('foo_idx')->isPrimary());
        self::assertFalse($table->getIndex('foo_idx')->isUnique());

        $newTable = clone $table;
        $newTable->dropIndex('foo_idx');
        $newTable->addIndex(['foo', 'foreign_key_test'], 'foo_idx');

        $diff = $comparator->diffTable($table, $newTable);
        self::assertNotFalse($diff);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');
        self::assertCount(2, $table->getIndexes());
        self::assertTrue($table->hasIndex('foo_idx'));
        self::assertEquals(
            ['foo', 'foreign_key_test'],
            array_map('strtolower', $table->getIndex('foo_idx')->getColumns()),
        );

        $newTable = clone $table;
        $newTable->dropIndex('foo_idx');
        $newTable->addIndex(['foo', 'foreign_key_test'], 'bar_idx');

        $diff = $comparator->diffTable($table, $newTable);
        self::assertNotFalse($diff);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');
        self::assertCount(2, $table->getIndexes());
        self::assertTrue($table->hasIndex('bar_idx'));
        self::assertFalse($table->hasIndex('foo_idx'));
        self::assertEquals(
            ['foo', 'foreign_key_test'],
            array_map('strtolower', $table->getIndex('bar_idx')->getColumns()),
        );
        self::assertFalse($table->getIndex('bar_idx')->isPrimary());
        self::assertFalse($table->getIndex('bar_idx')->isUnique());

        $newTable = clone $table;
        $newTable->dropIndex('bar_idx');
        $newTable->addForeignKeyConstraint('alter_table_foreign', ['foreign_key_test'], ['id']);

        $diff = $comparator->diffTable($table, $newTable);
        self::assertNotFalse($diff);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table');

        // don't check for index size here, some platforms automatically add indexes for foreign keys.
        self::assertFalse($table->hasIndex('bar_idx'));

        $fks = $table->getForeignKeys();
        self::assertCount(1, $fks);
        $foreignKey = current($fks);
        self::assertEquals('alter_table_foreign', strtolower($foreignKey->getForeignTableName()));
        self::assertEquals(['foreign_key_test'], array_map('strtolower', $foreignKey->getColumns()));
        self::assertEquals(['id'], array_map('strtolower', $foreignKey->getForeignColumns()));
    }

    public function testTableInNamespace(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform->supportsSchemas()) {
            self::markTestSkipped('Schema definition is not supported by this platform.');
        }

        //create schema
        $diff                  = new SchemaDiff();
        $diff->newNamespaces[] = 'testschema';

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
        if (! $this->connection->getDatabasePlatform()->supportsViews()) {
            self::markTestSkipped('Views is not supported by this platform.');
        }

        $this->createTestTable('view_test_table');

        $name = 'doctrine_test_view';
        $sql  = 'SELECT * FROM view_test_table';

        $view = new View($name, $sql);

        $this->schemaManager->createView($view);

        $views = $this->schemaManager->listViews();

        $filtered = array_values($this->filterElementsByName($views, $name));
        self::assertCount(1, $filtered);

        $viewKey = strtolower($filtered[0]->getName());
        self::assertStringContainsString('view_test_table', $views[$viewKey]->getSql());
    }

    public function testAutoincrementDetection(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsIdentityColumns()) {
            self::markTestSkipped('This test is only supported on platforms that have autoincrement');
        }

        $table = new Table('test_autoincrement');
        $table->setSchemaConfig($this->schemaManager->createSchemaConfig());
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $inferredTable = $this->schemaManager->introspectTable('test_autoincrement');
        self::assertTrue($inferredTable->hasColumn('id'));
        self::assertTrue($inferredTable->getColumn('id')->getAutoincrement());
    }

    public function testAutoincrementDetectionMulticolumns(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsIdentityColumns()) {
            self::markTestSkipped('This test is only supported on platforms that have autoincrement');
        }

        $table = new Table('test_not_autoincrement');
        $table->setSchemaConfig($this->schemaManager->createSchemaConfig());
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('other_id', Types::INTEGER);
        $table->setPrimaryKey(['id', 'other_id']);

        $this->schemaManager->createTable($table);

        $inferredTable = $this->schemaManager->introspectTable('test_not_autoincrement');
        self::assertTrue($inferredTable->hasColumn('id'));
        self::assertFalse($inferredTable->getColumn('id')->getAutoincrement());
    }

    /**
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     *
     * @dataProvider \Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils::comparatorProvider
     */
    public function testUpdateSchemaWithForeignKeyRenaming(callable $comparatorFactory): void
    {
        $table = new Table('test_fk_base');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $tableFK = new Table('test_fk_rename');
        $tableFK->setSchemaConfig($this->schemaManager->createSchemaConfig());
        $tableFK->addColumn('id', Types::INTEGER);
        $tableFK->addColumn('fk_id', Types::INTEGER);
        $tableFK->setPrimaryKey(['id']);
        $tableFK->addIndex(['fk_id'], 'fk_idx');
        $tableFK->addForeignKeyConstraint('test_fk_base', ['fk_id'], ['id']);

        $this->dropTableIfExists($tableFK->getName());
        $this->dropTableIfExists($table->getName());

        $this->schemaManager->createTable($table);
        $this->schemaManager->createTable($tableFK);

        $tableFKNew = new Table('test_fk_rename');
        $tableFKNew->setSchemaConfig($this->schemaManager->createSchemaConfig());
        $tableFKNew->addColumn('id', Types::INTEGER);
        $tableFKNew->addColumn('rename_fk_id', Types::INTEGER);
        $tableFKNew->setPrimaryKey(['id']);
        $tableFKNew->addIndex(['rename_fk_id'], 'fk_idx');
        $tableFKNew->addForeignKeyConstraint('test_fk_base', ['rename_fk_id'], ['id']);

        $diff = $comparatorFactory($this->schemaManager)->diffTable($tableFK, $tableFKNew);
        self::assertNotFalse($diff);

        $this->schemaManager->alterTable($diff);

        $table       = $this->schemaManager->introspectTable('test_fk_rename');
        $foreignKeys = $table->getForeignKeys();

        self::assertTrue($table->hasColumn('rename_fk_id'));
        self::assertCount(1, $foreignKeys);
        self::assertSame(['rename_fk_id'], array_map('strtolower', current($foreignKeys)->getColumns()));
    }

    /**
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     *
     * @dataProvider \Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils::comparatorProvider
     */
    public function testRenameIndexUsedInForeignKeyConstraint(callable $comparatorFactory): void
    {
        $primaryTable = new Table('test_rename_index_primary');
        $primaryTable->addColumn('id', Types::INTEGER);
        $primaryTable->setPrimaryKey(['id']);

        $foreignTable = new Table('test_rename_index_foreign');
        $foreignTable->addColumn('fk', Types::INTEGER);
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

        $diff = $comparatorFactory($this->schemaManager)->diffTable($foreignTable, $foreignTable2);
        self::assertNotFalse($diff);

        $this->schemaManager->alterTable($diff);

        $foreignTable = $this->schemaManager->introspectTable('test_rename_index_foreign');

        self::assertFalse($foreignTable->hasIndex('rename_index_fk_idx'));
        self::assertTrue($foreignTable->hasIndex('renamed_index_fk_idx'));
        self::assertTrue($foreignTable->hasForeignKey('fk_constraint'));
    }

    public function testGetColumnComment(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (
            ! $platform->supportsInlineColumnComments() &&
            ! $platform->supportsCommentOnStatement() &&
            ! $platform instanceof SQLServerPlatform
        ) {
            self::markTestSkipped('Database does not support column comments.');
        }

        $table = new Table('column_comment_test');
        $table->addColumn('id', Types::INTEGER, ['comment' => 'This is a comment']);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns('column_comment_test');
        self::assertCount(1, $columns);
        self::assertEquals('This is a comment', $columns['id']->getComment());

        $newTable = clone $table;
        $newTable->changeColumn('id', ['comment' => null]);

        $diff = $this->schemaManager->createComparator()
            ->diffTable($table, $newTable);
        self::assertNotFalse($diff);

        $this->schemaManager->alterTable($diff);

        $columns = $this->schemaManager->listTableColumns('column_comment_test');
        self::assertCount(1, $columns);
        self::assertEmpty($columns['id']->getComment());
    }

    /** @psalm-suppress DeprecatedConstant */
    public function testAutomaticallyAppendCommentOnMarkedColumns(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (
            ! $platform->supportsInlineColumnComments() &&
            ! $platform->supportsCommentOnStatement() &&
            ! $platform instanceof SQLServerPlatform
        ) {
            self::markTestSkipped('Database does not support column comments.');
        }

        $table = new Table('column_comment_test2');
        $table->addColumn('id', Types::INTEGER, ['comment' => 'This is a comment']);
        $table->addColumn('obj', Types::OBJECT, ['comment' => 'This is a comment']);
        $table->addColumn('arr', Types::ARRAY, ['comment' => 'This is a comment']);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns('column_comment_test2');
        self::assertCount(3, $columns);
        self::assertEquals('This is a comment', $columns['id']->getComment());
        self::assertEquals('This is a comment', $columns['obj']->getComment());
        self::assertInstanceOf(ObjectType::class, $columns['obj']->getType());
        self::assertEquals('This is a comment', $columns['arr']->getComment());
        self::assertInstanceOf(ArrayType::class, $columns['arr']->getType());
    }

    public function testCommentHintOnDateIntervalTypeColumn(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (
            ! $platform->supportsInlineColumnComments() &&
            ! $platform->supportsCommentOnStatement() &&
            ! $platform instanceof SQLServerPlatform
        ) {
            self::markTestSkipped('Database does not support column comments.');
        }

        $table = new Table('column_dateinterval_comment');
        $table->addColumn('id', Types::INTEGER, ['comment' => 'This is a comment']);
        $table->addColumn('date_interval', Types::DATEINTERVAL, ['comment' => 'This is a comment']);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns('column_dateinterval_comment');
        self::assertCount(2, $columns);
        self::assertEquals('This is a comment', $columns['id']->getComment());
        self::assertEquals('This is a comment', $columns['date_interval']->getComment());
        self::assertInstanceOf(DateIntervalType::class, $columns['date_interval']->getType());
    }

    public function testChangeColumnsTypeWithDefaultValue(): void
    {
        $tableName = 'column_def_change_type';
        $table     = new Table($tableName);

        $table->addColumn('col_int', Types::SMALLINT, ['default' => 666]);
        $table->addColumn('col_string', Types::STRING, ['default' => 'foo']);

        $this->dropAndCreateTable($table);

        $newTable = clone $table;
        $newTable->changeColumn('col_int', ['type' => Type::getType(Types::INTEGER)]);
        $newTable->changeColumn('col_string', ['fixed' => true]);

        $diff = $this->schemaManager->createComparator()
            ->diffTable($table, $newTable);
        self::assertNotFalse($diff);

        $this->schemaManager->alterTable($diff);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertInstanceOf(IntegerType::class, $columns['col_int']->getType());
        self::assertEquals(666, $columns['col_int']->getDefault());

        self::assertInstanceOf(StringType::class, $columns['col_string']->getType());
        self::assertEquals('foo', $columns['col_string']->getDefault());
    }

    public function testListTableWithBlob(): void
    {
        $table = new Table('test_blob_table');
        $table->addColumn('binarydata', Types::BLOB, []);

        $this->schemaManager->createTable($table);

        $created = $this->schemaManager->introspectTable('test_blob_table');

        self::assertTrue($created->hasColumn('binarydata'));
        self::assertInstanceOf(BlobType::class, $created->getColumn('binarydata')->getType());
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
        $table = new Table($name, [], [], [], [], $options);
        $table->setSchemaConfig($this->schemaManager->createSchemaConfig());
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addColumn('test', Types::STRING, ['length' => 255]);
        $table->addColumn('foreign_key_test', Types::INTEGER);

        return $table;
    }

    protected function getTestCompositeTable(string $name): Table
    {
        $table = new Table($name, [], [], [], [], []);
        $table->setSchemaConfig($this->schemaManager->createSchemaConfig());
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('other_id', Types::INTEGER, ['notnull' => true]);
        $table->setPrimaryKey(['id', 'other_id']);
        $table->addColumn('test', Types::STRING, ['length' => 255]);

        return $table;
    }

    /** @param Table[] $tables */
    protected function assertHasTable(array $tables): void
    {
        $foundTable = false;
        foreach ($tables as $table) {
            self::assertInstanceOf(Table::class, $table, 'No Table instance was found in tables array.');
            if (strtolower($table->getName()) !== 'list_tables_test_new_name') {
                continue;
            }

            $foundTable = true;
        }

        self::assertTrue($foundTable, 'Could not find new table');
    }

    public function testListForeignKeysComposite(): void
    {
        $this->schemaManager->createTable($this->getTestTable('test_create_fk3'));
        $this->schemaManager->createTable($this->getTestCompositeTable('test_create_fk4'));

        $foreignKey = new ForeignKeyConstraint(
            ['id', 'foreign_key_test'],
            'test_create_fk4',
            ['id', 'other_id'],
            'foreign_key_test_fk2',
        );

        $this->schemaManager->createForeignKey($foreignKey, 'test_create_fk3');

        $fkeys = $this->schemaManager->listTableForeignKeys('test_create_fk3');

        self::assertCount(1, $fkeys, "Table 'test_create_fk3' has to have one foreign key.");

        self::assertInstanceOf(ForeignKeyConstraint::class, $fkeys[0]);
        self::assertEquals(['id', 'foreign_key_test'], array_map('strtolower', $fkeys[0]->getLocalColumns()));
        self::assertEquals(['id', 'other_id'], array_map('strtolower', $fkeys[0]->getForeignColumns()));
    }

    /**
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     *
     * @dataProvider \Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils::comparatorProvider
     */
    public function testColumnDefaultLifecycle(callable $comparatorFactory): void
    {
        $table = new Table('col_def_lifecycle');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('column1', Types::STRING, ['default' => null]);
        $table->addColumn('column2', Types::STRING, ['default' => false]);
        $table->addColumn('column3', Types::STRING, ['default' => true]);
        $table->addColumn('column4', Types::STRING, ['default' => 0]);
        $table->addColumn('column5', Types::STRING, ['default' => '']);
        $table->addColumn('column6', Types::STRING, ['default' => 'def']);
        $table->addColumn('column7', Types::INTEGER, ['default' => 0]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('col_def_lifecycle');

        self::assertNull($columns['id']->getDefault());
        self::assertNull($columns['column1']->getDefault());
        self::assertSame('', $columns['column2']->getDefault());
        self::assertSame('1', $columns['column3']->getDefault());
        self::assertSame('0', $columns['column4']->getDefault());
        self::assertSame('', $columns['column5']->getDefault());
        self::assertSame('def', $columns['column6']->getDefault());
        self::assertSame('0', $columns['column7']->getDefault());

        $diffTable = clone $table;

        $diffTable->changeColumn('column1', ['default' => false]);
        $diffTable->changeColumn('column2', ['default' => null]);
        $diffTable->changeColumn('column3', ['default' => false]);
        $diffTable->changeColumn('column4', ['default' => null]);
        $diffTable->changeColumn('column5', ['default' => false]);
        $diffTable->changeColumn('column6', ['default' => 666]);
        $diffTable->changeColumn('column7', ['default' => null]);

        $diff = $comparatorFactory($this->schemaManager)->diffTable($table, $diffTable);
        self::assertNotFalse($diff);

        $this->schemaManager->alterTable($diff);

        $columns = $this->schemaManager->listTableColumns('col_def_lifecycle');

        self::assertSame('', $columns['column1']->getDefault());
        self::assertNull($columns['column2']->getDefault());
        self::assertSame('', $columns['column3']->getDefault());
        self::assertNull($columns['column4']->getDefault());
        self::assertSame('', $columns['column5']->getDefault());
        self::assertSame('666', $columns['column6']->getDefault());
        self::assertNull($columns['column7']->getDefault());
    }

    public function testListTableWithBinary(): void
    {
        $tableName = 'test_binary_table';

        $table = new Table($tableName);
        $table->addColumn('column_binary', Types::BINARY, ['length' => 16, 'fixed' => true]);
        $table->addColumn('column_varbinary', Types::BINARY, ['length' => 32]);

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

        $defaultSchemaName = $this->connection->getDatabasePlatform()->getDefaultSchemaName();
        $primaryTableName  = 'primary_table';
        $foreignTableName  = 'foreign_table';

        $table = new Table($foreignTableName);
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $table = new Table($primaryTableName);
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('foo', Types::INTEGER);
        $table->addColumn('bar', Types::STRING);
        $table->addForeignKeyConstraint($foreignTableName, ['foo'], ['id']);
        $table->addIndex(['bar']);
        $table->setPrimaryKey(['id']);

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

    public function testCommentStringsAreQuoted(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (
            ! $platform->supportsInlineColumnComments() &&
            ! $platform->supportsCommentOnStatement() &&
            ! $platform instanceof SQLServerPlatform
        ) {
            self::markTestSkipped('Database does not support column comments.');
        }

        $this->dropTableIfExists('my_table');

        $table = new Table('my_table');
        $table->addColumn('id', Types::INTEGER, ['comment' => "It's a comment with a quote"]);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns('my_table');
        self::assertEquals("It's a comment with a quote", $columns['id']->getComment());
    }

    public function testCommentNotDuplicated(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsInlineColumnComments()) {
            self::markTestSkipped('Database does not support column comments.');
        }

        $this->dropTableIfExists('my_table');

        $options = [
            'type' => Type::getType(Types::INTEGER),
            'default' => 0,
            'notnull' => true,
            'comment' => 'expected+column+comment',
        ];

        $columnDefinition = substr(
            $this->connection->getDatabasePlatform()->getColumnDeclarationSQL('id', $options),
            strlen('id') + 1,
        );

        $table = new Table('my_table');
        $table->addColumn('id', Types::INTEGER, [
            'columnDefinition' => $columnDefinition,
            'comment' => 'unexpected_column_comment',
        ]);

        $sql = $this->connection->getDatabasePlatform()->getCreateTableSQL($table);

        self::assertStringContainsString('expected+column+comment', $sql[0]);
        self::assertStringNotContainsString('unexpected_column_comment', $sql[0]);
    }

    /**
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     *
     * @dataProvider getAlterColumnComment
     */
    public function testAlterColumnComment(
        callable $comparatorFactory,
        ?string $comment1,
        ?string $expectedComment1,
        ?string $comment2,
        ?string $expectedComment2
    ): void {
        $platform = $this->connection->getDatabasePlatform();

        if (
            ! $platform->supportsInlineColumnComments() &&
            ! $platform->supportsCommentOnStatement() &&
            ! $platform instanceof SQLServerPlatform
        ) {
            self::markTestSkipped('Database does not support column comments.');
        }

        $offlineTable = new Table('alter_column_comment_test');
        $offlineTable->addColumn('comment1', Types::INTEGER, ['comment' => $comment1]);
        $offlineTable->addColumn('comment2', Types::INTEGER, ['comment' => $comment2]);
        $offlineTable->addColumn('no_comment1', Types::INTEGER);
        $offlineTable->addColumn('no_comment2', Types::INTEGER);
        $this->dropAndCreateTable($offlineTable);

        $onlineTable = $this->schemaManager->introspectTable('alter_column_comment_test');

        self::assertSame($expectedComment1, $onlineTable->getColumn('comment1')->getComment());
        self::assertSame($expectedComment2, $onlineTable->getColumn('comment2')->getComment());
        self::assertNull($onlineTable->getColumn('no_comment1')->getComment());
        self::assertNull($onlineTable->getColumn('no_comment2')->getComment());

        $onlineTable->changeColumn('comment1', ['comment' => $comment2]);
        $onlineTable->changeColumn('comment2', ['comment' => $comment1]);
        $onlineTable->changeColumn('no_comment1', ['comment' => $comment1]);
        $onlineTable->changeColumn('no_comment2', ['comment' => $comment2]);

        $tableDiff = $comparatorFactory($this->schemaManager)->diffTable($offlineTable, $onlineTable);

        self::assertInstanceOf(TableDiff::class, $tableDiff);

        $this->schemaManager->alterTable($tableDiff);

        $onlineTable = $this->schemaManager->introspectTable('alter_column_comment_test');

        self::assertSame($expectedComment2, $onlineTable->getColumn('comment1')->getComment());
        self::assertSame($expectedComment1, $onlineTable->getColumn('comment2')->getComment());
        self::assertSame($expectedComment1, $onlineTable->getColumn('no_comment1')->getComment());
        self::assertSame($expectedComment2, $onlineTable->getColumn('no_comment2')->getComment());
    }

    /** @return iterable<mixed[]> */
    public static function getAlterColumnComment(): iterable
    {
        foreach (ComparatorTestUtils::comparatorProvider() as $comparatorArguments) {
            foreach (
                [
                    [null, null, ' ', ' '],
                    [null, null, '0', '0'],
                    [null, null, 'foo', 'foo'],

                    ['', null, ' ', ' '],
                    ['', null, '0', '0'],
                    ['', null, 'foo', 'foo'],

                    [' ', ' ', '0', '0'],
                    [' ', ' ', 'foo', 'foo'],

                    ['0', '0', 'foo', 'foo'],
                ] as $testArguments
            ) {
                yield array_merge($comparatorArguments, $testArguments);
            }
        }
    }

    public function testDoesNotListIndexesImplicitlyCreatedByForeignKeys(): void
    {
        $primaryTable = new Table('test_list_index_impl_primary');
        $primaryTable->addColumn('id', Types::INTEGER);
        $primaryTable->setPrimaryKey(['id']);

        $foreignTable = new Table('test_list_index_impl_foreign');
        $foreignTable->addColumn('fk1', Types::INTEGER);
        $foreignTable->addColumn('fk2', Types::INTEGER);
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

    /**
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     *
     * @dataProvider \Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils::comparatorProvider
     */
    public function testComparatorShouldNotAddCommentToJsonTypeSinceItIsTheDefaultNow(callable $comparatorFactory): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform->hasNativeJsonType()) {
            self::markTestSkipped('This test is only supported on platforms that have native JSON type.');
        }

        $this->dropTableIfExists('json_test');
        $this->connection->executeQuery('CREATE TABLE json_test (parameters JSON NOT NULL)');

        $table = new Table('json_test');
        $table->addColumn('parameters', Types::JSON);

        $tableDiff = $comparatorFactory($this->schemaManager)
            ->diffTable($this->schemaManager->introspectTable('json_test'), $table);

        self::assertFalse($tableDiff);
    }

    /** @dataProvider commentsProvider */
    public function testExtractDoctrineTypeFromComment(string $comment, string $expected, string $currentType): void
    {
        $result = $this->schemaManager->extractDoctrineTypeFromComment($comment, $currentType);

        self::assertSame($expected, $result);
    }

    /** @return string[][] */
    public static function commentsProvider(): array
    {
        $currentType = 'current type';

        return [
            'invalid custom type comments'      => ['should.return.current.type', $currentType, $currentType],
            'valid doctrine type'               => ['(DC2Type:guid)', 'guid', $currentType],
            'valid with dots'                   => ['(DC2Type:type.should.return)', 'type.should.return', $currentType],
            'valid with namespace'              => ['(DC2Type:Namespace\Class)', 'Namespace\Class', $currentType],
            'valid with extra closing bracket'  => ['(DC2Type:should.stop)).before)', 'should.stop', $currentType],
            'valid with extra opening brackets' => ['(DC2Type:should((.stop)).before)', 'should((.stop', $currentType],
        ];
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

    /**
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     *
     * @dataProvider \Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils::comparatorProvider
     */
    public function testComparisonWithAutoDetectedSequenceDefinition(callable $comparatorFactory): void
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
        } catch (DatabaseObjectNotFoundException $e) {
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

        $tableDiff = $comparatorFactory($this->schemaManager)->diffSequence($createdSequence, $sequence);

        self::assertFalse($tableDiff);
    }

    public function testPrimaryKeyAutoIncrement(): void
    {
        $table = new Table('test_pk_auto_increment');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('text', Types::STRING);
        $table->setPrimaryKey(['id']);
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
        if (! $this->connection->getDatabasePlatform()->supportsColumnLengthIndexes()) {
            self::markTestSkipped(
                'This test is only supported on platforms that support indexes with column length definitions.',
            );
        }

        $table = new Table('test_partial_column_index');
        $table->addColumn('long_column', Types::STRING, ['length' => 40]);
        $table->addColumn('standard_column', Types::INTEGER);
        $table->addIndex(['long_column'], 'partial_long_column_idx', [], ['lengths' => [4]]);
        $table->addIndex(['standard_column', 'long_column'], 'standard_and_partial_idx', [], ['lengths' => [null, 2]]);

        $expected = $table->getIndexes();

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTable('test_partial_column_index');
        self::assertEquals($expected, $onlineTable->getIndexes());
    }

    public function testCommentInTable(): void
    {
        $table = new Table('table_with_comment');
        $table->addColumn('id', Types::INTEGER);
        $table->setComment('Foo with control characters \'\\');
        $this->dropAndCreateTable($table);

        $table = $this->schemaManager->introspectTable('table_with_comment');
        self::assertSame('Foo with control characters \'\\', $table->getComment());
    }

    public function testCreatedCompositeForeignKeyOrderIsCorrectAfterCreation(): void
    {
        $foreignKey     = 'fk_test_order';
        $localTable     = 'test_table_foreign';
        $foreignTable   = 'test_table_local';
        $localColumns   = ['child_col2', 'child_col1'];
        $foreignColumns = ['col2', 'col1'];

        $this->schemaManager->tryMethod('dropTable', $foreignTable);
        $this->schemaManager->tryMethod('dropTable', $localTable);

        $table = new Table($localTable);

        $table->addColumn('col1', Types::INTEGER);
        $table->addColumn('col2', Types::INTEGER);
        $table->setPrimaryKey($foreignColumns);

        $this->schemaManager->createTable($table);

        $table = new Table($foreignTable);

        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('child_col1', Types::INTEGER);
        $table->addColumn('child_col2', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $table->addForeignKeyConstraint(
            $localTable,
            $localColumns,
            $foreignColumns,
            [],
            $foreignKey,
        );

        $this->schemaManager->createTable($table);

        $table = $this->schemaManager->introspectTable($foreignTable);

        $foreignKey = $table->getForeignKey($foreignKey);

        self::assertSame($localColumns, array_map('strtolower', $foreignKey->getLocalColumns()));
        self::assertSame($foreignColumns, array_map('strtolower', $foreignKey->getForeignColumns()));
    }

    public function testIntrospectReservedKeywordTableViaListTableDetails(): void
    {
        $this->createReservedKeywordTables();

        $user = $this->schemaManager->introspectTable('"user"');
        self::assertCount(2, $user->getColumns());
        self::assertCount(2, $user->getIndexes());
        self::assertCount(1, $user->getForeignKeys());
    }

    public function testIntrospectReservedKeywordTableViaListTables(): void
    {
        $this->createReservedKeywordTables();

        $tables = $this->schemaManager->listTables();

        $user = $this->findTableByName($tables, 'user');
        self::assertNotNull($user);
        self::assertCount(2, $user->getColumns());
        self::assertCount(2, $user->getIndexes());
        self::assertCount(1, $user->getForeignKeys());
    }

    private function createReservedKeywordTables(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $this->dropTableIfExists($platform->quoteIdentifier('user'));
        $this->dropTableIfExists($platform->quoteIdentifier('group'));

        $schema = new Schema();

        $user = $schema->createTable('user');
        $user->addColumn('id', Types::INTEGER);
        $user->addColumn('group_id', Types::INTEGER);
        $user->setPrimaryKey(['id']);
        $user->addForeignKeyConstraint('group', ['group_id'], ['id']);

        $group = $schema->createTable('group');
        $group->addColumn('id', Types::INTEGER);
        $group->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createSchemaObjects($schema);
    }

    public function testChangeIndexWithForeignKeys(): void
    {
        $this->dropTableIfExists('child');
        $this->dropTableIfExists('parent');

        $schema = new Schema();

        $parent = $schema->createTable('parent');
        $parent->addColumn('id', Types::INTEGER);
        $parent->setPrimaryKey(['id']);

        $child = $schema->createTable('child');
        $child->addColumn('id', Types::INTEGER);
        $child->addColumn('parent_id', Types::INTEGER);
        $child->addIndex(['parent_id'], 'idx_1');
        $child->addForeignKeyConstraint('parent', ['parent_id'], ['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createSchemaObjects($schema);

        $child->dropIndex('idx_1');
        $child->addIndex(['parent_id'], 'idx_2');

        $diff = $schemaManager->createComparator()->diffTable(
            $schemaManager->introspectTable('child'),
            $child,
        );
        self::assertNotFalse($diff);

        $schemaManager->alterTable($diff);

        $child = $schemaManager->introspectTable('child');

        self::assertFalse($child->hasIndex('idx_1'));
        self::assertTrue($child->hasIndex('idx_2'));
    }

    public function testSwitchPrimaryKeyOrder(): void
    {
        $prototype = new Table('test_switch_pk_order');
        $prototype->addColumn('foo_id', Types::INTEGER);
        $prototype->addColumn('bar_id', Types::INTEGER);

        $table = clone $prototype;
        $table->setPrimaryKey(['foo_id', 'bar_id']);
        $this->dropAndCreateTable($table);

        $table = clone $prototype;
        $table->setPrimaryKey(['bar_id', 'foo_id']);

        $schemaManager = $this->connection->createSchemaManager();

        $diff = $schemaManager->createComparator()->diffTable(
            $schemaManager->introspectTable('test_switch_pk_order'),
            $table,
        );
        self::assertNotFalse($diff);

        $table      = $schemaManager->introspectTable('test_switch_pk_order');
        $primaryKey = $table->getPrimaryKey();
        self::assertNotNull($primaryKey);
        self::assertSame(['foo_id', 'bar_id'], array_map('strtolower', $primaryKey->getColumns()));
    }

    public function testDropColumnWithDefault(): void
    {
        $table = new Table('drop_column_with_default');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('todrop', Types::DECIMAL, ['default' => 10.2]);

        $this->dropAndCreateTable($table);

        $table->dropColumn('todrop');

        $diff = $this->schemaManager->createComparator()
            ->diffTable(
                $this->schemaManager->introspectTable('drop_column_with_default'),
                $table,
            );
        self::assertNotFalse($diff);
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
}

interface ListTableColumnsDispatchEventListener
{
    public function onSchemaColumnDefinition(): void;
}

interface ListTableIndexesDispatchEventListener
{
    public function onSchemaIndexDefinition(): void;
}

interface CreateTableDispatchEventListener
{
    public function onSchemaCreateTable(): void;
}
