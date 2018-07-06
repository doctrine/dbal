<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use function array_filter;
use function array_keys;
use function array_map;
use function array_search;
use function array_values;
use function count;
use function current;
use function end;
use function explode;
use function get_class;
use function in_array;
use function str_replace;
use function strcasecmp;
use function strlen;
use function strtolower;
use function substr;

class SchemaManagerFunctionalTestCase extends \Doctrine\Tests\DbalFunctionalTestCase
{
    /**
     * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    protected $_sm;

    protected function getPlatformName()
    {
        $class = get_class($this);
        $e = explode('\\', $class);
        $testClass = end($e);
        $dbms = strtolower(str_replace('SchemaManagerTest', null, $testClass));
        return $dbms;
    }

    protected function setUp()
    {
        parent::setUp();

        $dbms = $this->getPlatformName();

        if ($this->_conn->getDatabasePlatform()->getName() !== $dbms) {
            $this->markTestSkipped(get_class($this) . ' requires the use of ' . $dbms);
        }

        $this->_sm = $this->_conn->getSchemaManager();
    }


    protected function tearDown()
    {
        parent::tearDown();

        $this->_sm->tryMethod('dropTable', 'testschema.my_table_in_namespace');

        //TODO: SchemaDiff does not drop removed namespaces?
        try {
            //sql server versions below 2016 do not support 'IF EXISTS' so we have to catch the exception here
            $this->_conn->exec('DROP SCHEMA testschema');
        } catch (DBALException $e) {
            return;
        }
    }


    /**
     * @group DBAL-1220
     */
    public function testDropsDatabaseWithActiveConnections()
    {
        if (! $this->_sm->getDatabasePlatform()->supportsCreateDropDatabase()) {
            $this->markTestSkipped('Cannot drop Database client side with this Driver.');
        }

        $this->_sm->dropAndCreateDatabase('test_drop_database');

        $knownDatabases = $this->_sm->listDatabases();
        if ($this->_conn->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertContains('TEST_DROP_DATABASE', $knownDatabases);
        } else {
            self::assertContains('test_drop_database', $knownDatabases);
        }

        $params = $this->_conn->getParams();
        if ($this->_conn->getDatabasePlatform() instanceof OraclePlatform) {
            $params['user'] = 'test_drop_database';
        } else {
            $params['dbname'] = 'test_drop_database';
        }

        $user = $params['user'] ?? null;
        $password = $params['password'] ?? null;

        $connection = $this->_conn->getDriver()->connect($params, $user, $password);

        self::assertInstanceOf('Doctrine\DBAL\Driver\Connection', $connection);

        $this->_sm->dropDatabase('test_drop_database');

        self::assertNotContains('test_drop_database', $this->_sm->listDatabases());
    }

    /**
     * @group DBAL-195
     */
    public function testDropAndCreateSequence()
    {
        if ( ! $this->_conn->getDatabasePlatform()->supportsSequences()) {
            $this->markTestSkipped($this->_conn->getDriver()->getName().' does not support sequences.');
        }

        $name = 'dropcreate_sequences_test_seq';

        $this->_sm->dropAndCreateSequence(new Sequence($name, 20, 10));

        self::assertTrue($this->hasElementWithName($this->_sm->listSequences(), $name));
    }

    private function hasElementWithName(array $items, string $name) : bool
    {
        $filteredList = array_filter(
            $items,
            function (\Doctrine\DBAL\Schema\AbstractAsset $item) use ($name) : bool {
                return $item->getShortestName($item->getNamespaceName()) === $name;
            }
        );

        return count($filteredList) === 1;
    }

    public function testListSequences()
    {
        if (! $this->_conn->getDatabasePlatform()->supportsSequences()) {
            $this->markTestSkipped($this->_conn->getDriver()->getName() . ' does not support sequences.');
        }

        $sequence = new Sequence('list_sequences_test_seq', 20, 10);
        $this->_sm->createSequence($sequence);

        $sequences = $this->_sm->listSequences();

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
        if (!$this->_sm->getDatabasePlatform()->supportsCreateDropDatabase()) {
            $this->markTestSkipped('Cannot drop Database client side with this Driver.');
        }

        $this->_sm->dropAndCreateDatabase('test_create_database');
        $databases = $this->_sm->listDatabases();

        $databases = array_map('strtolower', $databases);

        self::assertContains('test_create_database', $databases);
    }

    /**
     * @group DBAL-1058
     */
    public function testListNamespaceNames()
    {
        if (!$this->_sm->getDatabasePlatform()->supportsSchemas()) {
            $this->markTestSkipped('Platform does not support schemas.');
        }

        // Currently dropping schemas is not supported, so we have to workaround here.
        $namespaces = $this->_sm->listNamespaceNames();
        $namespaces = array_map('strtolower', $namespaces);

        if (!in_array('test_create_schema', $namespaces)) {
            $this->_conn->executeUpdate($this->_sm->getDatabasePlatform()->getCreateSchemaSQL('test_create_schema'));

            $namespaces = $this->_sm->listNamespaceNames();
            $namespaces = array_map('strtolower', $namespaces);
        }

        self::assertContains('test_create_schema', $namespaces);
    }

    public function testListTables()
    {
        $this->createTestTable('list_tables_test');
        $tables = $this->_sm->listTables();

        self::assertInternalType('array', $tables);
        self::assertTrue(count($tables) > 0, "List Tables has to find at least one table named 'list_tables_test'.");

        $foundTable = false;
        foreach ($tables as $table) {
            self::assertInstanceOf('Doctrine\DBAL\Schema\Table', $table);
            if (strtolower($table->getName()) == 'list_tables_test') {
                $foundTable = true;

                self::assertTrue($table->hasColumn('id'));
                self::assertTrue($table->hasColumn('test'));
                self::assertTrue($table->hasColumn('foreign_key_test'));
            }
        }

        self::assertTrue( $foundTable , "The 'list_tables_test' table has to be found.");
    }

    public function createListTableColumns()
    {
        $table = new Table('list_table_columns');
        $table->addColumn('id', 'integer', array('notnull' => true));
        $table->addColumn('test', 'string', array('length' => 255, 'notnull' => false, 'default' => 'expected default'));
        $table->addColumn('foo', 'text', array('notnull' => true));
        $table->addColumn('bar', 'decimal', array('precision' => 10, 'scale' => 4, 'notnull' => false));
        $table->addColumn('baz1', 'datetime');
        $table->addColumn('baz2', 'time');
        $table->addColumn('baz3', 'date');
        $table->setPrimaryKey(array('id'));

        return $table;
    }

