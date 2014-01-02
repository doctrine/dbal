<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Schema;

require_once __DIR__ . '/../../../TestInit.php';

class MySqlSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
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
        $diffTable  = clone $table;

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
}
