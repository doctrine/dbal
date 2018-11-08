<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\View;
use Doctrine\DBAL\Types\ArrayType;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\DateIntervalType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\ObjectType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalFunctionalTestCase;
use function array_filter;
use function array_keys;
use function array_map;
use function array_search;
use function array_values;
use function count;
use function current;
use function end;
use function explode;
use function in_array;
use function str_replace;
use function strcasecmp;
use function strlen;
use function strtolower;
use function substr;

class SchemaManagerFunctionalTestCase extends DbalFunctionalTestCase
{
    /** @var AbstractSchemaManager */
    protected $schemaManager;

    protected function getPlatformName()
    {
        $class     = static::class;
        $e         = explode('\\', $class);
        $testClass = end($e);
        return strtolower(str_replace('SchemaManagerTest', null, $testClass));
    }

    protected function setUp()
    {
        parent::setUp();

        $dbms = $this->getPlatformName();

        if ($this->connection->getDatabasePlatform()->getName() !== $dbms) {
            $this->markTestSkipped(static::class . ' requires the use of ' . $dbms);
        }

        $this->schemaManager = $this->connection->getSchemaManager();
    }


    protected function tearDown()
    {
        parent::tearDown();

        $this->schemaManager->tryMethod('dropTable', 'testschema.my_table_in_namespace');

        //TODO: SchemaDiff does not drop removed namespaces?
        try {
            //sql server versions below 2016 do not support 'IF EXISTS' so we have to catch the exception here
            $this->connection->exec('DROP SCHEMA testschema');
        } catch (DBALException $e) {
            return;
        }
    }