    public function testListTableColumns()
    {
        $table = $this->createListTableColumns();

        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns('list_table_columns');
        $columnsKeys = array_keys($columns);

        self::assertArrayHasKey('id', $columns);
        self::assertEquals(0, array_search('id', $columnsKeys));
        self::assertEquals('id',   strtolower($columns['id']->getname()));
        self::assertInstanceOf('Doctrine\DBAL\Types\IntegerType', $columns['id']->gettype());
        self::assertEquals(false,  $columns['id']->getunsigned());
        self::assertEquals(true,   $columns['id']->getnotnull());
        self::assertEquals(null,   $columns['id']->getdefault());
        self::assertInternalType('array',  $columns['id']->getPlatformOptions());

        self::assertArrayHasKey('test', $columns);
        self::assertEquals(1, array_search('test', $columnsKeys));
        self::assertEquals('test', strtolower($columns['test']->getname()));
        self::assertInstanceOf('Doctrine\DBAL\Types\StringType', $columns['test']->gettype());
        self::assertEquals(255,    $columns['test']->getlength());
        self::assertEquals(false,  $columns['test']->getfixed());
        self::assertEquals(false,  $columns['test']->getnotnull());
        self::assertEquals('expected default',   $columns['test']->getdefault());
        self::assertInternalType('array',  $columns['test']->getPlatformOptions());

        self::assertEquals('foo',  strtolower($columns['foo']->getname()));
        self::assertEquals(2, array_search('foo', $columnsKeys));
        self::assertInstanceOf('Doctrine\DBAL\Types\TextType', $columns['foo']->gettype());
        self::assertEquals(false,  $columns['foo']->getunsigned());
        self::assertEquals(false,  $columns['foo']->getfixed());
        self::assertEquals(true,   $columns['foo']->getnotnull());
        self::assertEquals(null,   $columns['foo']->getdefault());
        self::assertInternalType('array',  $columns['foo']->getPlatformOptions());

        self::assertEquals('bar',  strtolower($columns['bar']->getname()));
        self::assertEquals(3, array_search('bar', $columnsKeys));
        self::assertInstanceOf('Doctrine\DBAL\Types\DecimalType', $columns['bar']->gettype());
        self::assertEquals(null,   $columns['bar']->getlength());
        self::assertEquals(10,     $columns['bar']->getprecision());
        self::assertEquals(4,      $columns['bar']->getscale());
        self::assertEquals(false,  $columns['bar']->getunsigned());
        self::assertEquals(false,  $columns['bar']->getfixed());
        self::assertEquals(false,  $columns['bar']->getnotnull());
        self::assertEquals(null,   $columns['bar']->getdefault());
        self::assertInternalType('array',  $columns['bar']->getPlatformOptions());

        self::assertEquals('baz1', strtolower($columns['baz1']->getname()));
        self::assertEquals(4, array_search('baz1', $columnsKeys));
        self::assertInstanceOf('Doctrine\DBAL\Types\DateTimeType', $columns['baz1']->gettype());
        self::assertEquals(true,   $columns['baz1']->getnotnull());
        self::assertEquals(null,   $columns['baz1']->getdefault());
        self::assertInternalType('array',  $columns['baz1']->getPlatformOptions());

        self::assertEquals('baz2', strtolower($columns['baz2']->getname()));
        self::assertEquals(5, array_search('baz2', $columnsKeys));
        self::assertContains($columns['baz2']->gettype()->getName(), array('time', 'date', 'datetime'));
        self::assertEquals(true,   $columns['baz2']->getnotnull());
        self::assertEquals(null,   $columns['baz2']->getdefault());
        self::assertInternalType('array',  $columns['baz2']->getPlatformOptions());

        self::assertEquals('baz3', strtolower($columns['baz3']->getname()));
        self::assertEquals(6, array_search('baz3', $columnsKeys));
        self::assertContains($columns['baz3']->gettype()->getName(), array('time', 'date', 'datetime'));
        self::assertEquals(true,   $columns['baz3']->getnotnull());
        self::assertEquals(null,   $columns['baz3']->getdefault());
        self::assertInternalType('array',  $columns['baz3']->getPlatformOptions());
    }

    /**
     * @group DBAL-1078
     */
    public function testListTableColumnsWithFixedStringColumn()
    {
        $tableName = 'test_list_table_fixed_string';

        $table = new Table($tableName);
        $table->addColumn('column_char', 'string', array('fixed' => true, 'length' => 2));

        $this->_sm->createTable($table);

        $columns = $this->_sm->listTableColumns($tableName);

        self::assertArrayHasKey('column_char', $columns);
        self::assertInstanceOf('Doctrine\DBAL\Types\StringType', $columns['column_char']->getType());
        self::assertTrue($columns['column_char']->getFixed());
        self::assertSame(2, $columns['column_char']->getLength());
    }

    public function testListTableColumnsDispatchEvent()
    {
        $table = $this->createListTableColumns();

        $this->_sm->dropAndCreateTable($table);

        $listenerMock = $this
            ->getMockBuilder('ListTableColumnsDispatchEventListener')
            ->setMethods(['onSchemaColumnDefinition'])
            ->getMock();
        $listenerMock
            ->expects($this->exactly(7))
            ->method('onSchemaColumnDefinition');

        $oldEventManager = $this->_sm->getDatabasePlatform()->getEventManager();

        $eventManager = new EventManager();
        $eventManager->addEventListener(array(Events::onSchemaColumnDefinition), $listenerMock);

        $this->_sm->getDatabasePlatform()->setEventManager($eventManager);

        $this->_sm->listTableColumns('list_table_columns');

        $this->_sm->getDatabasePlatform()->setEventManager($oldEventManager);
    }

    public function testListTableIndexesDispatchEvent()
    {
        $table = $this->getTestTable('list_table_indexes_test');
        $table->addUniqueIndex(array('test'), 'test_index_name');
        $table->addIndex(array('id', 'test'), 'test_composite_idx');

        $this->_sm->dropAndCreateTable($table);

        $listenerMock = $this
            ->getMockBuilder('ListTableIndexesDispatchEventListener')
            ->setMethods(['onSchemaIndexDefinition'])
            ->getMock();
        $listenerMock
            ->expects($this->exactly(3))
            ->method('onSchemaIndexDefinition');

        $oldEventManager = $this->_sm->getDatabasePlatform()->getEventManager();

        $eventManager = new EventManager();
        $eventManager->addEventListener(array(Events::onSchemaIndexDefinition), $listenerMock);

        $this->_sm->getDatabasePlatform()->setEventManager($eventManager);

        $this->_sm->listTableIndexes('list_table_indexes_test');

        $this->_sm->getDatabasePlatform()->setEventManager($oldEventManager);
    }

