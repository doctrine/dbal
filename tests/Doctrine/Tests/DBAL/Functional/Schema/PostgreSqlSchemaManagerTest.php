<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema;
use Doctrine\DBAL\Types\Type;

require_once __DIR__ . '/../../../TestInit.php';

class PostgreSqlSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    public function tearDown()
    {
        parent::tearDown();

        if (!$this->_conn) {
            return;
        }

        $this->_conn->getConfiguration()->setFilterSchemaAssetsExpression(null);
    }

    /**
     * @group DBAL-177
     */
    public function testGetSearchPath()
    {
        $params = $this->_conn->getParams();

        $paths = $this->_sm->getSchemaSearchPaths();
        $this->assertEquals(array($params['user'], 'public'), $paths);
    }

    /**
     * @group DBAL-244
     */
    public function testGetSchemaNames()
    {
        $names = $this->_sm->getSchemaNames();

        $this->assertInternalType('array', $names);
        $this->assertTrue(count($names) > 0);
        $this->assertTrue(in_array('public', $names), "The public schema should be found.");
    }

    /**
     * @group DBAL-21
     */
    public function testSupportDomainTypeFallback()
    {
        $createDomainTypeSQL = "CREATE DOMAIN MyMoney AS DECIMAL(18,2)";
        $this->_conn->exec($createDomainTypeSQL);

        $createTableSQL = "CREATE TABLE domain_type_test (id INT PRIMARY KEY, value MyMoney)";
        $this->_conn->exec($createTableSQL);

        $table = $this->_conn->getSchemaManager()->listTableDetails('domain_type_test');
        $this->assertInstanceOf('Doctrine\DBAL\Types\DecimalType', $table->getColumn('value')->getType());

        Type::addType('MyMoney', 'Doctrine\Tests\DBAL\Functional\Schema\MoneyType');
        $this->_conn->getDatabasePlatform()->registerDoctrineTypeMapping('MyMoney', 'MyMoney');

        $table = $this->_conn->getSchemaManager()->listTableDetails('domain_type_test');
        $this->assertInstanceOf('Doctrine\Tests\DBAL\Functional\Schema\MoneyType', $table->getColumn('value')->getType());
    }

    /**
     * @group DBAL-37
     */
    public function testDetectsAutoIncrement()
    {
        $autoincTable = new \Doctrine\DBAL\Schema\Table('autoinc_table');
        $column = $autoincTable->addColumn('id', 'integer');
        $column->setAutoincrement(true);
        $this->_sm->createTable($autoincTable);
        $autoincTable = $this->_sm->listTableDetails('autoinc_table');

        $this->assertTrue($autoincTable->getColumn('id')->getAutoincrement());
    }

    /**
     * @group DBAL-37
     */
    public function testAlterTableAutoIncrementAdd()
    {
        $tableFrom = new \Doctrine\DBAL\Schema\Table('autoinc_table_add');
        $column = $tableFrom->addColumn('id', 'integer');
        $this->_sm->createTable($tableFrom);
        $tableFrom = $this->_sm->listTableDetails('autoinc_table_add');
        $this->assertFalse($tableFrom->getColumn('id')->getAutoincrement());

        $tableTo = new \Doctrine\DBAL\Schema\Table('autoinc_table_add');
        $column = $tableTo->addColumn('id', 'integer');
        $column->setAutoincrement(true);

        $c = new \Doctrine\DBAL\Schema\Comparator();
        $diff = $c->diffTable($tableFrom, $tableTo);
        $sql = $this->_conn->getDatabasePlatform()->getAlterTableSQL($diff);
        $this->assertEquals(array(
            "CREATE SEQUENCE autoinc_table_add_id_seq",
            "SELECT setval('autoinc_table_add_id_seq', (SELECT MAX(id) FROM autoinc_table_add))",
            "ALTER TABLE autoinc_table_add ALTER id SET DEFAULT nextval('autoinc_table_add_id_seq')",
        ), $sql);

        $this->_sm->alterTable($diff);
        $tableFinal = $this->_sm->listTableDetails('autoinc_table_add');
        $this->assertTrue($tableFinal->getColumn('id')->getAutoincrement());
    }

    /**
     * @group DBAL-37
     */
    public function testAlterTableAutoIncrementDrop()
    {
        $tableFrom = new \Doctrine\DBAL\Schema\Table('autoinc_table_drop');
        $column = $tableFrom->addColumn('id', 'integer');
        $column->setAutoincrement(true);
        $this->_sm->createTable($tableFrom);
        $tableFrom = $this->_sm->listTableDetails('autoinc_table_drop');
        $this->assertTrue($tableFrom->getColumn('id')->getAutoincrement());

        $tableTo = new \Doctrine\DBAL\Schema\Table('autoinc_table_drop');
        $column = $tableTo->addColumn('id', 'integer');

        $c = new \Doctrine\DBAL\Schema\Comparator();
        $diff = $c->diffTable($tableFrom, $tableTo);
        $this->assertInstanceOf('Doctrine\DBAL\Schema\TableDiff', $diff, "There should be a difference and not false being returned from the table comparison");
        $this->assertEquals(array("ALTER TABLE autoinc_table_drop ALTER id DROP DEFAULT"), $this->_conn->getDatabasePlatform()->getAlterTableSQL($diff));

        $this->_sm->alterTable($diff);
        $tableFinal = $this->_sm->listTableDetails('autoinc_table_drop');
        $this->assertFalse($tableFinal->getColumn('id')->getAutoincrement());
    }

    /**
     * @group DBAL-75
     */
    public function testTableWithSchema()
    {
        $this->_conn->exec('CREATE SCHEMA nested');

        $nestedRelatedTable = new \Doctrine\DBAL\Schema\Table('nested.schemarelated');
        $column = $nestedRelatedTable->addColumn('id', 'integer');
        $column->setAutoincrement(true);
        $nestedRelatedTable->setPrimaryKey(array('id'));

        $nestedSchemaTable = new \Doctrine\DBAL\Schema\Table('nested.schematable');
        $column = $nestedSchemaTable->addColumn('id', 'integer');
        $column->setAutoincrement(true);
        $nestedSchemaTable->setPrimaryKey(array('id'));
        $nestedSchemaTable->addUnnamedForeignKeyConstraint($nestedRelatedTable, array('id'), array('id'));

        $this->_sm->createTable($nestedRelatedTable);
        $this->_sm->createTable($nestedSchemaTable);

        $tables = $this->_sm->listTableNames();
        $this->assertContains('nested.schematable', $tables, "The table should be detected with its non-public schema.");

        $nestedSchemaTable = $this->_sm->listTableDetails('nested.schematable');
        $this->assertTrue($nestedSchemaTable->hasColumn('id'));
        $this->assertEquals(array('id'), $nestedSchemaTable->getPrimaryKey()->getColumns());

        $relatedFks = $nestedSchemaTable->getForeignKeys();
        $this->assertEquals(1, count($relatedFks));
        $relatedFk = array_pop($relatedFks);
        $this->assertEquals("nested.schemarelated", $relatedFk->getForeignTableName());
    }

    /**
     * @group DBAL-91
     * @group DBAL-88
     */
    public function testReturnQuotedAssets()
    {
        $sql = 'create table dbal91_something ( id integer  CONSTRAINT id_something PRIMARY KEY NOT NULL  ,"table"   integer );';
        $this->_conn->exec($sql);

        $sql = 'ALTER TABLE dbal91_something ADD CONSTRAINT something_input FOREIGN KEY( "table" ) REFERENCES dbal91_something ON UPDATE CASCADE;';
        $this->_conn->exec($sql);

        $table = $this->_sm->listTableDetails('dbal91_something');

        $this->assertEquals(
            array(
                "CREATE TABLE dbal91_something (id INT NOT NULL, \"table\" INT DEFAULT NULL, PRIMARY KEY(id))",
                "CREATE INDEX IDX_A9401304ECA7352B ON dbal91_something (\"table\")",
            ),
            $this->_conn->getDatabasePlatform()->getCreateTableSQL($table)
        );
    }

    /**
     * @group DBAL-204
     */
    public function testFilterSchemaExpression()
    {
        $testTable = new \Doctrine\DBAL\Schema\Table('dbal204_test_prefix');
        $column = $testTable->addColumn('id', 'integer');
        $this->_sm->createTable($testTable);
        $testTable = new \Doctrine\DBAL\Schema\Table('dbal204_without_prefix');
        $column = $testTable->addColumn('id', 'integer');
        $this->_sm->createTable($testTable);

        $this->_conn->getConfiguration()->setFilterSchemaAssetsExpression('#^dbal204_#');
        $names = $this->_sm->listTableNames();
        $this->assertEquals(2, count($names));

        $this->_conn->getConfiguration()->setFilterSchemaAssetsExpression('#^dbal204_test#');
        $names = $this->_sm->listTableNames();
        $this->assertEquals(1, count($names));
    }

    public function testListForeignKeys()
    {
        if(!$this->_conn->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('Does not support foreign key constraints.');
        }

        $fkOptions = array('SET NULL', 'SET DEFAULT', 'NO ACTION','CASCADE', 'RESTRICT');
        $foreignKeys = array();
        $fkTable = $this->getTestTable('test_create_fk1');
        for($i = 0; $i < count($fkOptions); $i++) {
            $fkTable->addColumn("foreign_key_test$i", 'integer');
            $foreignKeys[] = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
                                 array("foreign_key_test$i"), 'test_create_fk2', array('id'), "foreign_key_test_$i"."_fk", array('onDelete' => $fkOptions[$i]));
        }
        $this->_sm->dropAndCreateTable($fkTable);
        $this->createTestTable('test_create_fk2');

        foreach($foreignKeys as $foreignKey) {
            $this->_sm->createForeignKey($foreignKey, 'test_create_fk1');
        }
        $fkeys = $this->_sm->listTableForeignKeys('test_create_fk1');
        $this->assertEquals(count($foreignKeys), count($fkeys), "Table 'test_create_fk1' has to have " . count($foreignKeys) . " foreign keys.");
        for ($i = 0; $i < count($fkeys); $i++) {
            $this->assertEquals(array("foreign_key_test$i"), array_map('strtolower', $fkeys[$i]->getLocalColumns()));
            $this->assertEquals(array('id'), array_map('strtolower', $fkeys[$i]->getForeignColumns()));
            $this->assertEquals('test_create_fk2', strtolower($fkeys[0]->getForeignTableName()));
            if ($foreignKeys[$i]->getOption('onDelete') == 'NO ACTION') {
                $this->assertFalse($fkeys[$i]->hasOption('onDelete'), 'Unexpected option: '. $fkeys[$i]->getOption('onDelete'));
            } else {
                $this->assertEquals($foreignKeys[$i]->getOption('onDelete'), $fkeys[$i]->getOption('onDelete'));
            }
        }
    }

    /**
     * @group DBAL-511
     */
    public function testDefaultValueCharacterVarying()
    {
        $testTable = new \Doctrine\DBAL\Schema\Table('dbal511_default');
        $testTable->addColumn('id', 'integer');
        $testTable->addColumn('def', 'string', array('default' => 'foo'));
        $testTable->setPrimaryKey(array('id'));

        $this->_sm->createTable($testTable);

        $databaseTable = $this->_sm->listTableDetails($testTable->getName());

        $this->assertEquals('foo', $databaseTable->getColumn('def')->getDefault());
    }

    /**
     * @group DDC-2843
     */
    public function testBooleanDefault()
    {
        $table = new \Doctrine\DBAL\Schema\Table('ddc2843_bools');
        $table->addColumn('id', 'integer');
        $table->addColumn('checked', 'boolean', array('default' => false));

        $this->_sm->createTable($table);

        $databaseTable = $this->_sm->listTableDetails($table->getName());

        $c = new \Doctrine\DBAL\Schema\Comparator();
        $diff = $c->diffTable($table, $databaseTable);

        $this->assertFalse($diff);
    }

    public function testListTableWithBinary()
    {
        $tableName = 'test_binary_table';

        $table = new \Doctrine\DBAL\Schema\Table($tableName);
        $table->addColumn('id', 'integer');
        $table->addColumn('column_varbinary', 'binary', array());
        $table->addColumn('column_binary', 'binary', array('fixed' => true));
        $table->setPrimaryKey(array('id'));

        $this->_sm->createTable($table);

        $table = $this->_sm->listTableDetails($tableName);

        $this->assertInstanceOf('Doctrine\DBAL\Types\BlobType', $table->getColumn('column_varbinary')->getType());
        $this->assertFalse($table->getColumn('column_varbinary')->getFixed());

        $this->assertInstanceOf('Doctrine\DBAL\Types\BlobType', $table->getColumn('column_binary')->getType());
        $this->assertFalse($table->getColumn('column_binary')->getFixed());
    }

    public function testListQuotedTable()
    {
        $offlineTable = new Schema\Table('user');
        $offlineTable->addColumn('id', 'integer');
        $offlineTable->addColumn('username', 'string', array('unique' => true));
        $offlineTable->addColumn('fk', 'integer');
        $offlineTable->setPrimaryKey(array('id'));
        $offlineTable->addForeignKeyConstraint($offlineTable, array('fk'), array('id'));

        $this->_sm->dropAndCreateTable($offlineTable);

        $onlineTable = $this->_sm->listTableDetails('"user"');

        $comparator = new Schema\Comparator();

        $this->assertFalse($comparator->diffTable($offlineTable, $onlineTable));
    }

    public function testListTablesExcludesViews()
    {
        $this->createTestTable('list_tables_excludes_views');

        $name = "list_tables_excludes_views_test_view";
        $sql = "SELECT * from list_tables_excludes_views";

        $view = new Schema\View($name, $sql);

        $this->_sm->dropAndCreateView($view);

        $tables = $this->_sm->listTables();

        $foundTable = false;
        foreach ($tables as $table) {
            $this->assertInstanceOf('Doctrine\DBAL\Schema\Table', $table, 'No Table instance was found in tables array.');
            if (strtolower($table->getName()) == 'list_tables_excludes_views_test_view') {
                $foundTable = true;
            }
        }

        $this->assertFalse($foundTable, 'View "list_tables_excludes_views_test_view" must not be found in table list');
    }

    /**
     * @group DBAL-1033
     */
    public function testPartialIndexes()
    {
        $offlineTable = new Schema\Table('person');
        $offlineTable->addColumn('id', 'integer');
        $offlineTable->addColumn('name', 'string');
        $offlineTable->addColumn('email', 'string');
        $offlineTable->addUniqueIndex(array('id', 'name'), 'simple_partial_index', array('where' => '(id IS NULL)'));
        $offlineTable->addIndex(array('id', 'name'), 'complex_partial_index', array(), array('where' => '(((id IS NOT NULL) AND (name IS NULL)) AND (email IS NULL))'));

        $this->_sm->dropAndCreateTable($offlineTable);

        $onlineTable = $this->_sm->listTableDetails('person');

        $comparator = new Schema\Comparator();

        $this->assertFalse($comparator->diffTable($offlineTable, $onlineTable));
        $this->assertTrue($onlineTable->hasIndex('simple_partial_index'));
        $this->assertTrue($onlineTable->hasIndex('complex_partial_index'));
        $this->assertTrue($onlineTable->getIndex('simple_partial_index')->hasOption('where'));
        $this->assertTrue($onlineTable->getIndex('complex_partial_index')->hasOption('where'));
        $this->assertSame('(id IS NULL)', $onlineTable->getIndex('simple_partial_index')->getOption('where'));
        $this->assertSame(
            '(((id IS NOT NULL) AND (name IS NULL)) AND (email IS NULL))',
            $onlineTable->getIndex('complex_partial_index')->getOption('where')
        );
    }

}

class MoneyType extends Type
{

    public function getName()
    {
        return "MyMoney";
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'MyMoney';
    }

}
