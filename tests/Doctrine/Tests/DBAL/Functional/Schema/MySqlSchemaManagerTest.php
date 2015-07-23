<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

class MySqlSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (!Type::hasType('point')) {
            Type::addType('point', 'Doctrine\Tests\Types\MySqlPointType');
        }
    }

    public function testSwitchPrimaryKeyColumns()
    {
        $tableOld = new Table("switch_primary_key_columns");
        $tableOld->addColumn('foo_id', 'integer');
        $tableOld->addColumn('bar_id', 'integer');
        $tableNew = clone $tableOld;

        $this->_sm->createTable($tableOld);
        $tableFetched = $this->_sm->listTableDetails("switch_primary_key_columns");
        $tableNew = clone $tableFetched;
        $tableNew->setPrimaryKey(array('bar_id', 'foo_id'));

        $comparator = new Comparator;
        $this->_sm->alterTable($comparator->diffTable($tableFetched, $tableNew));
    }

    public function testDiffTableBug()
    {
        $schema = new Schema();
        $table = $schema->createTable('diffbug_routing_translations');
        $table->addColumn('id', 'integer');
        $table->addColumn('route', 'string');
        $table->addColumn('locale', 'string');
        $table->addColumn('attribute', 'string');
        $table->addColumn('localized_value', 'string');
        $table->addColumn('original_value', 'string');
        $table->setPrimaryKey(array('id'));
        $table->addUniqueIndex(array('route', 'locale', 'attribute'));
        $table->addIndex(array('localized_value')); // this is much more selective than the unique index

        $this->_sm->createTable($table);
        $tableFetched = $this->_sm->listTableDetails("diffbug_routing_translations");

        $comparator = new Comparator;
        $diff = $comparator->diffTable($tableFetched, $table);

        $this->assertFalse($diff, "no changes expected.");
    }

    public function testFulltextIndex()
    {
        $table = new Table('fulltext_index');
        $table->addColumn('text', 'text');
        $table->addIndex(array('text'), 'f_index');
        $table->addOption('engine', 'MyISAM');

        $index = $table->getIndex('f_index');
        $index->addFlag('fulltext');

        $this->_sm->dropAndCreateTable($table);

        $indexes = $this->_sm->listTableIndexes('fulltext_index');
        $this->assertArrayHasKey('f_index', $indexes);
        $this->assertTrue($indexes['f_index']->hasFlag('fulltext'));
    }

    public function testSpatialIndex()
    {
        $table = new Table('spatial_index');
        $table->addColumn('point', 'point');
        $table->addIndex(array('point'), 's_index');
        $table->addOption('engine', 'MyISAM');

        $index = $table->getIndex('s_index');
        $index->addFlag('spatial');

        $this->_sm->dropAndCreateTable($table);

        $indexes = $this->_sm->listTableIndexes('spatial_index');
        $this->assertArrayHasKey('s_index', $indexes);
        $this->assertTrue($indexes['s_index']->hasFlag('spatial'));
    }

    /**
     * @group DBAL-400
     */
    public function testAlterTableAddPrimaryKey()
    {
        $table = new Table('alter_table_add_pk');
        $table->addColumn('id', 'integer');
        $table->addColumn('foo', 'integer');
        $table->addIndex(array('id'), 'idx_id');

        $this->_sm->createTable($table);

        $comparator = new Comparator();
        $diffTable = clone $table;

        $diffTable->dropIndex('idx_id');
        $diffTable->setPrimaryKey(array('id'));

        $this->_sm->alterTable($comparator->diffTable($table, $diffTable));

        $table = $this->_sm->listTableDetails("alter_table_add_pk");

        $this->assertFalse($table->hasIndex('idx_id'));
        $this->assertTrue($table->hasPrimaryKey());
    }

    /**
     * @group DBAL-464
     */
    public function testDropPrimaryKeyWithAutoincrementColumn()
    {
        $table = new Table("drop_primary_key");
        $table->addColumn('id', 'integer', array('primary' => true, 'autoincrement' => true));
        $table->addColumn('foo', 'integer', array('primary' => true));
        $table->setPrimaryKey(array('id', 'foo'));

        $this->_sm->dropAndCreateTable($table);

        $diffTable = clone $table;

        $diffTable->dropPrimaryKey();

        $comparator = new Comparator();

        $this->_sm->alterTable($comparator->diffTable($table, $diffTable));

        $table = $this->_sm->listTableDetails("drop_primary_key");

        $this->assertFalse($table->hasPrimaryKey());
        $this->assertFalse($table->getColumn('id')->getAutoincrement());
    }

    /**
     * @group DBAL-789
     */
    public function testDoesNotPropagateDefaultValuesForUnsupportedColumnTypes()
    {
        $table = new Table("text_blob_default_value");
        $table->addColumn('def_text', 'text', array('default' => 'def'));
        $table->addColumn('def_text_null', 'text', array('notnull' => false, 'default' => 'def'));
        $table->addColumn('def_blob', 'blob', array('default' => 'def'));
        $table->addColumn('def_blob_null', 'blob', array('notnull' => false, 'default' => 'def'));

        $this->_sm->dropAndCreateTable($table);

        $onlineTable = $this->_sm->listTableDetails("text_blob_default_value");

        $this->assertNull($onlineTable->getColumn('def_text')->getDefault());
        $this->assertNull($onlineTable->getColumn('def_text_null')->getDefault());
        $this->assertFalse($onlineTable->getColumn('def_text_null')->getNotnull());
        $this->assertNull($onlineTable->getColumn('def_blob')->getDefault());
        $this->assertNull($onlineTable->getColumn('def_blob_null')->getDefault());
        $this->assertFalse($onlineTable->getColumn('def_blob_null')->getNotnull());

        $comparator = new Comparator();

        $this->_sm->alterTable($comparator->diffTable($table, $onlineTable));

        $onlineTable = $this->_sm->listTableDetails("text_blob_default_value");

        $this->assertNull($onlineTable->getColumn('def_text')->getDefault());
        $this->assertNull($onlineTable->getColumn('def_text_null')->getDefault());
        $this->assertFalse($onlineTable->getColumn('def_text_null')->getNotnull());
        $this->assertNull($onlineTable->getColumn('def_blob')->getDefault());
        $this->assertNull($onlineTable->getColumn('def_blob_null')->getDefault());
        $this->assertFalse($onlineTable->getColumn('def_blob_null')->getNotnull());
    }

    public function testColumnCollation()
    {
        $table = new Table('test_collation');
        $table->addOption('collate', $collation = 'latin1_swedish_ci');
        $table->addOption('charset', 'latin1');
        $table->addColumn('id', 'integer');
        $table->addColumn('text', 'text');
        $table->addColumn('foo', 'text')->setPlatformOption('collation', 'latin1_swedish_ci');
        $table->addColumn('bar', 'text')->setPlatformOption('collation', 'utf8_general_ci');
        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns('test_collation');

        $this->assertArrayNotHasKey('collation', $columns['id']->getPlatformOptions());
        $this->assertEquals('latin1_swedish_ci', $columns['text']->getPlatformOption('collation'));
        $this->assertEquals('latin1_swedish_ci', $columns['foo']->getPlatformOption('collation'));
        $this->assertEquals('utf8_general_ci', $columns['bar']->getPlatformOption('collation'));
    }

    /**
     * @group DBAL-843
     */
    public function testListLobTypeColumns()
    {
        $tableName = 'lob_type_columns';
        $table = new Table($tableName);

        $table->addColumn('col_tinytext', 'text', array('length' => MySqlPlatform::LENGTH_LIMIT_TINYTEXT));
        $table->addColumn('col_text', 'text', array('length' => MySqlPlatform::LENGTH_LIMIT_TEXT));
        $table->addColumn('col_mediumtext', 'text', array('length' => MySqlPlatform::LENGTH_LIMIT_MEDIUMTEXT));
        $table->addColumn('col_longtext', 'text');

        $table->addColumn('col_tinyblob', 'text', array('length' => MySqlPlatform::LENGTH_LIMIT_TINYBLOB));
        $table->addColumn('col_blob', 'blob', array('length' => MySqlPlatform::LENGTH_LIMIT_BLOB));
        $table->addColumn('col_mediumblob', 'blob', array('length' => MySqlPlatform::LENGTH_LIMIT_MEDIUMBLOB));
        $table->addColumn('col_longblob', 'blob');

        $this->_sm->dropAndCreateTable($table);

        $platform = $this->_sm->getDatabasePlatform();
        $offlineColumns = $table->getColumns();
        $onlineColumns = $this->_sm->listTableColumns($tableName);

        $this->assertSame(
            $platform->getClobTypeDeclarationSQL($offlineColumns['col_tinytext']->toArray()),
            $platform->getClobTypeDeclarationSQL($onlineColumns['col_tinytext']->toArray())
        );
        $this->assertSame(
            $platform->getClobTypeDeclarationSQL($offlineColumns['col_text']->toArray()),
            $platform->getClobTypeDeclarationSQL($onlineColumns['col_text']->toArray())
        );
        $this->assertSame(
            $platform->getClobTypeDeclarationSQL($offlineColumns['col_mediumtext']->toArray()),
            $platform->getClobTypeDeclarationSQL($onlineColumns['col_mediumtext']->toArray())
        );
        $this->assertSame(
            $platform->getClobTypeDeclarationSQL($offlineColumns['col_longtext']->toArray()),
            $platform->getClobTypeDeclarationSQL($onlineColumns['col_longtext']->toArray())
        );

        $this->assertSame(
            $platform->getBlobTypeDeclarationSQL($offlineColumns['col_tinyblob']->toArray()),
            $platform->getBlobTypeDeclarationSQL($onlineColumns['col_tinyblob']->toArray())
        );
        $this->assertSame(
            $platform->getBlobTypeDeclarationSQL($offlineColumns['col_blob']->toArray()),
            $platform->getBlobTypeDeclarationSQL($onlineColumns['col_blob']->toArray())
        );
        $this->assertSame(
            $platform->getBlobTypeDeclarationSQL($offlineColumns['col_mediumblob']->toArray()),
            $platform->getBlobTypeDeclarationSQL($onlineColumns['col_mediumblob']->toArray())
        );
        $this->assertSame(
            $platform->getBlobTypeDeclarationSQL($offlineColumns['col_longblob']->toArray()),
            $platform->getBlobTypeDeclarationSQL($onlineColumns['col_longblob']->toArray())
        );
    }

    /**
     * @group DBAL-423
     */
    public function testDiffListGuidTableColumn()
    {
        $offlineTable = new Table('list_guid_table_column');
        $offlineTable->addColumn('col_guid', 'guid');

        $this->_sm->dropAndCreateTable($offlineTable);

        $onlineTable = $this->_sm->listTableDetails('list_guid_table_column');

        $comparator = new Comparator();

        $this->assertFalse(
            $comparator->diffTable($offlineTable, $onlineTable),
            "No differences should be detected with the offline vs online schema."
        );
    }

    /**
     * @group DBAL-1082
     */
    public function testListDecimalTypeColumns()
    {
        $tableName = 'test_list_decimal_columns';
        $table = new Table($tableName);

        $table->addColumn('col', 'decimal');
        $table->addColumn('col_unsigned', 'decimal', array('unsigned' => true));

        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns($tableName);

        $this->assertArrayHasKey('col', $columns);
        $this->assertArrayHasKey('col_unsigned', $columns);
        $this->assertFalse($columns['col']->getUnsigned());
        $this->assertTrue($columns['col_unsigned']->getUnsigned());
    }

    /**
     * @group DBAL-1082
     */
    public function testListFloatTypeColumns()
    {
        $tableName = 'test_list_float_columns';
        $table = new Table($tableName);

        $table->addColumn('col', 'float');
        $table->addColumn('col_unsigned', 'float', array('unsigned' => true));

        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns($tableName);

        $this->assertArrayHasKey('col', $columns);
        $this->assertArrayHasKey('col_unsigned', $columns);
        $this->assertFalse($columns['col']->getUnsigned());
        $this->assertTrue($columns['col_unsigned']->getUnsigned());
    }

    public function testListTableIndexes2Db()
    {
        $conn1 = $this->_conn;
        $sm1 = $this->_sm;

        $conn2 = \Doctrine\Tests\TestUtil::getTempConnection();
        $sm2 = $conn2->getSchemaManager();

        $table1 = new Table('test_list_table_indexes_2db_1');
        $table1->addColumn('id', 'integer');
        $table1->addColumn('name', 'string');
        $table1->addColumn('param1', 'integer');
        $table1->addColumn('created', 'datetime');
        $table1->setPrimaryKey(array('id'));
        $table1->addUniqueIndex(array('name'));
        $table1->addIndex(array('param1'));
        $indexes1 = $table1->getIndexes();
        $sm1->dropAndCreateTable($table1);

        $table2 = new Table('test_list_table_indexes_2db_2');
        $table2->addColumn('id', 'integer');
        $table2->addColumn('name', 'string');
        $table2->addColumn('param2', 'integer');
        $table2->addColumn('created', 'datetime');
        $table2->setPrimaryKey(array('id'));
        $table2->addUniqueIndex(array('name'));
        $table2->addIndex(array('param2'));
        $indexes2 = $table2->getIndexes();
        $sm2->dropAndCreateTable($table2);

        // listTableIndexes 1
        $indexesFetched = $sm1->listTableIndexes($table1->getName());
        $diff = array_diff(array_keys($indexes1), array_keys($indexesFetched));
        $this->assertEmpty($diff, "indexes: no changes expected: 11");

        $indexesFetched = $sm1->listTableIndexes($table1->getName(), $conn1->getDatabase());
        $diff = array_diff(array_keys($indexes1), array_keys($indexesFetched));
        $this->assertEmpty($diff, "indexes: no changes expected: 12");

        $indexesFetched = $sm2->listTableIndexes($table1->getName(), $conn1->getDatabase());
        $diff = array_diff(array_keys($indexes1), array_keys($indexesFetched));
        $this->assertEmpty($diff, "indexes: no changes expected: 13");

        // listTableIndexes 2
        $indexesFetched = $sm2->listTableIndexes($table2->getName());
        $diff = array_diff(array_keys($indexes2), array_keys($indexesFetched));
        $this->assertEmpty($diff, "indexes: no changes expected: 21");

        $indexesFetched = $sm2->listTableIndexes($table2->getName(), $conn2->getDatabase());
        $diff = array_diff(array_keys($indexes2), array_keys($indexesFetched));
        $this->assertEmpty($diff, "indexes: no changes expected: 22");

        $indexesFetched = $sm1->listTableIndexes($table2->getName(), $conn2->getDatabase());
        $diff = array_diff(array_keys($indexes2), array_keys($indexesFetched));
        $this->assertEmpty($diff, "indexes: no changes expected: 23");
    }

    public function testListTableDetails2Db()
    {
        $conn1 = $this->_conn;
        $sm1 = $this->_sm;

        $conn2 = \Doctrine\Tests\TestUtil::getTempConnection();
        $sm2 = $conn2->getSchemaManager();

        $comparator = new \Doctrine\DBAL\Schema\Comparator();

        $table1 = new Table('test_list_table_details_2db_1');
        $table1->addColumn('id', 'integer');
        $table1->addColumn('name', 'string');
        $table1->addColumn('param1', 'integer');
        $table1->addColumn('created', 'datetime');
        $table1->setPrimaryKey(array('id'));
        $table1->addUniqueIndex(array('name'));
        $table1->addIndex(array('param1'));
        $sm1->dropAndCreateTable($table1);

        $table2 = new Table('test_list_table_details_2db_2');
        $table2->addColumn('id', 'integer');
        $table2->addColumn('name', 'string');
        $table2->addColumn('param2', 'integer');
        $table2->addColumn('created', 'datetime');
        $table2->setPrimaryKey(array('id'));
        $table2->addUniqueIndex(array('name'));
        $table2->addIndex(array('param2'));
        $sm2->dropAndCreateTable($table2);


        // listTableDetails 1
        $tableFetched = $sm1->listTableDetails($table1->getName());
        $diff = $comparator->diffTable($tableFetched, $table1);
        $this->assertFalse($diff, "tables: no changes expected: 11");

        $tableFetched = $sm1->listTableDetails($table1->getName(), $conn1->getDatabase());
        $diff = $comparator->diffTable($tableFetched, $table1);
        $this->assertFalse($diff, "tables: no changes expected: 12");

        $tableFetched = $sm2->listTableDetails($table1->getName(), $conn1->getDatabase());
        $diff = $comparator->diffTable($tableFetched, $table1);
        $this->assertFalse($diff, "tables: no changes expected: 13");

        // listTableDetails 2
        $tableFetched = $sm2->listTableDetails($table2->getName());
        $diff = $comparator->diffTable($tableFetched, $table2);
        $this->assertFalse($diff, "tables: no changes expected: 21");

        $tableFetched = $sm2->listTableDetails($table2->getName(), $conn2->getDatabase());
        $diff = $comparator->diffTable($tableFetched, $table2);
        $this->assertFalse($diff, "tables: no changes expected: 22");

        $tableFetched = $sm1->listTableDetails($table2->getName(), $conn2->getDatabase());
        $diff = $comparator->diffTable($tableFetched, $table2);
        $this->assertFalse($diff, "tables: no changes expected: 23");
    }
}