    public function testDiffListTableColumns()
    {
        if ($this->_sm->getDatabasePlatform()->getName() == 'oracle') {
            $this->markTestSkipped('Does not work with Oracle, since it cannot detect DateTime, Date and Time differenecs (at the moment).');
        }

        $offlineTable = $this->createListTableColumns();
        $this->_sm->dropAndCreateTable($offlineTable);
        $onlineTable = $this->_sm->listTableDetails('list_table_columns');

        $comparator = new \Doctrine\DBAL\Schema\Comparator();
        $diff = $comparator->diffTable($offlineTable, $onlineTable);

        self::assertFalse($diff, "No differences should be detected with the offline vs online schema.");
    }

    public function testListTableIndexes()
    {
        $table = $this->getTestCompositeTable('list_table_indexes_test');
        $table->addUniqueIndex(array('test'), 'test_index_name');
        $table->addIndex(array('id', 'test'), 'test_composite_idx');

        $this->_sm->dropAndCreateTable($table);

        $tableIndexes = $this->_sm->listTableIndexes('list_table_indexes_test');

        self::assertEquals(3, count($tableIndexes));

        self::assertArrayHasKey('primary', $tableIndexes, 'listTableIndexes() has to return a "primary" array key.');
        self::assertEquals(array('id', 'other_id'), array_map('strtolower', $tableIndexes['primary']->getColumns()));
        self::assertTrue($tableIndexes['primary']->isUnique());
        self::assertTrue($tableIndexes['primary']->isPrimary());

        self::assertEquals('test_index_name', strtolower($tableIndexes['test_index_name']->getName()));
        self::assertEquals(array('test'), array_map('strtolower', $tableIndexes['test_index_name']->getColumns()));
        self::assertTrue($tableIndexes['test_index_name']->isUnique());
        self::assertFalse($tableIndexes['test_index_name']->isPrimary());

        self::assertEquals('test_composite_idx', strtolower($tableIndexes['test_composite_idx']->getName()));
        self::assertEquals(array('id', 'test'), array_map('strtolower', $tableIndexes['test_composite_idx']->getColumns()));
        self::assertFalse($tableIndexes['test_composite_idx']->isUnique());
        self::assertFalse($tableIndexes['test_composite_idx']->isPrimary());
    }

    public function testDropAndCreateIndex()
    {
        $table = $this->getTestTable('test_create_index');
        $table->addUniqueIndex(array('test'), 'test');
        $this->_sm->dropAndCreateTable($table);

        $this->_sm->dropAndCreateIndex($table->getIndex('test'), $table);
        $tableIndexes = $this->_sm->listTableIndexes('test_create_index');
        self::assertInternalType('array', $tableIndexes);

        self::assertEquals('test',        strtolower($tableIndexes['test']->getName()));
        self::assertEquals(array('test'), array_map('strtolower', $tableIndexes['test']->getColumns()));
        self::assertTrue($tableIndexes['test']->isUnique());
        self::assertFalse($tableIndexes['test']->isPrimary());
    }

    public function testCreateTableWithForeignKeys()
    {
        if(!$this->_sm->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('Platform does not support foreign keys.');
        }

        $tableB = $this->getTestTable('test_foreign');

        $this->_sm->dropAndCreateTable($tableB);

        $tableA = $this->getTestTable('test_create_fk');
        $tableA->addForeignKeyConstraint('test_foreign', array('foreign_key_test'), array('id'));

        $this->_sm->dropAndCreateTable($tableA);

        $fkTable = $this->_sm->listTableDetails('test_create_fk');
        $fkConstraints = $fkTable->getForeignKeys();
        self::assertEquals(1, count($fkConstraints), "Table 'test_create_fk1' has to have one foreign key.");

        $fkConstraint = current($fkConstraints);
        self::assertInstanceOf('\Doctrine\DBAL\Schema\ForeignKeyConstraint', $fkConstraint);
        self::assertEquals('test_foreign',             strtolower($fkConstraint->getForeignTableName()));
        self::assertEquals(array('foreign_key_test'),  array_map('strtolower', $fkConstraint->getColumns()));
        self::assertEquals(array('id'),                array_map('strtolower', $fkConstraint->getForeignColumns()));

        self::assertTrue($fkTable->columnsAreIndexed($fkConstraint->getColumns()), "The columns of a foreign key constraint should always be indexed.");
    }

    public function testListForeignKeys()
    {
        if(!$this->_conn->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('Does not support foreign key constraints.');
        }

        $this->createTestTable('test_create_fk1');
        $this->createTestTable('test_create_fk2');

        $foreignKey = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
            array('foreign_key_test'), 'test_create_fk2', array('id'), 'foreign_key_test_fk', array('onDelete' => 'CASCADE')
        );

        $this->_sm->createForeignKey($foreignKey, 'test_create_fk1');

        $fkeys = $this->_sm->listTableForeignKeys('test_create_fk1');

        self::assertEquals(1, count($fkeys), "Table 'test_create_fk1' has to have one foreign key.");

        self::assertInstanceOf('Doctrine\DBAL\Schema\ForeignKeyConstraint', $fkeys[0]);
        self::assertEquals(array('foreign_key_test'),  array_map('strtolower', $fkeys[0]->getLocalColumns()));
        self::assertEquals(array('id'),                array_map('strtolower', $fkeys[0]->getForeignColumns()));
        self::assertEquals('test_create_fk2',          strtolower($fkeys[0]->getForeignTableName()));