    /**
     * @group DBAL-1220
     */
    public function testDropsDatabaseWithActiveConnections()
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsCreateDropDatabase()) {
            $this->markTestSkipped('Cannot drop Database client side with this Driver.');
        }

        $this->schemaManager->dropAndCreateDatabase('test_drop_database');

        $knownDatabases = $this->schemaManager->listDatabases();
        if ($this->connection->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertContains('TEST_DROP_DATABASE', $knownDatabases);
        } else {
            self::assertContains('test_drop_database', $knownDatabases);
        }

        $params = $this->connection->getParams();
        if ($this->connection->getDatabasePlatform() instanceof OraclePlatform) {
            $params['user'] = 'test_drop_database';
        } else {
            $params['dbname'] = 'test_drop_database';
        }

        $user     = $params['user'] ?? null;
        $password = $params['password'] ?? null;

        $connection = $this->connection->getDriver()->connect($params, $user, $password);

        self::assertInstanceOf(Connection::class, $connection);

        $this->schemaManager->dropDatabase('test_drop_database');

        self::assertNotContains('test_drop_database', $this->schemaManager->listDatabases());
    }

    /**
     * @group DBAL-195
     */
    public function testDropAndCreateSequence()
    {
        if (! $this->connection->getDatabasePlatform()->supportsSequences()) {
            $this->markTestSkipped($this->connection->getDriver()->getName() . ' does not support sequences.');
        }

        $name = 'dropcreate_sequences_test_seq';

        $this->schemaManager->dropAndCreateSequence(new Sequence($name, 20, 10));

        self::assertTrue($this->hasElementWithName($this->schemaManager->listSequences(), $name));
    }

    /**
     * @param AbstractAsset[] $items
     */
    private function hasElementWithName(array $items, string $name) : bool
    {
        $filteredList = array_filter(
            $items,
            static function (AbstractAsset $item) use ($name) : bool {
                return $item->getShortestName($item->getNamespaceName()) === $name;
            }
        );

        return count($filteredList) === 1;
    }

    public function testListSequences()
    {
        if (! $this->connection->getDatabasePlatform()->supportsSequences()) {
            $this->markTestSkipped($this->connection->getDriver()->getName() . ' does not support sequences.');
        }

        $sequence = new Sequence('list_sequences_test_seq', 20, 10);
        $this->schemaManager->createSequence($sequence);

        $sequences = $this->schemaManager->listSequences();

        self::assertInternalType('array', $sequences, 'listSequences() should return an array.');

        $foundSequence = null;
        foreach ($sequences as $sequence) {
            self::assertInstanceOf(Sequence::class, $sequence, 'Array elements of listSequences() should be Sequence instances.');
            if (strtolower($sequence->getName()) !== 'list_sequences_test_seq') {
                continue;
            }

            $foundSequence = $sequence;
        }

        self::assertNotNull($foundSequence, "Sequence with name 'list_sequences_test_seq' was not found.");
        self::assertSame(20, $foundSequence->getAllocationSize(), 'Allocation Size is expected to be 20.');
        self::assertSame(10, $foundSequence->getInitialValue(), 'Initial Value is expected to be 10.');
    }

    public function testListDatabases()
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsCreateDropDatabase()) {
            $this->markTestSkipped('Cannot drop Database client side with this Driver.');
        }

        $this->schemaManager->dropAndCreateDatabase('test_create_database');
        $databases = $this->schemaManager->listDatabases();

        $databases = array_map('strtolower', $databases);

        self::assertContains('test_create_database', $databases);
    }

    /**
     * @group DBAL-1058
     */
    public function testListNamespaceNames()
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsSchemas()) {
            $this->markTestSkipped('Platform does not support schemas.');
        }

        // Currently dropping schemas is not supported, so we have to workaround here.
        $namespaces = $this->schemaManager->listNamespaceNames();
        $namespaces = array_map('strtolower', $namespaces);

        if (! in_array('test_create_schema', $namespaces)) {
            $this->connection->executeUpdate($this->schemaManager->getDatabasePlatform()->getCreateSchemaSQL('test_create_schema'));

            $namespaces = $this->schemaManager->listNamespaceNames();
            $namespaces = array_map('strtolower', $namespaces);
        }

        self::assertContains('test_create_schema', $namespaces);
    }

    public function testListTables()
    {
        $this->createTestTable('list_tables_test');
        $tables = $this->schemaManager->listTables();

        self::assertInternalType('array', $tables);
        self::assertTrue(count($tables) > 0, "List Tables has to find at least one table named 'list_tables_test'.");

        $foundTable = false;
        foreach ($tables as $table) {
            self::assertInstanceOf(Table::class, $table);
            if (strtolower($table->getName()) !== 'list_tables_test') {
                continue;
            }

            $foundTable = true;

            self::assertTrue($table->hasColumn('id'));
            self::assertTrue($table->hasColumn('test'));
            self::assertTrue($table->hasColumn('foreign_key_test'));
        }

        self::assertTrue($foundTable, "The 'list_tables_test' table has to be found.");
    }

    public function createListTableColumns()
    {
        $table = new Table('list_table_columns');
        $table->addColumn('id', 'integer', ['notnull' => true]);
        $table->addColumn('test', 'string', ['length' => 255, 'notnull' => false, 'default' => 'expected default']);
        $table->addColumn('foo', 'text', ['notnull' => true]);
        $table->addColumn('bar', 'decimal', ['precision' => 10, 'scale' => 4, 'notnull' => false]);
        $table->addColumn('baz1', 'datetime');
        $table->addColumn('baz2', 'time');
        $table->addColumn('baz3', 'date');
        $table->setPrimaryKey(['id']);

        return $table;
    }

    public function testListTableColumns()
    {
        $table = $this->createListTableColumns();

        $this->schemaManager->dropAndCreateTable($table);

        $columns     = $this->schemaManager->listTableColumns('list_table_columns');
        $columnsKeys = array_keys($columns);

        self::assertArrayHasKey('id', $columns);
        self::assertEquals(0, array_search('id', $columnsKeys));
        self::assertEquals('id', strtolower($columns['id']->getname()));
        self::assertInstanceOf(IntegerType::class, $columns['id']->gettype());
        self::assertEquals(false, $columns['id']->getunsigned());
        self::assertEquals(true, $columns['id']->getnotnull());
        self::assertEquals(null, $columns['id']->getdefault());
        self::assertInternalType('array', $columns['id']->getPlatformOptions());

        self::assertArrayHasKey('test', $columns);
        self::assertEquals(1, array_search('test', $columnsKeys));
        self::assertEquals('test', strtolower($columns['test']->getname()));
        self::assertInstanceOf(StringType::class, $columns['test']->gettype());
        self::assertEquals(255, $columns['test']->getlength());
        self::assertEquals(false, $columns['test']->getfixed());
        self::assertEquals(false, $columns['test']->getnotnull());
        self::assertEquals('expected default', $columns['test']->getdefault());
        self::assertInternalType('array', $columns['test']->getPlatformOptions());

        self::assertEquals('foo', strtolower($columns['foo']->getname()));
        self::assertEquals(2, array_search('foo', $columnsKeys));
        self::assertInstanceOf(TextType::class, $columns['foo']->gettype());
        self::assertEquals(false, $columns['foo']->getunsigned());
        self::assertEquals(false, $columns['foo']->getfixed());
        self::assertEquals(true, $columns['foo']->getnotnull());
        self::assertEquals(null, $columns['foo']->getdefault());
        self::assertInternalType('array', $columns['foo']->getPlatformOptions());

        self::assertEquals('bar', strtolower($columns['bar']->getname()));
        self::assertEquals(3, array_search('bar', $columnsKeys));
        self::assertInstanceOf(DecimalType::class, $columns['bar']->gettype());
        self::assertEquals(null, $columns['bar']->getlength());
        self::assertEquals(10, $columns['bar']->getprecision());
        self::assertEquals(4, $columns['bar']->getscale());
        self::assertEquals(false, $columns['bar']->getunsigned());
        self::assertEquals(false, $columns['bar']->getfixed());
        self::assertEquals(false, $columns['bar']->getnotnull());
        self::assertEquals(null, $columns['bar']->getdefault());
        self::assertInternalType('array', $columns['bar']->getPlatformOptions());

        self::assertEquals('baz1', strtolower($columns['baz1']->getname()));
        self::assertEquals(4, array_search('baz1', $columnsKeys));
        self::assertInstanceOf(DateTimeType::class, $columns['baz1']->gettype());
        self::assertEquals(true, $columns['baz1']->getnotnull());
        self::assertEquals(null, $columns['baz1']->getdefault());
        self::assertInternalType('array', $columns['baz1']->getPlatformOptions());

        self::assertEquals('baz2', strtolower($columns['baz2']->getname()));
        self::assertEquals(5, array_search('baz2', $columnsKeys));
        self::assertContains($columns['baz2']->gettype()->getName(), ['time', 'date', 'datetime']);
        self::assertEquals(true, $columns['baz2']->getnotnull());
        self::assertEquals(null, $columns['baz2']->getdefault());
        self::assertInternalType('array', $columns['baz2']->getPlatformOptions());

        self::assertEquals('baz3', strtolower($columns['baz3']->getname()));
        self::assertEquals(6, array_search('baz3', $columnsKeys));
        self::assertContains($columns['baz3']->gettype()->getName(), ['time', 'date', 'datetime']);
        self::assertEquals(true, $columns['baz3']->getnotnull());
        self::assertEquals(null, $columns['baz3']->getdefault());
        self::assertInternalType('array', $columns['baz3']->getPlatformOptions());
    }

    /**
     * @group DBAL-1078
     */
    public function testListTableColumnsWithFixedStringColumn()
    {
        $tableName = 'test_list_table_fixed_string';

        $table = new Table($tableName);
        $table->addColumn('column_char', 'string', ['fixed' => true, 'length' => 2]);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertArrayHasKey('column_char', $columns);
        self::assertInstanceOf(StringType::class, $columns['column_char']->getType());
        self::assertTrue($columns['column_char']->getFixed());
        self::assertSame(2, $columns['column_char']->getLength());
    }

    public function testListTableColumnsDispatchEvent()
    {
        $table = $this->createListTableColumns();

        $this->schemaManager->dropAndCreateTable($table);

        $listenerMock = $this
            ->getMockBuilder('ListTableColumnsDispatchEventListener')
            ->setMethods(['onSchemaColumnDefinition'])
            ->getMock();
        $listenerMock
            ->expects($this->exactly(7))
            ->method('onSchemaColumnDefinition');

        $oldEventManager = $this->schemaManager->getDatabasePlatform()->getEventManager();

        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::onSchemaColumnDefinition], $listenerMock);

        $this->schemaManager->getDatabasePlatform()->setEventManager($eventManager);

        $this->schemaManager->listTableColumns('list_table_columns');

        $this->schemaManager->getDatabasePlatform()->setEventManager($oldEventManager);
    }

    public function testListTableIndexesDispatchEvent()
    {
        $table = $this->getTestTable('list_table_indexes_test');
        $table->addUniqueIndex(['test'], 'test_index_name');
        $table->addIndex(['id', 'test'], 'test_composite_idx');

        $this->schemaManager->dropAndCreateTable($table);

        $listenerMock = $this
            ->getMockBuilder('ListTableIndexesDispatchEventListener')
            ->setMethods(['onSchemaIndexDefinition'])
            ->getMock();
        $listenerMock
            ->expects($this->exactly(3))
            ->method('onSchemaIndexDefinition');

        $oldEventManager = $this->schemaManager->getDatabasePlatform()->getEventManager();

        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::onSchemaIndexDefinition], $listenerMock);

        $this->schemaManager->getDatabasePlatform()->setEventManager($eventManager);

        $this->schemaManager->listTableIndexes('list_table_indexes_test');

        $this->schemaManager->getDatabasePlatform()->setEventManager($oldEventManager);
    }

    public function testDiffListTableColumns()
    {
        if ($this->schemaManager->getDatabasePlatform()->getName() === 'oracle') {
            $this->markTestSkipped('Does not work with Oracle, since it cannot detect DateTime, Date and Time differenecs (at the moment).');
        }

        $offlineTable = $this->createListTableColumns();
        $this->schemaManager->dropAndCreateTable($offlineTable);
        $onlineTable = $this->schemaManager->listTableDetails('list_table_columns');

        $comparator = new Comparator();
        $diff       = $comparator->diffTable($offlineTable, $onlineTable);

        self::assertFalse($diff, 'No differences should be detected with the offline vs online schema.');
    }

    public function testListTableIndexes()
    {
        $table = $this->getTestCompositeTable('list_table_indexes_test');
        $table->addUniqueIndex(['test'], 'test_index_name');
        $table->addIndex(['id', 'test'], 'test_composite_idx');

        $this->schemaManager->dropAndCreateTable($table);

        $tableIndexes = $this->schemaManager->listTableIndexes('list_table_indexes_test');

        self::assertEquals(3, count($tableIndexes));

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

    public function testDropAndCreateIndex()
    {
        $table = $this->getTestTable('test_create_index');
        $table->addUniqueIndex(['test'], 'test');
        $this->schemaManager->dropAndCreateTable($table);

        $this->schemaManager->dropAndCreateIndex($table->getIndex('test'), $table);
        $tableIndexes = $this->schemaManager->listTableIndexes('test_create_index');
        self::assertInternalType('array', $tableIndexes);

        self::assertEquals('test', strtolower($tableIndexes['test']->getName()));
        self::assertEquals(['test'], array_map('strtolower', $tableIndexes['test']->getColumns()));
        self::assertTrue($tableIndexes['test']->isUnique());
        self::assertFalse($tableIndexes['test']->isPrimary());
    }

    public function testCreateTableWithForeignKeys()
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('Platform does not support foreign keys.');
        }

        $tableB = $this->getTestTable('test_foreign');

        $this->schemaManager->dropAndCreateTable($tableB);

        $tableA = $this->getTestTable('test_create_fk');
        $tableA->addForeignKeyConstraint('test_foreign', ['foreign_key_test'], ['id']);

        $this->schemaManager->dropAndCreateTable($tableA);

        $fkTable       = $this->schemaManager->listTableDetails('test_create_fk');
        $fkConstraints = $fkTable->getForeignKeys();
        self::assertEquals(1, count($fkConstraints), "Table 'test_create_fk1' has to have one foreign key.");

        $fkConstraint = current($fkConstraints);
        self::assertInstanceOf(ForeignKeyConstraint::class, $fkConstraint);
        self::assertEquals('test_foreign', strtolower($fkConstraint->getForeignTableName()));
        self::assertEquals(['foreign_key_test'], array_map('strtolower', $fkConstraint->getColumns()));
        self::assertEquals(['id'], array_map('strtolower', $fkConstraint->getForeignColumns()));

        self::assertTrue($fkTable->columnsAreIndexed($fkConstraint->getColumns()), 'The columns of a foreign key constraint should always be indexed.');
    }

    public function testListForeignKeys()
    {
        if (! $this->connection->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('Does not support foreign key constraints.');
        }

        $this->createTestTable('test_create_fk1');
        $this->createTestTable('test_create_fk2');

        $foreignKey = new ForeignKeyConstraint(
            ['foreign_key_test'],
            'test_create_fk2',
            ['id'],
            'foreign_key_test_fk',
            ['onDelete' => 'CASCADE']
        );

        $this->schemaManager->createForeignKey($foreignKey, 'test_create_fk1');

        $fkeys = $this->schemaManager->listTableForeignKeys('test_create_fk1');

        self::assertEquals(1, count($fkeys), "Table 'test_create_fk1' has to have one foreign key.");

        self::assertInstanceOf(ForeignKeyConstraint::class, $fkeys[0]);
        self::assertEquals(['foreign_key_test'], array_map('strtolower', $fkeys[0]->getLocalColumns()));
        self::assertEquals(['id'], array_map('strtolower', $fkeys[0]->getForeignColumns()));
        self::assertEquals('test_create_fk2', strtolower($fkeys[0]->getForeignTableName()));

        if (! $fkeys[0]->hasOption('onDelete')) {
            return;
        }

        self::assertEquals('CASCADE', $fkeys[0]->getOption('onDelete'));
    }

    protected function getCreateExampleViewSql()
    {
        $this->markTestSkipped('No Create Example View SQL was defined for this SchemaManager');
    }

    public function testCreateSchema()
    {
        $this->createTestTable('test_table');

        $schema = $this->schemaManager->createSchema();
        self::assertTrue($schema->hasTable('test_table'));
    }

    public function testAlterTableScenario()
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsAlterTable()) {
            $this->markTestSkipped('Alter Table is not supported by this platform.');
        }

        $alterTable = $this->createTestTable('alter_table');
        $this->createTestTable('alter_table_foreign');

        $table = $this->schemaManager->listTableDetails('alter_table');
        self::assertTrue($table->hasColumn('id'));
        self::assertTrue($table->hasColumn('test'));
        self::assertTrue($table->hasColumn('foreign_key_test'));
        self::assertEquals(0, count($table->getForeignKeys()));
        self::assertEquals(1, count($table->getIndexes()));

        $tableDiff                         = new TableDiff('alter_table');
        $tableDiff->fromTable              = $alterTable;
        $tableDiff->addedColumns['foo']    = new Column('foo', Type::getType('integer'));
        $tableDiff->removedColumns['test'] = $table->getColumn('test');

        $this->schemaManager->alterTable($tableDiff);

        $table = $this->schemaManager->listTableDetails('alter_table');
        self::assertFalse($table->hasColumn('test'));
        self::assertTrue($table->hasColumn('foo'));

        $tableDiff                 = new TableDiff('alter_table');
        $tableDiff->fromTable      = $table;
        $tableDiff->addedIndexes[] = new Index('foo_idx', ['foo']);

        $this->schemaManager->alterTable($tableDiff);

        $table = $this->schemaManager->listTableDetails('alter_table');
        self::assertEquals(2, count($table->getIndexes()));
        self::assertTrue($table->hasIndex('foo_idx'));
        self::assertEquals(['foo'], array_map('strtolower', $table->getIndex('foo_idx')->getColumns()));
        self::assertFalse($table->getIndex('foo_idx')->isPrimary());
        self::assertFalse($table->getIndex('foo_idx')->isUnique());

        $tableDiff                   = new TableDiff('alter_table');
        $tableDiff->fromTable        = $table;
        $tableDiff->changedIndexes[] = new Index('foo_idx', ['foo', 'foreign_key_test']);

        $this->schemaManager->alterTable($tableDiff);

        $table = $this->schemaManager->listTableDetails('alter_table');
        self::assertEquals(2, count($table->getIndexes()));
        self::assertTrue($table->hasIndex('foo_idx'));
        self::assertEquals(['foo', 'foreign_key_test'], array_map('strtolower', $table->getIndex('foo_idx')->getColumns()));

        $tableDiff                            = new TableDiff('alter_table');
        $tableDiff->fromTable                 = $table;
        $tableDiff->renamedIndexes['foo_idx'] = new Index('bar_idx', ['foo', 'foreign_key_test']);

        $this->schemaManager->alterTable($tableDiff);

        $table = $this->schemaManager->listTableDetails('alter_table');
        self::assertEquals(2, count($table->getIndexes()));
        self::assertTrue($table->hasIndex('bar_idx'));
        self::assertFalse($table->hasIndex('foo_idx'));
        self::assertEquals(['foo', 'foreign_key_test'], array_map('strtolower', $table->getIndex('bar_idx')->getColumns()));
        self::assertFalse($table->getIndex('bar_idx')->isPrimary());
        self::assertFalse($table->getIndex('bar_idx')->isUnique());

        $tableDiff                     = new TableDiff('alter_table');
        $tableDiff->fromTable          = $table;
        $tableDiff->removedIndexes[]   = new Index('bar_idx', ['foo', 'foreign_key_test']);
        $fk                            = new ForeignKeyConstraint(['foreign_key_test'], 'alter_table_foreign', ['id']);
        $tableDiff->addedForeignKeys[] = $fk;

        $this->schemaManager->alterTable($tableDiff);
        $table = $this->schemaManager->listTableDetails('alter_table');

        // dont check for index size here, some platforms automatically add indexes for foreign keys.
        self::assertFalse($table->hasIndex('bar_idx'));

        if (! $this->schemaManager->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            return;
        }

        $fks = $table->getForeignKeys();
        self::assertCount(1, $fks);
        $foreignKey = current($fks);
        self::assertEquals('alter_table_foreign', strtolower($foreignKey->getForeignTableName()));
        self::assertEquals(['foreign_key_test'], array_map('strtolower', $foreignKey->getColumns()));
        self::assertEquals(['id'], array_map('strtolower', $foreignKey->getForeignColumns()));
    }


    public function testTableInNamespace()
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsSchemas()) {
            $this->markTestSkipped('Schema definition is not supported by this platform.');
        }

        //create schema
        $diff                  = new SchemaDiff();
        $diff->newNamespaces[] = 'testschema';

        foreach ($diff->toSql($this->schemaManager->getDatabasePlatform()) as $sql) {
            $this->connection->exec($sql);
        }

        //test if table is create in namespace
        $this->createTestTable('testschema.my_table_in_namespace');
        self::assertContains('testschema.my_table_in_namespace', $this->schemaManager->listTableNames());

        //tables without namespace should be created in default namespace
        //default namespaces are ignored in table listings
        $this->createTestTable('my_table_not_in_namespace');
        self::assertContains('my_table_not_in_namespace', $this->schemaManager->listTableNames());
    }

    public function testCreateAndListViews()
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsViews()) {
            $this->markTestSkipped('Views is not supported by this platform.');
        }

        $this->createTestTable('view_test_table');

        $name = 'doctrine_test_view';
        $sql  = 'SELECT * FROM view_test_table';

        $view = new View($name, $sql);

        $this->schemaManager->dropAndCreateView($view);

        self::assertTrue($this->hasElementWithName($this->schemaManager->listViews(), $name));
    }

    public function testAutoincrementDetection()
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('This test is only supported on platforms that have autoincrement');
        }

        $table = new Table('test_autoincrement');
        $table->setSchemaConfig($this->schemaManager->createSchemaConfig());
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $inferredTable = $this->schemaManager->listTableDetails('test_autoincrement');
        self::assertTrue($inferredTable->hasColumn('id'));
        self::assertTrue($inferredTable->getColumn('id')->getAutoincrement());
    }

    /**
     * @group DBAL-792
     */
    public function testAutoincrementDetectionMulticolumns()
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('This test is only supported on platforms that have autoincrement');
        }

        $table = new Table('test_not_autoincrement');
        $table->setSchemaConfig($this->schemaManager->createSchemaConfig());
        $table->addColumn('id', 'integer');
        $table->addColumn('other_id', 'integer');
        $table->setPrimaryKey(['id', 'other_id']);

        $this->schemaManager->createTable($table);

        $inferredTable = $this->schemaManager->listTableDetails('test_not_autoincrement');
        self::assertTrue($inferredTable->hasColumn('id'));
        self::assertFalse($inferredTable->getColumn('id')->getAutoincrement());
    }

    /**
     * @group DDC-887
     */
    public function testUpdateSchemaWithForeignKeyRenaming()
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('This test is only supported on platforms that have foreign keys.');
        }

        $table = new Table('test_fk_base');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);

        $tableFK = new Table('test_fk_rename');
        $tableFK->setSchemaConfig($this->schemaManager->createSchemaConfig());
        $tableFK->addColumn('id', 'integer');
        $tableFK->addColumn('fk_id', 'integer');
        $tableFK->setPrimaryKey(['id']);
        $tableFK->addIndex(['fk_id'], 'fk_idx');
        $tableFK->addForeignKeyConstraint('test_fk_base', ['fk_id'], ['id']);

        $this->schemaManager->createTable($table);
        $this->schemaManager->createTable($tableFK);

        $tableFKNew = new Table('test_fk_rename');
        $tableFKNew->setSchemaConfig($this->schemaManager->createSchemaConfig());
        $tableFKNew->addColumn('id', 'integer');
        $tableFKNew->addColumn('rename_fk_id', 'integer');
        $tableFKNew->setPrimaryKey(['id']);
        $tableFKNew->addIndex(['rename_fk_id'], 'fk_idx');
        $tableFKNew->addForeignKeyConstraint('test_fk_base', ['rename_fk_id'], ['id']);

        $c         = new Comparator();
        $tableDiff = $c->diffTable($tableFK, $tableFKNew);

        $this->schemaManager->alterTable($tableDiff);

        $table       = $this->schemaManager->listTableDetails('test_fk_rename');
        $foreignKeys = $table->getForeignKeys();

        self::assertTrue($table->hasColumn('rename_fk_id'));
        self::assertCount(1, $foreignKeys);
        self::assertSame(['rename_fk_id'], array_map('strtolower', current($foreignKeys)->getColumns()));
    }

    /**
     * @group DBAL-1062
     */
    public function testRenameIndexUsedInForeignKeyConstraint()
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('This test is only supported on platforms that have foreign keys.');
        }

        $primaryTable = new Table('test_rename_index_primary');
        $primaryTable->addColumn('id', 'integer');
        $primaryTable->setPrimaryKey(['id']);

        $foreignTable = new Table('test_rename_index_foreign');
        $foreignTable->addColumn('fk', 'integer');
        $foreignTable->addIndex(['fk'], 'rename_index_fk_idx');
        $foreignTable->addForeignKeyConstraint(
            'test_rename_index_primary',
            ['fk'],
            ['id'],
            [],
            'fk_constraint'
        );

        $this->schemaManager->dropAndCreateTable($primaryTable);
        $this->schemaManager->dropAndCreateTable($foreignTable);

        $foreignTable2 = clone $foreignTable;
        $foreignTable2->renameIndex('rename_index_fk_idx', 'renamed_index_fk_idx');

        $comparator = new Comparator();

        $this->schemaManager->alterTable($comparator->diffTable($foreignTable, $foreignTable2));

        $foreignTable = $this->schemaManager->listTableDetails('test_rename_index_foreign');

        self::assertFalse($foreignTable->hasIndex('rename_index_fk_idx'));
        self::assertTrue($foreignTable->hasIndex('renamed_index_fk_idx'));
        self::assertTrue($foreignTable->hasForeignKey('fk_constraint'));
    }

    /**
     * @group DBAL-42
     */
    public function testGetColumnComment()
    {
        if (! $this->connection->getDatabasePlatform()->supportsInlineColumnComments() &&
             ! $this->connection->getDatabasePlatform()->supportsCommentOnStatement() &&
            $this->connection->getDatabasePlatform()->getName() !== 'mssql') {
            $this->markTestSkipped('Database does not support column comments.');
        }

        $table = new Table('column_comment_test');
        $table->addColumn('id', 'integer', ['comment' => 'This is a comment']);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns('column_comment_test');
        self::assertEquals(1, count($columns));
        self::assertEquals('This is a comment', $columns['id']->getComment());

        $tableDiff                       = new TableDiff('column_comment_test');
        $tableDiff->fromTable            = $table;
        $tableDiff->changedColumns['id'] = new ColumnDiff(
            'id',
            new Column(
                'id',
                Type::getType('integer')
            ),
            ['comment'],
            new Column(
                'id',
                Type::getType('integer'),
                ['comment' => 'This is a comment']
            )
        );

        $this->schemaManager->alterTable($tableDiff);

        $columns = $this->schemaManager->listTableColumns('column_comment_test');
        self::assertEquals(1, count($columns));
        self::assertEmpty($columns['id']->getComment());
    }

    /**
     * @group DBAL-42
     */
    public function testAutomaticallyAppendCommentOnMarkedColumns()
    {
        if (! $this->connection->getDatabasePlatform()->supportsInlineColumnComments() &&
             ! $this->connection->getDatabasePlatform()->supportsCommentOnStatement() &&
            $this->connection->getDatabasePlatform()->getName() !== 'mssql') {
            $this->markTestSkipped('Database does not support column comments.');
        }

        $table = new Table('column_comment_test2');
        $table->addColumn('id', 'integer', ['comment' => 'This is a comment']);
        $table->addColumn('obj', 'object', ['comment' => 'This is a comment']);
        $table->addColumn('arr', 'array', ['comment' => 'This is a comment']);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns('column_comment_test2');
        self::assertEquals(3, count($columns));
        self::assertEquals('This is a comment', $columns['id']->getComment());
        self::assertEquals('This is a comment', $columns['obj']->getComment(), 'The Doctrine2 Typehint should be stripped from comment.');
        self::assertInstanceOf(ObjectType::class, $columns['obj']->getType(), 'The Doctrine2 should be detected from comment hint.');
        self::assertEquals('This is a comment', $columns['arr']->getComment(), 'The Doctrine2 Typehint should be stripped from comment.');
        self::assertInstanceOf(ArrayType::class, $columns['arr']->getType(), 'The Doctrine2 should be detected from comment hint.');
    }

    /**
     * @group DBAL-1228
     */
    public function testCommentHintOnDateIntervalTypeColumn()
    {
        if (! $this->connection->getDatabasePlatform()->supportsInlineColumnComments() &&
            ! $this->connection->getDatabasePlatform()->supportsCommentOnStatement() &&
            $this->connection->getDatabasePlatform()->getName() !== 'mssql') {
            $this->markTestSkipped('Database does not support column comments.');
        }

        $table = new Table('column_dateinterval_comment');
        $table->addColumn('id', 'integer', ['comment' => 'This is a comment']);
        $table->addColumn('date_interval', 'dateinterval', ['comment' => 'This is a comment']);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns('column_dateinterval_comment');
        self::assertEquals(2, count($columns));
        self::assertEquals('This is a comment', $columns['id']->getComment());
        self::assertEquals('This is a comment', $columns['date_interval']->getComment(), 'The Doctrine2 Typehint should be stripped from comment.');
        self::assertInstanceOf(DateIntervalType::class, $columns['date_interval']->getType(), 'The Doctrine2 should be detected from comment hint.');
    }

    /**
     * @group DBAL-825
     */
    public function testChangeColumnsTypeWithDefaultValue()
    {
        $tableName = 'column_def_change_type';
        $table     = new Table($tableName);

        $table->addColumn('col_int', 'smallint', ['default' => 666]);
        $table->addColumn('col_string', 'string', ['default' => 'foo']);

        $this->schemaManager->dropAndCreateTable($table);

        $tableDiff                            = new TableDiff($tableName);
        $tableDiff->fromTable                 = $table;
        $tableDiff->changedColumns['col_int'] = new ColumnDiff(
            'col_int',
            new Column('col_int', Type::getType('integer'), ['default' => 666]),
            ['type'],
            new Column('col_int', Type::getType('smallint'), ['default' => 666])
        );

        $tableDiff->changedColumns['col_string'] = new ColumnDiff(
            'col_string',
            new Column('col_string', Type::getType('string'), ['default' => 'foo', 'fixed' => true]),
            ['fixed'],
            new Column('col_string', Type::getType('string'), ['default' => 'foo'])
        );

        $this->schemaManager->alterTable($tableDiff);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertInstanceOf(IntegerType::class, $columns['col_int']->getType());
        self::assertEquals(666, $columns['col_int']->getDefault());

        self::assertInstanceOf(StringType::class, $columns['col_string']->getType());
        self::assertEquals('foo', $columns['col_string']->getDefault());
    }

    /**
     * @group DBAL-197
     */
    public function testListTableWithBlob()
    {
        $table = new Table('test_blob_table');
        $table->addColumn('id', 'integer', ['comment' => 'This is a comment']);
        $table->addColumn('binarydata', 'blob', []);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $created = $this->schemaManager->listTableDetails('test_blob_table');

        self::assertTrue($created->hasColumn('id'));
        self::assertTrue($created->hasColumn('binarydata'));
        self::assertTrue($created->hasPrimaryKey());
    }

    /**
     * @param string  $name
     * @param mixed[] $data
     *
     * @return Table
     */
    protected function createTestTable($name = 'test_table', array $data = [])
    {
        $options = $data['options'] ?? [];

        $table = $this->getTestTable($name, $options);

        $this->schemaManager->dropAndCreateTable($table);

        return $table;
    }

    protected function getTestTable($name, $options = [])
    {
        $table = new Table($name, [], [], [], false, $options);
        $table->setSchemaConfig($this->schemaManager->createSchemaConfig());
        $table->addColumn('id', 'integer', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addColumn('test', 'string', ['length' => 255]);
        $table->addColumn('foreign_key_test', 'integer');
        return $table;
    }

    protected function getTestCompositeTable($name)
    {
        $table = new Table($name, [], [], [], false, []);
        $table->setSchemaConfig($this->schemaManager->createSchemaConfig());
        $table->addColumn('id', 'integer', ['notnull' => true]);
        $table->addColumn('other_id', 'integer', ['notnull' => true]);
        $table->setPrimaryKey(['id', 'other_id']);
        $table->addColumn('test', 'string', ['length' => 255]);
        return $table;
    }

    protected function assertHasTable($tables, $tableName)
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

    public function testListForeignKeysComposite()
    {
        if (! $this->connection->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('Does not support foreign key constraints.');
        }

        $this->schemaManager->createTable($this->getTestTable('test_create_fk3'));
        $this->schemaManager->createTable($this->getTestCompositeTable('test_create_fk4'));

        $foreignKey = new ForeignKeyConstraint(
            ['id', 'foreign_key_test'],
            'test_create_fk4',
            ['id', 'other_id'],
            'foreign_key_test_fk2'
        );

        $this->schemaManager->createForeignKey($foreignKey, 'test_create_fk3');

        $fkeys = $this->schemaManager->listTableForeignKeys('test_create_fk3');

        self::assertEquals(1, count($fkeys), "Table 'test_create_fk3' has to have one foreign key.");

        self::assertInstanceOf(ForeignKeyConstraint::class, $fkeys[0]);
        self::assertEquals(['id', 'foreign_key_test'], array_map('strtolower', $fkeys[0]->getLocalColumns()));
        self::assertEquals(['id', 'other_id'], array_map('strtolower', $fkeys[0]->getForeignColumns()));
    }

    /**
     * @group DBAL-44
     */
    public function testColumnDefaultLifecycle()
    {
        $table = new Table('col_def_lifecycle');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('column1', 'string', ['default' => null]);
        $table->addColumn('column2', 'string', ['default' => false]);
        $table->addColumn('column3', 'string', ['default' => true]);
        $table->addColumn('column4', 'string', ['default' => 0]);
        $table->addColumn('column5', 'string', ['default' => '']);
        $table->addColumn('column6', 'string', ['default' => 'def']);
        $table->addColumn('column7', 'integer', ['default' => 0]);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->dropAndCreateTable($table);

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

        $comparator = new Comparator();

        $this->schemaManager->alterTable($comparator->diffTable($table, $diffTable));

        $columns = $this->schemaManager->listTableColumns('col_def_lifecycle');

        self::assertSame('', $columns['column1']->getDefault());
        self::assertNull($columns['column2']->getDefault());
        self::assertSame('', $columns['column3']->getDefault());
        self::assertNull($columns['column4']->getDefault());
        self::assertSame('', $columns['column5']->getDefault());
        self::assertSame('666', $columns['column6']->getDefault());
        self::assertNull($columns['column7']->getDefault());
    }

    public function testListTableWithBinary()
    {
        $tableName = 'test_binary_table';

        $table = new Table($tableName);
        $table->addColumn('id', 'integer');
        $table->addColumn('column_varbinary', 'binary', []);
        $table->addColumn('column_binary', 'binary', ['fixed' => true]);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $table = $this->schemaManager->listTableDetails($tableName);

        self::assertInstanceOf(BinaryType::class, $table->getColumn('column_varbinary')->getType());
        self::assertFalse($table->getColumn('column_varbinary')->getFixed());

        self::assertInstanceOf(BinaryType::class, $table->getColumn('column_binary')->getType());
        self::assertTrue($table->getColumn('column_binary')->getFixed());
    }

    public function testListTableDetailsWithFullQualifiedTableName()
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsSchemas()) {
            $this->markTestSkipped('Test only works on platforms that support schemas.');
        }

        $defaultSchemaName = $this->schemaManager->getDatabasePlatform()->getDefaultSchemaName();
        $primaryTableName  = 'primary_table';
        $foreignTableName  = 'foreign_table';

        $table = new Table($foreignTableName);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->dropAndCreateTable($table);

        $table = new Table($primaryTableName);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('foo', 'integer');
        $table->addColumn('bar', 'string');
        $table->addForeignKeyConstraint($foreignTableName, ['foo'], ['id']);
        $table->addIndex(['bar']);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->dropAndCreateTable($table);

        self::assertEquals(
            $this->schemaManager->listTableColumns($primaryTableName),
            $this->schemaManager->listTableColumns($defaultSchemaName . '.' . $primaryTableName)
        );
        self::assertEquals(
            $this->schemaManager->listTableIndexes($primaryTableName),
            $this->schemaManager->listTableIndexes($defaultSchemaName . '.' . $primaryTableName)
        );
        self::assertEquals(
            $this->schemaManager->listTableForeignKeys($primaryTableName),
            $this->schemaManager->listTableForeignKeys($defaultSchemaName . '.' . $primaryTableName)
        );
    }

    public function testCommentStringsAreQuoted()
    {
        if (! $this->connection->getDatabasePlatform()->supportsInlineColumnComments() &&
            ! $this->connection->getDatabasePlatform()->supportsCommentOnStatement() &&
            $this->connection->getDatabasePlatform()->getName() !== 'mssql') {
            $this->markTestSkipped('Database does not support column comments.');
        }

        $table = new Table('my_table');
        $table->addColumn('id', 'integer', ['comment' => "It's a comment with a quote"]);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns('my_table');
        self::assertEquals("It's a comment with a quote", $columns['id']->getComment());
    }

    public function testCommentNotDuplicated()
    {
        if (! $this->connection->getDatabasePlatform()->supportsInlineColumnComments()) {
            $this->markTestSkipped('Database does not support column comments.');
        }

        $options          = [
            'type' => Type::getType('integer'),
            'default' => 0,
            'notnull' => true,
            'comment' => 'expected+column+comment',
        ];
        $columnDefinition = substr($this->connection->getDatabasePlatform()->getColumnDeclarationSQL('id', $options), strlen('id') + 1);

        $table = new Table('my_table');
        $table->addColumn('id', 'integer', ['columnDefinition' => $columnDefinition, 'comment' => 'unexpected_column_comment']);
        $sql = $this->connection->getDatabasePlatform()->getCreateTableSQL($table);

        self::assertContains('expected+column+comment', $sql[0]);
        self::assertNotContains('unexpected_column_comment', $sql[0]);
    }

    /**
     * @group DBAL-1009
     * @dataProvider getAlterColumnComment
     */
    public function testAlterColumnComment($comment1, $expectedComment1, $comment2, $expectedComment2)
    {
        if (! $this->connection->getDatabasePlatform()->supportsInlineColumnComments() &&
            ! $this->connection->getDatabasePlatform()->supportsCommentOnStatement() &&
            $this->connection->getDatabasePlatform()->getName() !== 'mssql') {
            $this->markTestSkipped('Database does not support column comments.');
        }

        $offlineTable = new Table('alter_column_comment_test');
        $offlineTable->addColumn('comment1', 'integer', ['comment' => $comment1]);
        $offlineTable->addColumn('comment2', 'integer', ['comment' => $comment2]);
        $offlineTable->addColumn('no_comment1', 'integer');
        $offlineTable->addColumn('no_comment2', 'integer');
        $this->schemaManager->dropAndCreateTable($offlineTable);

        $onlineTable = $this->schemaManager->listTableDetails('alter_column_comment_test');

        self::assertSame($expectedComment1, $onlineTable->getColumn('comment1')->getComment());
        self::assertSame($expectedComment2, $onlineTable->getColumn('comment2')->getComment());
        self::assertNull($onlineTable->getColumn('no_comment1')->getComment());
        self::assertNull($onlineTable->getColumn('no_comment2')->getComment());

        $onlineTable->changeColumn('comment1', ['comment' => $comment2]);
        $onlineTable->changeColumn('comment2', ['comment' => $comment1]);
        $onlineTable->changeColumn('no_comment1', ['comment' => $comment1]);
        $onlineTable->changeColumn('no_comment2', ['comment' => $comment2]);

        $comparator = new Comparator();

        $tableDiff = $comparator->diffTable($offlineTable, $onlineTable);

        self::assertInstanceOf(TableDiff::class, $tableDiff);

        $this->schemaManager->alterTable($tableDiff);

        $onlineTable = $this->schemaManager->listTableDetails('alter_column_comment_test');

        self::assertSame($expectedComment2, $onlineTable->getColumn('comment1')->getComment());
        self::assertSame($expectedComment1, $onlineTable->getColumn('comment2')->getComment());
        self::assertSame($expectedComment1, $onlineTable->getColumn('no_comment1')->getComment());
        self::assertSame($expectedComment2, $onlineTable->getColumn('no_comment2')->getComment());
    }

    public function getAlterColumnComment()
    {
        return [
            [null, null, ' ', ' '],
            [null, null, '0', '0'],
            [null, null, 'foo', 'foo'],

            ['', null, ' ', ' '],
            ['', null, '0', '0'],
            ['', null, 'foo', 'foo'],

            [' ', ' ', '0', '0'],
            [' ', ' ', 'foo', 'foo'],

            ['0', '0', 'foo', 'foo'],
        ];
    }

    /**
     * @group DBAL-1095
     */
    public function testDoesNotListIndexesImplicitlyCreatedByForeignKeys()
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('This test is only supported on platforms that have foreign keys.');
        }

        $primaryTable = new Table('test_list_index_impl_primary');
        $primaryTable->addColumn('id', 'integer');
        $primaryTable->setPrimaryKey(['id']);

        $foreignTable = new Table('test_list_index_impl_foreign');
        $foreignTable->addColumn('fk1', 'integer');
        $foreignTable->addColumn('fk2', 'integer');
        $foreignTable->addIndex(['fk1'], 'explicit_fk1_idx');
        $foreignTable->addForeignKeyConstraint('test_list_index_impl_primary', ['fk1'], ['id']);
        $foreignTable->addForeignKeyConstraint('test_list_index_impl_primary', ['fk2'], ['id']);

        $this->schemaManager->dropAndCreateTable($primaryTable);
        $this->schemaManager->dropAndCreateTable($foreignTable);

        $indexes = $this->schemaManager->listTableIndexes('test_list_index_impl_foreign');

        self::assertCount(2, $indexes);
        self::assertArrayHasKey('explicit_fk1_idx', $indexes);
        self::assertArrayHasKey('idx_3d6c147fdc58d6c', $indexes);
    }

    /**
     * @after
     */
    public function removeJsonArrayTable() : void
    {
        if (! $this->schemaManager->tablesExist(['json_array_test'])) {
            return;
        }

        $this->schemaManager->dropTable('json_array_test');
    }

    /**
     * @group 2782
     * @group 6654
     */
    public function testComparatorShouldReturnFalseWhenLegacyJsonArrayColumnHasComment() : void
    {
        $table = new Table('json_array_test');
        $table->addColumn('parameters', 'json_array');

        $this->schemaManager->createTable($table);

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($this->schemaManager->listTableDetails('json_array_test'), $table);

        self::assertFalse($tableDiff);
    }

    /**
     * @group 2782
     * @group 6654
     */
    public function testComparatorShouldModifyOnlyTheCommentWhenUpdatingFromJsonArrayTypeOnLegacyPlatforms() : void
    {
        if ($this->schemaManager->getDatabasePlatform()->hasNativeJsonType()) {
            $this->markTestSkipped('This test is only supported on platforms that do not have native JSON type.');
        }

        $table = new Table('json_array_test');
        $table->addColumn('parameters', 'json_array');

        $this->schemaManager->createTable($table);

        $table = new Table('json_array_test');
        $table->addColumn('parameters', 'json');

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($this->schemaManager->listTableDetails('json_array_test'), $table);

        self::assertInstanceOf(TableDiff::class, $tableDiff);

        $changedColumn = $tableDiff->changedColumns['parameters'] ?? $tableDiff->changedColumns['PARAMETERS'];

        self::assertSame(['comment'], $changedColumn->changedProperties);
    }

    /**
     * @group 2782
     * @group 6654
     */
    public function testComparatorShouldAddCommentToLegacyJsonArrayTypeThatDoesNotHaveIt() : void
    {
        if (! $this->schemaManager->getDatabasePlatform()->hasNativeJsonType()) {
            $this->markTestSkipped('This test is only supported on platforms that have native JSON type.');
        }

        $this->connection->executeQuery('CREATE TABLE json_array_test (parameters JSON NOT NULL)');

        $table = new Table('json_array_test');
        $table->addColumn('parameters', 'json_array');

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($this->schemaManager->listTableDetails('json_array_test'), $table);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertSame(['comment'], $tableDiff->changedColumns['parameters']->changedProperties);
    }

    /**
     * @group 2782
     * @group 6654
     */
    public function testComparatorShouldReturnAllChangesWhenUsingLegacyJsonArrayType() : void
    {
        if (! $this->schemaManager->getDatabasePlatform()->hasNativeJsonType()) {
            $this->markTestSkipped('This test is only supported on platforms that have native JSON type.');
        }

        $this->connection->executeQuery('CREATE TABLE json_array_test (parameters JSON DEFAULT NULL)');

        $table = new Table('json_array_test');
        $table->addColumn('parameters', 'json_array');

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($this->schemaManager->listTableDetails('json_array_test'), $table);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertSame(['notnull', 'comment'], $tableDiff->changedColumns['parameters']->changedProperties);
    }

    /**
     * @group 2782
     * @group 6654
     */
    public function testComparatorShouldReturnAllChangesWhenUsingLegacyJsonArrayTypeEvenWhenPlatformHasJsonSupport() : void
    {
        if (! $this->schemaManager->getDatabasePlatform()->hasNativeJsonType()) {
            $this->markTestSkipped('This test is only supported on platforms that have native JSON type.');
        }

        $this->connection->executeQuery('CREATE TABLE json_array_test (parameters JSON DEFAULT NULL)');

        $table = new Table('json_array_test');
        $table->addColumn('parameters', 'json_array');

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($this->schemaManager->listTableDetails('json_array_test'), $table);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertSame(['notnull', 'comment'], $tableDiff->changedColumns['parameters']->changedProperties);
    }

    /**
     * @group 2782
     * @group 6654
     */
    public function testComparatorShouldNotAddCommentToJsonTypeSinceItIsTheDefaultNow() : void
    {
        if (! $this->schemaManager->getDatabasePlatform()->hasNativeJsonType()) {
            $this->markTestSkipped('This test is only supported on platforms that have native JSON type.');
        }

        $this->connection->executeQuery('CREATE TABLE json_test (parameters JSON NOT NULL)');

        $table = new Table('json_test');
        $table->addColumn('parameters', 'json');

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($this->schemaManager->listTableDetails('json_test'), $table);

        self::assertFalse($tableDiff);
    }

    /**
     * @dataProvider commentsProvider
     * @group 2596
     */
    public function testExtractDoctrineTypeFromComment(string $comment, string $expected, string $currentType) : void
    {
        $result = $this->schemaManager->extractDoctrineTypeFromComment($comment, $currentType);

        self::assertSame($expected, $result);
    }

    /**
     * @return string[][]
     */
    public function commentsProvider() : array
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

    public function testCreateAndListSequences() : void
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsSequences()) {
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
     * @group #3086
     */
    public function testComparisonWithAutoDetectedSequenceDefinition() : void
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsSequences()) {
            self::markTestSkipped('This test is only supported on platforms that support sequences.');
        }

        $sequenceName           = 'sequence_auto_detect_test';
        $sequenceAllocationSize = 5;
        $sequenceInitialValue   = 10;
        $sequence               = new Sequence($sequenceName, $sequenceAllocationSize, $sequenceInitialValue);

        $this->schemaManager->dropAndCreateSequence($sequence);

        $createdSequence = array_values(
            array_filter(
                $this->schemaManager->listSequences(),
                static function (Sequence $sequence) use ($sequenceName) : bool {
                    return strcasecmp($sequence->getName(), $sequenceName) === 0;
                }
            )
        )[0] ?? null;

        self::assertNotNull($createdSequence);

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffSequence($createdSequence, $sequence);

        self::assertFalse($tableDiff);
    }

    /**
     * @group DBAL-2921
     */
    public function testPrimaryKeyAutoIncrement()
    {
        $table = new Table('test_pk_auto_increment');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('text', 'string');
        $table->setPrimaryKey(['id']);
        $this->schemaManager->dropAndCreateTable($table);

        $this->connection->insert('test_pk_auto_increment', ['text' => '1']);

        $query = $this->connection->query('SELECT id FROM test_pk_auto_increment WHERE text = \'1\'');
        $query->execute();
        $lastUsedIdBeforeDelete = (int) $query->fetchColumn();

        $this->connection->query('DELETE FROM test_pk_auto_increment');

        $this->connection->insert('test_pk_auto_increment', ['text' => '2']);

        $query = $this->connection->query('SELECT id FROM test_pk_auto_increment WHERE text = \'2\'');
        $query->execute();
        $lastUsedIdAfterDelete = (int) $query->fetchColumn();

        $this->assertGreaterThan($lastUsedIdBeforeDelete, $lastUsedIdAfterDelete);
    }

    public function testGenerateAnIndexWithPartialColumnLength() : void
    {
        if (! $this->schemaManager->getDatabasePlatform()->supportsColumnLengthIndexes()) {
            self::markTestSkipped('This test is only supported on platforms that support indexes with column length definitions.');
        }

        $table = new Table('test_partial_column_index');
        $table->addColumn('long_column', 'string', ['length' => 40]);
        $table->addColumn('standard_column', 'integer');
        $table->addIndex(['long_column'], 'partial_long_column_idx', [], ['lengths' => [4]]);
        $table->addIndex(['standard_column', 'long_column'], 'standard_and_partial_idx', [], ['lengths' => [null, 2]]);

        $expected = $table->getIndexes();

        $this->schemaManager->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->listTableDetails('test_partial_column_index');
        self::assertEquals($expected, $onlineTable->getIndexes());
    }
}
