<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;

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

    /**
     * @group DBAL-1220
     */
    public function testDropsDatabaseWithActiveConnections()
    {
        if (! $this->_sm->getDatabasePlatform()->supportsCreateDropDatabase()) {
            $this->markTestSkipped('Cannot drop Database client side with this Driver.');
        }

        $this->_sm->dropAndCreateDatabase('test_drop_database');

        $this->assertContains('test_drop_database', $this->_sm->listDatabases());

        $params = $this->_conn->getParams();
        $params['dbname'] = 'test_drop_database';

        $user = isset($params['user']) ? $params['user'] : null;
        $password = isset($params['password']) ? $params['password'] : null;

        $connection = $this->_conn->getDriver()->connect($params, $user, $password);

        $this->assertInstanceOf('Doctrine\DBAL\Driver\Connection', $connection);

        $this->_sm->dropDatabase('test_drop_database');

        $this->assertNotContains('test_drop_database', $this->_sm->listDatabases());

        unset($connection);
    }

    /**
     * @group DBAL-195
     */
    public function testDropAndCreateSequence()
    {
        if(!$this->_conn->getDatabasePlatform()->supportsSequences()) {
            $this->markTestSkipped($this->_conn->getDriver()->getName().' does not support sequences.');
        }

        $sequence = new \Doctrine\DBAL\Schema\Sequence('dropcreate_sequences_test_seq', 20, 10);
        $this->_sm->dropAndCreateSequence($sequence);
    }

    public function testListSequences()
    {
        if(!$this->_conn->getDatabasePlatform()->supportsSequences()) {
            $this->markTestSkipped($this->_conn->getDriver()->getName().' does not support sequences.');
        }

        $sequence = new \Doctrine\DBAL\Schema\Sequence('list_sequences_test_seq', 20, 10);
        $this->_sm->createSequence($sequence);

        $sequences = $this->_sm->listSequences();

        $this->assertInternalType('array', $sequences, 'listSequences() should return an array.');

        $foundSequence = null;
        foreach($sequences as $sequence) {
            $this->assertInstanceOf('Doctrine\DBAL\Schema\Sequence', $sequence, 'Array elements of listSequences() should be Sequence instances.');
            if(strtolower($sequence->getName()) == 'list_sequences_test_seq') {
                $foundSequence = $sequence;
            }
        }

        $this->assertNotNull($foundSequence, "Sequence with name 'list_sequences_test_seq' was not found.");
        $this->assertEquals(20, $foundSequence->getAllocationSize(), "Allocation Size is expected to be 20.");
        $this->assertEquals(10, $foundSequence->getInitialValue(), "Initial Value is expected to be 10.");
    }

    public function testListDatabases()
    {
        if (!$this->_sm->getDatabasePlatform()->supportsCreateDropDatabase()) {
            $this->markTestSkipped('Cannot drop Database client side with this Driver.');
        }

        $this->_sm->dropAndCreateDatabase('test_create_database');
        $databases = $this->_sm->listDatabases();

        $databases = array_map('strtolower', $databases);

        $this->assertContains('test_create_database', $databases);
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

        $this->assertContains('test_create_schema', $namespaces);
    }

    public function testListTables()
    {
        $this->createTestTable('list_tables_test');
        $tables = $this->_sm->listTables();

        $this->assertInternalType('array', $tables);
        $this->assertTrue(count($tables) > 0, "List Tables has to find at least one table named 'list_tables_test'.");

        $foundTable = false;
        foreach ($tables as $table) {
            $this->assertInstanceOf('Doctrine\DBAL\Schema\Table', $table);
            if (strtolower($table->getName()) == 'list_tables_test') {
                $foundTable = true;

                $this->assertTrue($table->hasColumn('id'));
                $this->assertTrue($table->hasColumn('test'));
                $this->assertTrue($table->hasColumn('foreign_key_test'));
            }
        }

        $this->assertTrue( $foundTable , "The 'list_tables_test' table has to be found.");
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

        $this->assertArrayHasKey('id', $columns);
        $this->assertEquals('id',   strtolower($columns['id']->getname()));
        $this->assertInstanceOf('Doctrine\DBAL\Types\IntegerType', $columns['id']->gettype());
        $this->assertEquals(false,  $columns['id']->getunsigned());
        $this->assertEquals(true,   $columns['id']->getnotnull());
        $this->assertEquals(null,   $columns['id']->getdefault());
        $this->assertInternalType('array',  $columns['id']->getPlatformOptions());

        $this->assertArrayHasKey('test', $columns);
        $this->assertEquals('test', strtolower($columns['test']->getname()));
        $this->assertInstanceOf('Doctrine\DBAL\Types\StringType', $columns['test']->gettype());
        $this->assertEquals(255,    $columns['test']->getlength());
        $this->assertEquals(false,  $columns['test']->getfixed());
        $this->assertEquals(false,  $columns['test']->getnotnull());
        $this->assertEquals('expected default',   $columns['test']->getdefault());
        $this->assertInternalType('array',  $columns['test']->getPlatformOptions());

        $this->assertEquals('foo',  strtolower($columns['foo']->getname()));
        $this->assertInstanceOf('Doctrine\DBAL\Types\TextType', $columns['foo']->gettype());
        $this->assertEquals(false,  $columns['foo']->getunsigned());
        $this->assertEquals(false,  $columns['foo']->getfixed());
        $this->assertEquals(true,   $columns['foo']->getnotnull());
        $this->assertEquals(null,   $columns['foo']->getdefault());
        $this->assertInternalType('array',  $columns['foo']->getPlatformOptions());

        $this->assertEquals('bar',  strtolower($columns['bar']->getname()));
        $this->assertInstanceOf('Doctrine\DBAL\Types\DecimalType', $columns['bar']->gettype());
        $this->assertEquals(null,   $columns['bar']->getlength());
        $this->assertEquals(10,     $columns['bar']->getprecision());
        $this->assertEquals(4,      $columns['bar']->getscale());
        $this->assertEquals(false,  $columns['bar']->getunsigned());
        $this->assertEquals(false,  $columns['bar']->getfixed());
        $this->assertEquals(false,  $columns['bar']->getnotnull());
        $this->assertEquals(null,   $columns['bar']->getdefault());
        $this->assertInternalType('array',  $columns['bar']->getPlatformOptions());

        $this->assertEquals('baz1', strtolower($columns['baz1']->getname()));
        $this->assertInstanceOf('Doctrine\DBAL\Types\DateTimeType', $columns['baz1']->gettype());
        $this->assertEquals(true,   $columns['baz1']->getnotnull());
        $this->assertEquals(null,   $columns['baz1']->getdefault());
        $this->assertInternalType('array',  $columns['baz1']->getPlatformOptions());

        $this->assertEquals('baz2', strtolower($columns['baz2']->getname()));
        $this->assertContains($columns['baz2']->gettype()->getName(), array('time', 'date', 'datetime'));
        $this->assertEquals(true,   $columns['baz2']->getnotnull());
        $this->assertEquals(null,   $columns['baz2']->getdefault());
        $this->assertInternalType('array',  $columns['baz2']->getPlatformOptions());

        $this->assertEquals('baz3', strtolower($columns['baz3']->getname()));
        $this->assertContains($columns['baz2']->gettype()->getName(), array('time', 'date', 'datetime'));
        $this->assertEquals(true,   $columns['baz3']->getnotnull());
        $this->assertEquals(null,   $columns['baz3']->getdefault());
        $this->assertInternalType('array',  $columns['baz3']->getPlatformOptions());
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

        $this->assertArrayHasKey('column_char', $columns);
        $this->assertInstanceOf('Doctrine\DBAL\Types\StringType', $columns['column_char']->getType());
        $this->assertTrue($columns['column_char']->getFixed());
        $this->assertSame(2, $columns['column_char']->getLength());
    }

    public function testListTableColumnsDispatchEvent()
    {
        $table = $this->createListTableColumns();

        $this->_sm->dropAndCreateTable($table);

        $listenerMock = $this->getMock('ListTableColumnsDispatchEventListener', array('onSchemaColumnDefinition'));
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

        $listenerMock = $this->getMock('ListTableIndexesDispatchEventListener', array('onSchemaIndexDefinition'));
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

        $this->assertFalse($diff, "No differences should be detected with the offline vs online schema.");
    }

    public function testListTableIndexes()
    {
        $table = $this->getTestCompositeTable('list_table_indexes_test');
        $table->addUniqueIndex(array('test'), 'test_index_name');
        $table->addIndex(array('id', 'test'), 'test_composite_idx');

        $this->_sm->dropAndCreateTable($table);

        $tableIndexes = $this->_sm->listTableIndexes('list_table_indexes_test');

        $this->assertEquals(3, count($tableIndexes));

        $this->assertArrayHasKey('primary', $tableIndexes, 'listTableIndexes() has to return a "primary" array key.');
        $this->assertEquals(array('id', 'other_id'), array_map('strtolower', $tableIndexes['primary']->getColumns()));
        $this->assertTrue($tableIndexes['primary']->isUnique());
        $this->assertTrue($tableIndexes['primary']->isPrimary());

        $this->assertEquals('test_index_name', strtolower($tableIndexes['test_index_name']->getName()));
        $this->assertEquals(array('test'), array_map('strtolower', $tableIndexes['test_index_name']->getColumns()));
        $this->assertTrue($tableIndexes['test_index_name']->isUnique());
        $this->assertFalse($tableIndexes['test_index_name']->isPrimary());

        $this->assertEquals('test_composite_idx', strtolower($tableIndexes['test_composite_idx']->getName()));
        $this->assertEquals(array('id', 'test'), array_map('strtolower', $tableIndexes['test_composite_idx']->getColumns()));
        $this->assertFalse($tableIndexes['test_composite_idx']->isUnique());
        $this->assertFalse($tableIndexes['test_composite_idx']->isPrimary());
    }

    public function testDropAndCreateIndex()
    {
        $table = $this->getTestTable('test_create_index');
        $table->addUniqueIndex(array('test'), 'test');
        $this->_sm->dropAndCreateTable($table);

        $this->_sm->dropAndCreateIndex($table->getIndex('test'), $table);
        $tableIndexes = $this->_sm->listTableIndexes('test_create_index');
        $this->assertInternalType('array', $tableIndexes);

        $this->assertEquals('test',        strtolower($tableIndexes['test']->getName()));
        $this->assertEquals(array('test'), array_map('strtolower', $tableIndexes['test']->getColumns()));
        $this->assertTrue($tableIndexes['test']->isUnique());
        $this->assertFalse($tableIndexes['test']->isPrimary());
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
        $this->assertEquals(1, count($fkConstraints), "Table 'test_create_fk1' has to have one foreign key.");

        $fkConstraint = current($fkConstraints);
        $this->assertInstanceOf('\Doctrine\DBAL\Schema\ForeignKeyConstraint', $fkConstraint);
        $this->assertEquals('test_foreign',             strtolower($fkConstraint->getForeignTableName()));
        $this->assertEquals(array('foreign_key_test'),  array_map('strtolower', $fkConstraint->getColumns()));
        $this->assertEquals(array('id'),                array_map('strtolower', $fkConstraint->getForeignColumns()));

        $this->assertTrue($fkTable->columnsAreIndexed($fkConstraint->getColumns()), "The columns of a foreign key constraint should always be indexed.");
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

        $this->assertEquals(1, count($fkeys), "Table 'test_create_fk1' has to have one foreign key.");

        $this->assertInstanceOf('Doctrine\DBAL\Schema\ForeignKeyConstraint', $fkeys[0]);
        $this->assertEquals(array('foreign_key_test'),  array_map('strtolower', $fkeys[0]->getLocalColumns()));
        $this->assertEquals(array('id'),                array_map('strtolower', $fkeys[0]->getForeignColumns()));
        $this->assertEquals('test_create_fk2',          strtolower($fkeys[0]->getForeignTableName()));

        if($fkeys[0]->hasOption('onDelete')) {
            $this->assertEquals('CASCADE', $fkeys[0]->getOption('onDelete'));
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
        $this->assertTrue($schema->hasTable('test_table'));
    }

    public function testAlterTableScenario()
    {
        if(!$this->_sm->getDatabasePlatform()->supportsAlterTable()) {
            $this->markTestSkipped('Alter Table is not supported by this platform.');
        }

        $alterTable = $this->createTestTable('alter_table');
        $this->createTestTable('alter_table_foreign');

        $table = $this->_sm->listTableDetails('alter_table');
        $this->assertTrue($table->hasColumn('id'));
        $this->assertTrue($table->hasColumn('test'));
        $this->assertTrue($table->hasColumn('foreign_key_test'));
        $this->assertEquals(0, count($table->getForeignKeys()));
        $this->assertEquals(1, count($table->getIndexes()));

        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff("alter_table");
        $tableDiff->fromTable = $alterTable;
        $tableDiff->addedColumns['foo'] = new \Doctrine\DBAL\Schema\Column('foo', Type::getType('integer'));
        $tableDiff->removedColumns['test'] = $table->getColumn('test');

        $this->_sm->alterTable($tableDiff);

        $table = $this->_sm->listTableDetails('alter_table');
        $this->assertFalse($table->hasColumn('test'));
        $this->assertTrue($table->hasColumn('foo'));

        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff("alter_table");
        $tableDiff->fromTable = $table;
        $tableDiff->addedIndexes[] = new \Doctrine\DBAL\Schema\Index('foo_idx', array('foo'));

        $this->_sm->alterTable($tableDiff);

        $table = $this->_sm->listTableDetails('alter_table');
        $this->assertEquals(2, count($table->getIndexes()));
        $this->assertTrue($table->hasIndex('foo_idx'));
        $this->assertEquals(array('foo'), array_map('strtolower', $table->getIndex('foo_idx')->getColumns()));
        $this->assertFalse($table->getIndex('foo_idx')->isPrimary());
        $this->assertFalse($table->getIndex('foo_idx')->isUnique());

        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff("alter_table");
        $tableDiff->fromTable = $table;
        $tableDiff->changedIndexes[] = new \Doctrine\DBAL\Schema\Index('foo_idx', array('foo', 'foreign_key_test'));

        $this->_sm->alterTable($tableDiff);

        $table = $this->_sm->listTableDetails('alter_table');
        $this->assertEquals(2, count($table->getIndexes()));
        $this->assertTrue($table->hasIndex('foo_idx'));
        $this->assertEquals(array('foo', 'foreign_key_test'), array_map('strtolower', $table->getIndex('foo_idx')->getColumns()));

        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff("alter_table");
        $tableDiff->fromTable = $table;
        $tableDiff->renamedIndexes['foo_idx'] = new \Doctrine\DBAL\Schema\Index('bar_idx', array('foo', 'foreign_key_test'));

        $this->_sm->alterTable($tableDiff);

        $table = $this->_sm->listTableDetails('alter_table');
        $this->assertEquals(2, count($table->getIndexes()));
        $this->assertTrue($table->hasIndex('bar_idx'));
        $this->assertFalse($table->hasIndex('foo_idx'));
        $this->assertEquals(array('foo', 'foreign_key_test'), array_map('strtolower', $table->getIndex('bar_idx')->getColumns()));
        $this->assertFalse($table->getIndex('bar_idx')->isPrimary());
        $this->assertFalse($table->getIndex('bar_idx')->isUnique());

        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff("alter_table");
        $tableDiff->fromTable = $table;
        $tableDiff->removedIndexes[] = new \Doctrine\DBAL\Schema\Index('bar_idx', array('foo', 'foreign_key_test'));
        $fk = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(array('foreign_key_test'), 'alter_table_foreign', array('id'));
        $tableDiff->addedForeignKeys[] = $fk;

        $this->_sm->alterTable($tableDiff);
        $table = $this->_sm->listTableDetails('alter_table');

        // dont check for index size here, some platforms automatically add indexes for foreign keys.
        $this->assertFalse($table->hasIndex('bar_idx'));

        if ($this->_sm->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $fks = $table->getForeignKeys();
            $this->assertCount(1, $fks);
            $foreignKey = current($fks);
            $this->assertEquals('alter_table_foreign', strtolower($foreignKey->getForeignTableName()));
            $this->assertEquals(array('foreign_key_test'), array_map('strtolower', $foreignKey->getColumns()));
            $this->assertEquals(array('id'), array_map('strtolower', $foreignKey->getForeignColumns()));
        }
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

        $views = $this->_sm->listViews();
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
        $this->assertTrue($inferredTable->hasColumn('id'));
        $this->assertTrue($inferredTable->getColumn('id')->getAutoincrement());
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
        $this->assertTrue($inferredTable->hasColumn('id'));
        $this->assertFalse($inferredTable->getColumn('id')->getAutoincrement());
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

        $this->assertFalse($foreignTable->hasIndex('rename_index_fk_idx'));
        $this->assertTrue($foreignTable->hasIndex('renamed_index_fk_idx'));
        $this->assertTrue($foreignTable->hasForeignKey('fk_constraint'));
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
        $this->assertEquals(1, count($columns));
        $this->assertEquals('This is a comment', $columns['id']->getComment());

        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff('column_comment_test');
        $tableDiff->changedColumns['id'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'id', new \Doctrine\DBAL\Schema\Column(
                'id', \Doctrine\DBAL\Types\Type::getType('integer'), array('primary' => true)
            ),
            array('comment'),
            new \Doctrine\DBAL\Schema\Column(
                'id', \Doctrine\DBAL\Types\Type::getType('integer'), array('comment' => 'This is a comment')
            )
        );

        $this->_sm->alterTable($tableDiff);

        $columns = $this->_sm->listTableColumns("column_comment_test");
        $this->assertEquals(1, count($columns));
        $this->assertEmpty($columns['id']->getComment());
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
        $this->assertEquals(3, count($columns));
        $this->assertEquals('This is a comment', $columns['id']->getComment());
        $this->assertEquals('This is a comment', $columns['obj']->getComment(), "The Doctrine2 Typehint should be stripped from comment.");
        $this->assertInstanceOf('Doctrine\DBAL\Types\ObjectType', $columns['obj']->getType(), "The Doctrine2 should be detected from comment hint.");
        $this->assertEquals('This is a comment', $columns['arr']->getComment(), "The Doctrine2 Typehint should be stripped from comment.");
        $this->assertInstanceOf('Doctrine\DBAL\Types\ArrayType', $columns['arr']->getType(), "The Doctrine2 should be detected from comment hint.");
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
        $this->assertEquals(2, count($columns));
        $this->assertEquals('This is a comment', $columns['id']->getComment());
        $this->assertEquals('This is a comment', $columns['date_interval']->getComment(), "The Doctrine2 Typehint should be stripped from comment.");
        $this->assertInstanceOf('Doctrine\DBAL\Types\DateIntervalType', $columns['date_interval']->getType(), "The Doctrine2 should be detected from comment hint.");
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

        $this->assertInstanceOf('Doctrine\DBAL\Types\IntegerType', $columns['col_int']->getType());
        $this->assertEquals(666, $columns['col_int']->getDefault());

        $this->assertInstanceOf('Doctrine\DBAL\Types\StringType', $columns['col_string']->getType());
        $this->assertEquals('foo', $columns['col_string']->getDefault());
    }

    /**
     * @group DBAL-197
     */
    public function testListTableWithBlob()
    {
        $table = new Table('test_blob_table');
        $table->addColumn('id', 'integer', array('comment' => 'This is a comment'));
        $table->addColumn('binarydata', 'blob', array());
        $table->setPrimaryKey(array('id'));

        $this->_sm->createTable($table);
        $this->_sm->listTableDetails('test_blob_table');
    }

    /**
     * @param string $name
     * @param array $data
     * @return Table
     */
    protected function createTestTable($name = 'test_table', $data = array())
    {
        $options = array();
        if (isset($data['options'])) {
            $options = $data['options'];
        }

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
            $this->assertInstanceOf('Doctrine\DBAL\Schema\Table', $table, 'No Table instance was found in tables array.');
            if (strtolower($table->getName()) == 'list_tables_test_new_name') {
                $foundTable = true;
            }
        }
        $this->assertTrue($foundTable, "Could not find new table");
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

        $this->assertEquals(1, count($fkeys), "Table 'test_create_fk3' has to have one foreign key.");

        $this->assertInstanceOf('Doctrine\DBAL\Schema\ForeignKeyConstraint', $fkeys[0]);
        $this->assertEquals(array('id', 'foreign_key_test'), array_map('strtolower', $fkeys[0]->getLocalColumns()));
        $this->assertEquals(array('id', 'other_id'),         array_map('strtolower', $fkeys[0]->getForeignColumns()));
    }

    /**
     * @group DBAL-44
     */
    public function testColumnDefaultLifecycle()
    {
        $table = new Table("col_def_lifecycle");
        $table->addColumn('id', 'integer', array('primary' => true, 'autoincrement' => true));
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

        $this->assertNull($columns['id']->getDefault());
        $this->assertNull($columns['column1']->getDefault());
        $this->assertSame('', $columns['column2']->getDefault());
        $this->assertSame('1', $columns['column3']->getDefault());
        $this->assertSame('0', $columns['column4']->getDefault());
        $this->assertSame('', $columns['column5']->getDefault());
        $this->assertSame('def', $columns['column6']->getDefault());
        $this->assertSame('0', $columns['column7']->getDefault());

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

        $this->assertSame('', $columns['column1']->getDefault());
        $this->assertNull($columns['column2']->getDefault());
        $this->assertSame('', $columns['column3']->getDefault());
        $this->assertNull($columns['column4']->getDefault());
        $this->assertSame('', $columns['column5']->getDefault());
        $this->assertSame('666', $columns['column6']->getDefault());
        $this->assertNull($columns['column7']->getDefault());
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

        $this->assertInstanceOf('Doctrine\DBAL\Types\BinaryType', $table->getColumn('column_varbinary')->getType());
        $this->assertFalse($table->getColumn('column_varbinary')->getFixed());

        $this->assertInstanceOf('Doctrine\DBAL\Types\BinaryType', $table->getColumn('column_binary')->getType());
        $this->assertTrue($table->getColumn('column_binary')->getFixed());
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

        $this->assertEquals(
            $this->_sm->listTableColumns($primaryTableName),
            $this->_sm->listTableColumns($defaultSchemaName . '.' . $primaryTableName)
        );
        $this->assertEquals(
            $this->_sm->listTableIndexes($primaryTableName),
            $this->_sm->listTableIndexes($defaultSchemaName . '.' . $primaryTableName)
        );
        $this->assertEquals(
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
        $this->assertEquals("It's a comment with a quote", $columns['id']->getComment());
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

        $this->assertSame($expectedComment1, $onlineTable->getColumn('comment1')->getComment());
        $this->assertSame($expectedComment2, $onlineTable->getColumn('comment2')->getComment());
        $this->assertNull($onlineTable->getColumn('no_comment1')->getComment());
        $this->assertNull($onlineTable->getColumn('no_comment2')->getComment());

        $onlineTable->changeColumn('comment1', array('comment' => $comment2));
        $onlineTable->changeColumn('comment2', array('comment' => $comment1));
        $onlineTable->changeColumn('no_comment1', array('comment' => $comment1));
        $onlineTable->changeColumn('no_comment2', array('comment' => $comment2));

        $comparator = new Comparator();

        $tableDiff = $comparator->diffTable($offlineTable, $onlineTable);

        $this->assertInstanceOf('Doctrine\DBAL\Schema\TableDiff', $tableDiff);

        $this->_sm->alterTable($tableDiff);

        $onlineTable = $this->_sm->listTableDetails("alter_column_comment_test");

        $this->assertSame($expectedComment2, $onlineTable->getColumn('comment1')->getComment());
        $this->assertSame($expectedComment1, $onlineTable->getColumn('comment2')->getComment());
        $this->assertSame($expectedComment1, $onlineTable->getColumn('no_comment1')->getComment());
        $this->assertSame($expectedComment2, $onlineTable->getColumn('no_comment2')->getComment());
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

        $primaryTable = new Table('test_list_index_implicit_primary');
        $primaryTable->addColumn('id', 'integer');
        $primaryTable->setPrimaryKey(array('id'));

        $foreignTable = new Table('test_list_index_implicit_foreign');
        $foreignTable->addColumn('fk1', 'integer');
        $foreignTable->addColumn('fk2', 'integer');
        $foreignTable->addIndex(array('fk1'), 'explicit_fk1_idx');
        $foreignTable->addForeignKeyConstraint('test_list_index_implicit_primary', array('fk1'), array('id'));
        $foreignTable->addForeignKeyConstraint('test_list_index_implicit_primary', array('fk2'), array('id'));

        $this->_sm->dropAndCreateTable($primaryTable);
        $this->_sm->dropAndCreateTable($foreignTable);

        $indexes = $this->_sm->listTableIndexes('test_list_index_implicit_foreign');

        $this->assertCount(2, $indexes);
        $this->assertArrayHasKey('explicit_fk1_idx', $indexes);
        $this->assertArrayHasKey('idx_6d88c7b4fdc58d6c', $indexes);
    }
}