        if($fkeys[0]->hasOption('onDelete')) {
            self::assertEquals('CASCADE', $fkeys[0]->getOption('onDelete'));
        }
    }

    protected function getCreateExampleViewSql()
    {
        $this->markTestSkipped('No Create Example View SQL was defined for this SchemaManager');
    }

    public function testCreateSchema()
    {
        $this->createTestTable('test_table');

        $schema = $this->_sm->createSchema();
        self::assertTrue($schema->hasTable('test_table'));
    }

    public function testAlterTableScenario()
    {
        if(!$this->_sm->getDatabasePlatform()->supportsAlterTable()) {
            $this->markTestSkipped('Alter Table is not supported by this platform.');
        }

        $alterTable = $this->createTestTable('alter_table');
        $this->createTestTable('alter_table_foreign');

        $table = $this->_sm->listTableDetails('alter_table');
        self::assertTrue($table->hasColumn('id'));
        self::assertTrue($table->hasColumn('test'));
        self::assertTrue($table->hasColumn('foreign_key_test'));
        self::assertEquals(0, count($table->getForeignKeys()));
        self::assertEquals(1, count($table->getIndexes()));

        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff("alter_table");
        $tableDiff->fromTable = $alterTable;
        $tableDiff->addedColumns['foo'] = new \Doctrine\DBAL\Schema\Column('foo', Type::getType('integer'));
        $tableDiff->removedColumns['test'] = $table->getColumn('test');

        $this->_sm->alterTable($tableDiff);

        $table = $this->_sm->listTableDetails('alter_table');
        self::assertFalse($table->hasColumn('test'));
        self::assertTrue($table->hasColumn('foo'));

        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff("alter_table");
        $tableDiff->fromTable = $table;
        $tableDiff->addedIndexes[] = new \Doctrine\DBAL\Schema\Index('foo_idx', array('foo'));

        $this->_sm->alterTable($tableDiff);

        $table = $this->_sm->listTableDetails('alter_table');
        self::assertEquals(2, count($table->getIndexes()));
        self::assertTrue($table->hasIndex('foo_idx'));
        self::assertEquals(array('foo'), array_map('strtolower', $table->getIndex('foo_idx')->getColumns()));
        self::assertFalse($table->getIndex('foo_idx')->isPrimary());
        self::assertFalse($table->getIndex('foo_idx')->isUnique());

        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff("alter_table");
        $tableDiff->fromTable = $table;
        $tableDiff->changedIndexes[] = new \Doctrine\DBAL\Schema\Index('foo_idx', array('foo', 'foreign_key_test'));

        $this->_sm->alterTable($tableDiff);

        $table = $this->_sm->listTableDetails('alter_table');
        self::assertEquals(2, count($table->getIndexes()));
        self::assertTrue($table->hasIndex('foo_idx'));
        self::assertEquals(array('foo', 'foreign_key_test'), array_map('strtolower', $table->getIndex('foo_idx')->getColumns()));

        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff("alter_table");
        $tableDiff->fromTable = $table;
        $tableDiff->renamedIndexes['foo_idx'] = new \Doctrine\DBAL\Schema\Index('bar_idx', array('foo', 'foreign_key_test'));

        $this->_sm->alterTable($tableDiff);

        $table = $this->_sm->listTableDetails('alter_table');
        self::assertEquals(2, count($table->getIndexes()));
        self::assertTrue($table->hasIndex('bar_idx'));
        self::assertFalse($table->hasIndex('foo_idx'));
        self::assertEquals(array('foo', 'foreign_key_test'), array_map('strtolower', $table->getIndex('bar_idx')->getColumns()));
        self::assertFalse($table->getIndex('bar_idx')->isPrimary());
        self::assertFalse($table->getIndex('bar_idx')->isUnique());

        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff("alter_table");
        $tableDiff->fromTable = $table;
        $tableDiff->removedIndexes[] = new \Doctrine\DBAL\Schema\Index('bar_idx', array('foo', 'foreign_key_test'));
        $fk = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(array('foreign_key_test'), 'alter_table_foreign', array('id'));
        $tableDiff->addedForeignKeys[] = $fk;

        $this->_sm->alterTable($tableDiff);
        $table = $this->_sm->listTableDetails('alter_table');

        // dont check for index size here, some platforms automatically add indexes for foreign keys.
        self::assertFalse($table->hasIndex('bar_idx'));

        if ($this->_sm->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $fks = $table->getForeignKeys();
            self::assertCount(1, $fks);
            $foreignKey = current($fks);
            self::assertEquals('alter_table_foreign', strtolower($foreignKey->getForeignTableName()));
            self::assertEquals(array('foreign_key_test'), array_map('strtolower', $foreignKey->getColumns()));
            self::assertEquals(array('id'), array_map('strtolower', $foreignKey->getForeignColumns()));
        }
    }


    public function testTableInNamespace()
    {
        if (! $this->_sm->getDatabasePlatform()->supportsSchemas()) {
            $this->markTestSkipped('Schema definition is not supported by this platform.');
        }

        //create schema
        $diff                  = new SchemaDiff();
        $diff->newNamespaces[] = 'testschema';

        foreach ($diff->toSql($this->_sm->getDatabasePlatform()) as $sql) {
            $this->_conn->exec($sql);
        }

        //test if table is create in namespace
        $this->createTestTable('testschema.my_table_in_namespace');
        self::assertContains('testschema.my_table_in_namespace', $this->_sm->listTableNames());

        //tables without namespace should be created in default namespace
        //default namespaces are ignored in table listings
        $this->createTestTable('my_table_not_in_namespace');
        self::assertContains('my_table_not_in_namespace', $this->_sm->listTableNames());
    }

    public function testCreateAndListViews()
    {
        if (!$this->_sm->getDatabasePlatform()->supportsViews()) {
            $this->markTestSkipped('Views is not supported by this platform.');
        }

        $this->createTestTable('view_test_table');

        $name = "doctrine_test_view";
        $sql = "SELECT * FROM view_test_table";

        $view = new \Doctrine\DBAL\Schema\View($name, $sql);

        $this->_sm->dropAndCreateView($view);

        self::assertTrue($this->hasElementWithName($this->_sm->listViews(), $name));
    }

    public function testAutoincrementDetection()
    {
        if (!$this->_sm->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('This test is only supported on platforms that have autoincrement');
        }

        $table = new Table('test_autoincrement');
        $table->setSchemaConfig($this->_sm->createSchemaConfig());
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->setPrimaryKey(array('id'));

        $this->_sm->createTable($table);

        $inferredTable = $this->_sm->listTableDetails('test_autoincrement');
        self::assertTrue($inferredTable->hasColumn('id'));
        self::assertTrue($inferredTable->getColumn('id')->getAutoincrement());
    }

    /**
     * @group DBAL-792
     */
    public function testAutoincrementDetectionMulticolumns()
    {
        if (!$this->_sm->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('This test is only supported on platforms that have autoincrement');
        }

        $table = new Table('test_not_autoincrement');
        $table->setSchemaConfig($this->_sm->createSchemaConfig());
        $table->addColumn('id', 'integer');
        $table->addColumn('other_id', 'integer');
        $table->setPrimaryKey(array('id', 'other_id'));

        $this->_sm->createTable($table);

        $inferredTable = $this->_sm->listTableDetails('test_not_autoincrement');
        self::assertTrue($inferredTable->hasColumn('id'));
        self::assertFalse($inferredTable->getColumn('id')->getAutoincrement());
    }

    /**
     * @group DDC-887
     */
    public function testUpdateSchemaWithForeignKeyRenaming()
    {
        if (!$this->_sm->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('This test is only supported on platforms that have foreign keys.');
        }

        $table = new Table('test_fk_base');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));

        $tableFK = new Table('test_fk_rename');
        $tableFK->setSchemaConfig($this->_sm->createSchemaConfig());
        $tableFK->addColumn('id', 'integer');
        $tableFK->addColumn('fk_id', 'integer');
        $tableFK->setPrimaryKey(array('id'));
        $tableFK->addIndex(array('fk_id'), 'fk_idx');
        $tableFK->addForeignKeyConstraint('test_fk_base', array('fk_id'), array('id'));

        $this->_sm->createTable($table);
        $this->_sm->createTable($tableFK);

        $tableFKNew = new Table('test_fk_rename');
        $tableFKNew->setSchemaConfig($this->_sm->createSchemaConfig());
        $tableFKNew->addColumn('id', 'integer');
        $tableFKNew->addColumn('rename_fk_id', 'integer');
        $tableFKNew->setPrimaryKey(array('id'));
        $tableFKNew->addIndex(array('rename_fk_id'), 'fk_idx');
        $tableFKNew->addForeignKeyConstraint('test_fk_base', array('rename_fk_id'), array('id'));

        $c = new \Doctrine\DBAL\Schema\Comparator();
        $tableDiff = $c->diffTable($tableFK, $tableFKNew);

        $this->_sm->alterTable($tableDiff);

        $table       = $this->_sm->listTableDetails('test_fk_rename');
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
        if (! $this->_sm->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('This test is only supported on platforms that have foreign keys.');
        }

        $primaryTable = new Table('test_rename_index_primary');
        $primaryTable->addColumn('id', 'integer');
        $primaryTable->setPrimaryKey(array('id'));

        $foreignTable = new Table('test_rename_index_foreign');
        $foreignTable->addColumn('fk', 'integer');
        $foreignTable->addIndex(array('fk'), 'rename_index_fk_idx');
        $foreignTable->addForeignKeyConstraint(
            'test_rename_index_primary',
            array('fk'),
            array('id'),
            array(),
            'fk_constraint'
        );

        $this->_sm->dropAndCreateTable($primaryTable);
        $this->_sm->dropAndCreateTable($foreignTable);

        $foreignTable2 = clone $foreignTable;
        $foreignTable2->renameIndex('rename_index_fk_idx', 'renamed_index_fk_idx');

        $comparator = new Comparator();

        $this->_sm->alterTable($comparator->diffTable($foreignTable, $foreignTable2));

        $foreignTable = $this->_sm->listTableDetails('test_rename_index_foreign');

        self::assertFalse($foreignTable->hasIndex('rename_index_fk_idx'));
        self::assertTrue($foreignTable->hasIndex('renamed_index_fk_idx'));
        self::assertTrue($foreignTable->hasForeignKey('fk_constraint'));
    }

    /**
     * @group DBAL-42
     */
    public function testGetColumnComment()
    {
        if ( ! $this->_conn->getDatabasePlatform()->supportsInlineColumnComments() &&
             ! $this->_conn->getDatabasePlatform()->supportsCommentOnStatement() &&
            $this->_conn->getDatabasePlatform()->getName() != 'mssql') {
            $this->markTestSkipped('Database does not support column comments.');
        }

        $table = new Table('column_comment_test');
        $table->addColumn('id', 'integer', array('comment' => 'This is a comment'));
        $table->setPrimaryKey(array('id'));

        $this->_sm->createTable($table);

        $columns = $this->_sm->listTableColumns("column_comment_test");
        self::assertEquals(1, count($columns));
        self::assertEquals('This is a comment', $columns['id']->getComment());

        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff('column_comment_test');
        $tableDiff->fromTable = $table;
        $tableDiff->changedColumns['id'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'id', new \Doctrine\DBAL\Schema\Column(
                'id', \Doctrine\DBAL\Types\Type::getType('integer')
            ),
            array('comment'),
            new \Doctrine\DBAL\Schema\Column(
                'id', \Doctrine\DBAL\Types\Type::getType('integer'), array('comment' => 'This is a comment')
            )
        );

        $this->_sm->alterTable($tableDiff);

        $columns = $this->_sm->listTableColumns("column_comment_test");
        self::assertEquals(1, count($columns));
        self::assertEmpty($columns['id']->getComment());
    }

    /**
     * @group DBAL-42
     */
    public function testAutomaticallyAppendCommentOnMarkedColumns()
    {
        if ( ! $this->_conn->getDatabasePlatform()->supportsInlineColumnComments() &&
             ! $this->_conn->getDatabasePlatform()->supportsCommentOnStatement() &&
            $this->_conn->getDatabasePlatform()->getName() != 'mssql') {
            $this->markTestSkipped('Database does not support column comments.');
        }

        $table = new Table('column_comment_test2');
        $table->addColumn('id', 'integer', array('comment' => 'This is a comment'));
        $table->addColumn('obj', 'object', array('comment' => 'This is a comment'));
        $table->addColumn('arr', 'array', array('comment' => 'This is a comment'));
        $table->setPrimaryKey(array('id'));

        $this->_sm->createTable($table);

        $columns = $this->_sm->listTableColumns("column_comment_test2");
        self::assertEquals(3, count($columns));
        self::assertEquals('This is a comment', $columns['id']->getComment());
        self::assertEquals('This is a comment', $columns['obj']->getComment(), "The Doctrine2 Typehint should be stripped from comment.");
        self::assertInstanceOf('Doctrine\DBAL\Types\ObjectType', $columns['obj']->getType(), "The Doctrine2 should be detected from comment hint.");
        self::assertEquals('This is a comment', $columns['arr']->getComment(), "The Doctrine2 Typehint should be stripped from comment.");
        self::assertInstanceOf('Doctrine\DBAL\Types\ArrayType', $columns['arr']->getType(), "The Doctrine2 should be detected from comment hint.");
    }

    /**
     * @group DBAL-1228
     */
    public function testCommentHintOnDateIntervalTypeColumn()
    {
        if ( ! $this->_conn->getDatabasePlatform()->supportsInlineColumnComments() &&
            ! $this->_conn->getDatabasePlatform()->supportsCommentOnStatement() &&
            $this->_conn->getDatabasePlatform()->getName() != 'mssql') {
            $this->markTestSkipped('Database does not support column comments.');
        }

        $table = new Table('column_dateinterval_comment');
        $table->addColumn('id', 'integer', array('comment' => 'This is a comment'));
        $table->addColumn('date_interval', 'dateinterval', array('comment' => 'This is a comment'));
        $table->setPrimaryKey(array('id'));

        $this->_sm->createTable($table);

        $columns = $this->_sm->listTableColumns("column_dateinterval_comment");
        self::assertEquals(2, count($columns));
        self::assertEquals('This is a comment', $columns['id']->getComment());
        self::assertEquals('This is a comment', $columns['date_interval']->getComment(), "The Doctrine2 Typehint should be stripped from comment.");
        self::assertInstanceOf('Doctrine\DBAL\Types\DateIntervalType', $columns['date_interval']->getType(), "The Doctrine2 should be detected from comment hint.");
    }

    /**
     * @group DBAL-825
     */
    public function testChangeColumnsTypeWithDefaultValue()
    {
        $tableName = 'column_def_change_type';
        $table     = new Table($tableName);

        $table->addColumn('col_int', 'smallint', array('default' => 666));
        $table->addColumn('col_string', 'string', array('default' => 'foo'));

        $this->_sm->dropAndCreateTable($table);

        $tableDiff = new TableDiff($tableName);
        $tableDiff->fromTable = $table;
        $tableDiff->changedColumns['col_int'] = new ColumnDiff(
            'col_int',
            new Column('col_int', Type::getType('integer'), array('default' => 666)),
            array('type'),
            new Column('col_int', Type::getType('smallint'), array('default' => 666))
        );

        $tableDiff->changedColumns['col_string'] = new ColumnDiff(
            'col_string',
            new Column('col_string', Type::getType('string'), array('default' => 'foo', 'fixed' => true)),
            array('fixed'),
            new Column('col_string', Type::getType('string'), array('default' => 'foo'))
        );

        $this->_sm->alterTable($tableDiff);

        $columns = $this->_sm->listTableColumns($tableName);

        self::assertInstanceOf('Doctrine\DBAL\Types\IntegerType', $columns['col_int']->getType());
        self::assertEquals(666, $columns['col_int']->getDefault());

        self::assertInstanceOf('Doctrine\DBAL\Types\StringType', $columns['col_string']->getType());
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

        $this->_sm->createTable($table);

        $created = $this->_sm->listTableDetails('test_blob_table');

        self::assertTrue($created->hasColumn('id'));
        self::assertTrue($created->hasColumn('binarydata'));
        self::assertTrue($created->hasPrimaryKey());
    }

    /**
     * @param string $name
     * @param array  $data
     * @return Table
     */
    protected function createTestTable($name = 'test_table', $data = array())
    {
        $options = $data['options'] ?? [];

        $table = $this->getTestTable($name, $options);

        $this->_sm->dropAndCreateTable($table);

        return $table;
    }

    protected function getTestTable($name, $options=array())
    {
        $table = new Table($name, array(), array(), array(), false, $options);
        $table->setSchemaConfig($this->_sm->createSchemaConfig());
        $table->addColumn('id', 'integer', array('notnull' => true));
        $table->setPrimaryKey(array('id'));
        $table->addColumn('test', 'string', array('length' => 255));
        $table->addColumn('foreign_key_test', 'integer');
        return $table;
    }

    protected function getTestCompositeTable($name)
    {
        $table = new Table($name, array(), array(), array(), false, array());
        $table->setSchemaConfig($this->_sm->createSchemaConfig());
        $table->addColumn('id', 'integer', array('notnull' => true));
        $table->addColumn('other_id', 'integer', array('notnull' => true));
        $table->setPrimaryKey(array('id', 'other_id'));
        $table->addColumn('test', 'string', array('length' => 255));
        return $table;
    }

    protected function assertHasTable($tables, $tableName)
    {
        $foundTable = false;
        foreach ($tables as $table) {
            self::assertInstanceOf('Doctrine\DBAL\Schema\Table', $table, 'No Table instance was found in tables array.');
            if (strtolower($table->getName()) == 'list_tables_test_new_name') {
                $foundTable = true;
            }
        }
        self::assertTrue($foundTable, "Could not find new table");
    }

    public function testListForeignKeysComposite()
    {
        if(!$this->_conn->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('Does not support foreign key constraints.');
        }

        $this->_sm->createTable($this->getTestTable('test_create_fk3'));
        $this->_sm->createTable($this->getTestCompositeTable('test_create_fk4'));

        $foreignKey = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
            array('id', 'foreign_key_test'), 'test_create_fk4', array('id', 'other_id'), 'foreign_key_test_fk2'
        );

        $this->_sm->createForeignKey($foreignKey, 'test_create_fk3');

        $fkeys = $this->_sm->listTableForeignKeys('test_create_fk3');

        self::assertEquals(1, count($fkeys), "Table 'test_create_fk3' has to have one foreign key.");

        self::assertInstanceOf('Doctrine\DBAL\Schema\ForeignKeyConstraint', $fkeys[0]);
        self::assertEquals(array('id', 'foreign_key_test'), array_map('strtolower', $fkeys[0]->getLocalColumns()));
        self::assertEquals(array('id', 'other_id'),         array_map('strtolower', $fkeys[0]->getForeignColumns()));
    }

    /**
     * @group DBAL-44
     */
    public function testColumnDefaultLifecycle()
    {
        $table = new Table("col_def_lifecycle");
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('column1', 'string', array('default' => null));
        $table->addColumn('column2', 'string', array('default' => false));
        $table->addColumn('column3', 'string', array('default' => true));
        $table->addColumn('column4', 'string', array('default' => 0));
        $table->addColumn('column5', 'string', array('default' => ''));
        $table->addColumn('column6', 'string', array('default' => 'def'));
        $table->addColumn('column7', 'integer', array('default' => 0));
        $table->setPrimaryKey(array('id'));

        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns('col_def_lifecycle');

        self::assertNull($columns['id']->getDefault());
        self::assertNull($columns['column1']->getDefault());
        self::assertSame('', $columns['column2']->getDefault());
        self::assertSame('1', $columns['column3']->getDefault());
        self::assertSame('0', $columns['column4']->getDefault());
        self::assertSame('', $columns['column5']->getDefault());
        self::assertSame('def', $columns['column6']->getDefault());
        self::assertSame('0', $columns['column7']->getDefault());

        $diffTable = clone $table;

        $diffTable->changeColumn('column1', array('default' => false));
        $diffTable->changeColumn('column2', array('default' => null));
        $diffTable->changeColumn('column3', array('default' => false));
        $diffTable->changeColumn('column4', array('default' => null));
        $diffTable->changeColumn('column5', array('default' => false));
        $diffTable->changeColumn('column6', array('default' => 666));
        $diffTable->changeColumn('column7', array('default' => null));

        $comparator = new Comparator();

        $this->_sm->alterTable($comparator->diffTable($table, $diffTable));

        $columns = $this->_sm->listTableColumns('col_def_lifecycle');

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
        $table->addColumn('column_varbinary', 'binary', array());
        $table->addColumn('column_binary', 'binary', array('fixed' => true));
        $table->setPrimaryKey(array('id'));

        $this->_sm->createTable($table);

        $table = $this->_sm->listTableDetails($tableName);

        self::assertInstanceOf('Doctrine\DBAL\Types\BinaryType', $table->getColumn('column_varbinary')->getType());
        self::assertFalse($table->getColumn('column_varbinary')->getFixed());

        self::assertInstanceOf('Doctrine\DBAL\Types\BinaryType', $table->getColumn('column_binary')->getType());
        self::assertTrue($table->getColumn('column_binary')->getFixed());
    }

    public function testListTableDetailsWithFullQualifiedTableName()
    {
        if ( ! $this->_sm->getDatabasePlatform()->supportsSchemas()) {
            $this->markTestSkipped('Test only works on platforms that support schemas.');
        }

        $defaultSchemaName = $this->_sm->getDatabasePlatform()->getDefaultSchemaName();
        $primaryTableName  = 'primary_table';
        $foreignTableName  = 'foreign_table';

        $table = new Table($foreignTableName);
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->setPrimaryKey(array('id'));

        $this->_sm->dropAndCreateTable($table);

        $table = new Table($primaryTableName);
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('foo', 'integer');
        $table->addColumn('bar', 'string');
        $table->addForeignKeyConstraint($foreignTableName, array('foo'), array('id'));
        $table->addIndex(array('bar'));
        $table->setPrimaryKey(array('id'));

        $this->_sm->dropAndCreateTable($table);

        self::assertEquals(
            $this->_sm->listTableColumns($primaryTableName),
            $this->_sm->listTableColumns($defaultSchemaName . '.' . $primaryTableName)
        );
        self::assertEquals(
            $this->_sm->listTableIndexes($primaryTableName),
            $this->_sm->listTableIndexes($defaultSchemaName . '.' . $primaryTableName)
        );
        self::assertEquals(
            $this->_sm->listTableForeignKeys($primaryTableName),
            $this->_sm->listTableForeignKeys($defaultSchemaName . '.' . $primaryTableName)
        );
    }

    public function testCommentStringsAreQuoted()
    {
        if ( ! $this->_conn->getDatabasePlatform()->supportsInlineColumnComments() &&
            ! $this->_conn->getDatabasePlatform()->supportsCommentOnStatement() &&
            $this->_conn->getDatabasePlatform()->getName() != 'mssql') {
            $this->markTestSkipped('Database does not support column comments.');
        }

        $table = new Table('my_table');
        $table->addColumn('id', 'integer', array('comment' => "It's a comment with a quote"));
        $table->setPrimaryKey(array('id'));

        $this->_sm->createTable($table);

        $columns = $this->_sm->listTableColumns("my_table");
        self::assertEquals("It's a comment with a quote", $columns['id']->getComment());
    }

    public function testCommentNotDuplicated()
    {
        if ( ! $this->_conn->getDatabasePlatform()->supportsInlineColumnComments()) {
            $this->markTestSkipped('Database does not support column comments.');
        }

        $options = array(
            'type' => Type::getType('integer'),
            'default' => 0,
            'notnull' => true,
            'comment' => 'expected+column+comment',
        );
        $columnDefinition = substr($this->_conn->getDatabasePlatform()->getColumnDeclarationSQL('id', $options), strlen('id') + 1);

        $table = new Table('my_table');
        $table->addColumn('id', 'integer', array('columnDefinition' => $columnDefinition, 'comment' => 'unexpected_column_comment'));
        $sql = $this->_conn->getDatabasePlatform()->getCreateTableSQL($table);

        self::assertContains('expected+column+comment', $sql[0]);
        self::assertNotContains('unexpected_column_comment', $sql[0]);
    }

    /**
     * @group DBAL-1009
     *
     * @dataProvider getAlterColumnComment
     */
    public function testAlterColumnComment($comment1, $expectedComment1, $comment2, $expectedComment2)
    {
        if ( ! $this->_conn->getDatabasePlatform()->supportsInlineColumnComments() &&
            ! $this->_conn->getDatabasePlatform()->supportsCommentOnStatement() &&
            $this->_conn->getDatabasePlatform()->getName() != 'mssql') {
            $this->markTestSkipped('Database does not support column comments.');
        }

        $offlineTable = new Table('alter_column_comment_test');
        $offlineTable->addColumn('comment1', 'integer', array('comment' => $comment1));
        $offlineTable->addColumn('comment2', 'integer', array('comment' => $comment2));
        $offlineTable->addColumn('no_comment1', 'integer');
        $offlineTable->addColumn('no_comment2', 'integer');
        $this->_sm->dropAndCreateTable($offlineTable);

        $onlineTable = $this->_sm->listTableDetails("alter_column_comment_test");

        self::assertSame($expectedComment1, $onlineTable->getColumn('comment1')->getComment());
        self::assertSame($expectedComment2, $onlineTable->getColumn('comment2')->getComment());
        self::assertNull($onlineTable->getColumn('no_comment1')->getComment());
        self::assertNull($onlineTable->getColumn('no_comment2')->getComment());

        $onlineTable->changeColumn('comment1', array('comment' => $comment2));
        $onlineTable->changeColumn('comment2', array('comment' => $comment1));
        $onlineTable->changeColumn('no_comment1', array('comment' => $comment1));
        $onlineTable->changeColumn('no_comment2', array('comment' => $comment2));

        $comparator = new Comparator();

        $tableDiff = $comparator->diffTable($offlineTable, $onlineTable);

        self::assertInstanceOf('Doctrine\DBAL\Schema\TableDiff', $tableDiff);

        $this->_sm->alterTable($tableDiff);

        $onlineTable = $this->_sm->listTableDetails("alter_column_comment_test");

        self::assertSame($expectedComment2, $onlineTable->getColumn('comment1')->getComment());
        self::assertSame($expectedComment1, $onlineTable->getColumn('comment2')->getComment());
        self::assertSame($expectedComment1, $onlineTable->getColumn('no_comment1')->getComment());
        self::assertSame($expectedComment2, $onlineTable->getColumn('no_comment2')->getComment());
    }

    public function getAlterColumnComment()
    {
        return array(
            array(null, null, ' ', ' '),
            array(null, null, '0', '0'),
            array(null, null, 'foo', 'foo'),

            array('', null, ' ', ' '),
            array('', null, '0', '0'),
            array('', null, 'foo', 'foo'),

            array(' ', ' ', '0', '0'),
            array(' ', ' ', 'foo', 'foo'),

            array('0', '0', 'foo', 'foo'),
        );
    }

    /**
     * @group DBAL-1095
     */
    public function testDoesNotListIndexesImplicitlyCreatedByForeignKeys()
    {
        if (! $this->_sm->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('This test is only supported on platforms that have foreign keys.');
        }

        $primaryTable = new Table('test_list_index_impl_primary');
        $primaryTable->addColumn('id', 'integer');
        $primaryTable->setPrimaryKey(array('id'));

        $foreignTable = new Table('test_list_index_impl_foreign');
        $foreignTable->addColumn('fk1', 'integer');
        $foreignTable->addColumn('fk2', 'integer');
        $foreignTable->addIndex(array('fk1'), 'explicit_fk1_idx');
        $foreignTable->addForeignKeyConstraint('test_list_index_impl_primary', array('fk1'), array('id'));
        $foreignTable->addForeignKeyConstraint('test_list_index_impl_primary', array('fk2'), array('id'));

        $this->_sm->dropAndCreateTable($primaryTable);
        $this->_sm->dropAndCreateTable($foreignTable);

        $indexes = $this->_sm->listTableIndexes('test_list_index_impl_foreign');

        self::assertCount(2, $indexes);
        self::assertArrayHasKey('explicit_fk1_idx', $indexes);
        self::assertArrayHasKey('idx_3d6c147fdc58d6c', $indexes);
    }

    /**
     * @after
     */
    public function removeJsonArrayTable() : void
    {
        if ($this->_sm->tablesExist(['json_array_test'])) {
            $this->_sm->dropTable('json_array_test');
        }
    }

    /**
     * @group 2782
     * @group 6654
     */
    public function testComparatorShouldReturnFalseWhenLegacyJsonArrayColumnHasComment() : void
    {
        $table = new Table('json_array_test');
        $table->addColumn('parameters', 'json_array');

        $this->_sm->createTable($table);

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($this->_sm->listTableDetails('json_array_test'), $table);

        self::assertFalse($tableDiff);
    }

    /**
     * @group 2782
     * @group 6654
     */
    public function testComparatorShouldModifyOnlyTheCommentWhenUpdatingFromJsonArrayTypeOnLegacyPlatforms() : void
    {
        if ($this->_sm->getDatabasePlatform()->hasNativeJsonType()) {
            $this->markTestSkipped('This test is only supported on platforms that do not have native JSON type.');
        }

        $table = new Table('json_array_test');
        $table->addColumn('parameters', 'json_array');

        $this->_sm->createTable($table);

        $table = new Table('json_array_test');
        $table->addColumn('parameters', 'json');

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($this->_sm->listTableDetails('json_array_test'), $table);

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
        if ( ! $this->_sm->getDatabasePlatform()->hasNativeJsonType()) {
            $this->markTestSkipped('This test is only supported on platforms that have native JSON type.');
        }

        $this->_conn->executeQuery('CREATE TABLE json_array_test (parameters JSON NOT NULL)');

        $table = new Table('json_array_test');
        $table->addColumn('parameters', 'json_array');

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($this->_sm->listTableDetails('json_array_test'), $table);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertSame(['comment'], $tableDiff->changedColumns['parameters']->changedProperties);
    }

    /**
     * @group 2782
     * @group 6654
     */
    public function testComparatorShouldReturnAllChangesWhenUsingLegacyJsonArrayType() : void
    {
        if ( ! $this->_sm->getDatabasePlatform()->hasNativeJsonType()) {
            $this->markTestSkipped('This test is only supported on platforms that have native JSON type.');
        }

        $this->_conn->executeQuery('CREATE TABLE json_array_test (parameters JSON DEFAULT NULL)');

        $table = new Table('json_array_test');
        $table->addColumn('parameters', 'json_array');

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($this->_sm->listTableDetails('json_array_test'), $table);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertSame(['notnull', 'comment'], $tableDiff->changedColumns['parameters']->changedProperties);
    }

    /**
     * @group 2782
     * @group 6654
     */
    public function testComparatorShouldReturnAllChangesWhenUsingLegacyJsonArrayTypeEvenWhenPlatformHasJsonSupport() : void
    {
        if ( ! $this->_sm->getDatabasePlatform()->hasNativeJsonType()) {
            $this->markTestSkipped('This test is only supported on platforms that have native JSON type.');
        }

        $this->_conn->executeQuery('CREATE TABLE json_array_test (parameters JSON DEFAULT NULL)');

        $table = new Table('json_array_test');
        $table->addColumn('parameters', 'json_array');

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($this->_sm->listTableDetails('json_array_test'), $table);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertSame(['notnull', 'comment'], $tableDiff->changedColumns['parameters']->changedProperties);
    }

    /**
     * @group 2782
     * @group 6654
     */
    public function testComparatorShouldNotAddCommentToJsonTypeSinceItIsTheDefaultNow() : void
    {
        if ( ! $this->_sm->getDatabasePlatform()->hasNativeJsonType()) {
            $this->markTestSkipped('This test is only supported on platforms that have native JSON type.');
        }

        $this->_conn->executeQuery('CREATE TABLE json_test (parameters JSON NOT NULL)');

        $table = new Table('json_test');
        $table->addColumn('parameters', 'json');

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($this->_sm->listTableDetails('json_test'), $table);

        self::assertFalse($tableDiff);
    }

    /**
     * @dataProvider commentsProvider
     *
     * @group 2596
     */
    public function testExtractDoctrineTypeFromComment(string $comment, string $expected, string $currentType) : void
    {
        $result = $this->_sm->extractDoctrineTypeFromComment($comment, $currentType);

        self::assertSame($expected, $result);
    }

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
        if ( ! $this->_sm->getDatabasePlatform()->supportsSequences()) {
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

        $this->_sm->createSequence($sequence1);
        $this->_sm->createSequence($sequence2);

        /** @var Sequence[] $actualSequences */
        $actualSequences = [];
        foreach ($this->_sm->listSequences() as $sequence) {
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
        if (! $this->_sm->getDatabasePlatform()->supportsSequences()) {
            self::markTestSkipped('This test is only supported on platforms that support sequences.');
        }

        $sequenceName           = 'sequence_auto_detect_test';
        $sequenceAllocationSize = 5;
        $sequenceInitialValue   = 10;
        $sequence               = new Sequence($sequenceName, $sequenceAllocationSize, $sequenceInitialValue);

        $this->_sm->dropAndCreateSequence($sequence);

        $createdSequence = array_values(
            array_filter(
                $this->_sm->listSequences(),
                function (Sequence $sequence) use ($sequenceName) : bool {
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
        $this->_sm->dropAndCreateTable($table);

        $this->_conn->insert('test_pk_auto_increment', ['text' => '1']);

        $query = $this->_conn->query('SELECT id FROM test_pk_auto_increment WHERE text = \'1\'');
        $query->execute();
        $lastUsedIdBeforeDelete = (int) $query->fetchColumn();

        $this->_conn->query('DELETE FROM test_pk_auto_increment');

        $this->_conn->insert('test_pk_auto_increment', ['text' => '2']);

        $query = $this->_conn->query('SELECT id FROM test_pk_auto_increment WHERE text = \'2\'');
        $query->execute();
        $lastUsedIdAfterDelete = (int) $query->fetchColumn();

        $this->assertGreaterThan($lastUsedIdBeforeDelete, $lastUsedIdAfterDelete);
    }
}
