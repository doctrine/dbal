<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema;
use Doctrine\Tests\TestUtil;

require_once __DIR__ . '/../../../TestInit.php';

class OracleSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    public function setUp()
    {
        parent::setUp();

        if(!isset($GLOBALS['db_username'])) {
            $this->markTestSkipped('Foo');
        }

        $username = $GLOBALS['db_username'];

        $query = "GRANT ALL PRIVILEGES TO ".$username;

        $conn = \Doctrine\Tests\TestUtil::getTempConnection();
        $conn->executeUpdate($query);
    }

    public function testRenameTable()
    {
        $this->_sm->tryMethod('DropTable', 'list_tables_test');
        $this->_sm->tryMethod('DropTable', 'list_tables_test_new_name');

        $this->createTestTable('list_tables_test');
        $this->_sm->renameTable('list_tables_test', 'list_tables_test_new_name');

        $tables = $this->_sm->listTables();

        $this->assertHasTable($tables, 'list_tables_test_new_name');
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

        $this->assertInstanceOf('Doctrine\DBAL\Types\BinaryType', $table->getColumn('column_varbinary')->getType());
        $this->assertFalse($table->getColumn('column_varbinary')->getFixed());

        $this->assertInstanceOf('Doctrine\DBAL\Types\BinaryType', $table->getColumn('column_binary')->getType());
        $this->assertFalse($table->getColumn('column_binary')->getFixed());
    }

    /**
     * @group DBAL-472
     * @group DBAL-1001
     */
    public function testAlterTableColumnNotNull()
    {
        $comparator = new Schema\Comparator();
        $tableName  = 'list_table_column_notnull';
        $table      = new Schema\Table($tableName);

        $table->addColumn('id', 'integer');
        $table->addColumn('foo', 'integer');
        $table->addColumn('bar', 'string');
        $table->setPrimaryKey(array('id'));

        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns($tableName);

        $this->assertTrue($columns['id']->getNotnull());
        $this->assertTrue($columns['foo']->getNotnull());
        $this->assertTrue($columns['bar']->getNotnull());

        $diffTable = clone $table;
        $diffTable->changeColumn('foo', array('notnull' => false));
        $diffTable->changeColumn('bar', array('length' => 1024));

        $this->_sm->alterTable($comparator->diffTable($table, $diffTable));

        $columns = $this->_sm->listTableColumns($tableName);

        $this->assertTrue($columns['id']->getNotnull());
        $this->assertFalse($columns['foo']->getNotnull());
        $this->assertTrue($columns['bar']->getNotnull());
    }

    public function testListDatabases()
    {
        // We need the temp connection that has privileges to create a database.
        $sm = TestUtil::getTempConnection()->getSchemaManager();

        $sm->dropAndCreateDatabase('c##test_create_database');

        $databases = $this->_sm->listDatabases();
        $databases = array_map('strtolower', $databases);

        $this->assertContains('c##test_create_database', $databases);
    }

    /**
     * @group DBAL-831
     */
    public function testListTableDetailsWithDifferentIdentifierQuotingRequirements()
    {
        $primaryTableName = '"Primary_Table"';
        $offlinePrimaryTable = new Schema\Table($primaryTableName);
        $offlinePrimaryTable->addColumn(
            '"Id"',
            'integer',
            array('autoincrement' => true, 'comment' => 'Explicit casing.')
        );
        $offlinePrimaryTable->addColumn('select', 'integer', array('comment' => 'Reserved keyword.'));
        $offlinePrimaryTable->addColumn('foo', 'integer', array('comment' => 'Implicit uppercasing.'));
        $offlinePrimaryTable->addColumn('BAR', 'integer');
        $offlinePrimaryTable->addColumn('"BAZ"', 'integer');
        $offlinePrimaryTable->addIndex(array('select'), 'from');
        $offlinePrimaryTable->addIndex(array('foo'), 'foo_index');
        $offlinePrimaryTable->addIndex(array('BAR'), 'BAR_INDEX');
        $offlinePrimaryTable->addIndex(array('"BAZ"'), 'BAZ_INDEX');
        $offlinePrimaryTable->setPrimaryKey(array('"Id"'));

        $foreignTableName = 'foreign';
        $offlineForeignTable = new Schema\Table($foreignTableName);
        $offlineForeignTable->addColumn('id', 'integer', array('autoincrement' => true));
        $offlineForeignTable->addColumn('"Fk"', 'integer');
        $offlineForeignTable->addIndex(array('"Fk"'), '"Fk_index"');
        $offlineForeignTable->addForeignKeyConstraint(
            $primaryTableName,
            array('"Fk"'),
            array('"Id"'),
            array(),
            '"Primary_Table_Fk"'
        );
        $offlineForeignTable->setPrimaryKey(array('id'));

        $this->_sm->tryMethod('dropTable', $foreignTableName);
        $this->_sm->tryMethod('dropTable', $primaryTableName);

        $this->_sm->createTable($offlinePrimaryTable);
        $this->_sm->createTable($offlineForeignTable);

        $onlinePrimaryTable = $this->_sm->listTableDetails($primaryTableName);
        $onlineForeignTable = $this->_sm->listTableDetails($foreignTableName);

        $platform = $this->_sm->getDatabasePlatform();

        // Primary table assertions
        $this->assertSame($primaryTableName, $onlinePrimaryTable->getQuotedName($platform));

        $this->assertTrue($onlinePrimaryTable->hasColumn('"Id"'));
        $this->assertSame('"Id"', $onlinePrimaryTable->getColumn('"Id"')->getQuotedName($platform));
        $this->assertTrue($onlinePrimaryTable->hasPrimaryKey());
        $this->assertSame(array('"Id"'), $onlinePrimaryTable->getPrimaryKey()->getQuotedColumns($platform));

        $this->assertTrue($onlinePrimaryTable->hasColumn('select'));
        $this->assertSame('"select"', $onlinePrimaryTable->getColumn('select')->getQuotedName($platform));

        $this->assertTrue($onlinePrimaryTable->hasColumn('foo'));
        $this->assertSame('FOO', $onlinePrimaryTable->getColumn('foo')->getQuotedName($platform));

        $this->assertTrue($onlinePrimaryTable->hasColumn('BAR'));
        $this->assertSame('BAR', $onlinePrimaryTable->getColumn('BAR')->getQuotedName($platform));

        $this->assertTrue($onlinePrimaryTable->hasColumn('"BAZ"'));
        $this->assertSame('BAZ', $onlinePrimaryTable->getColumn('"BAZ"')->getQuotedName($platform));

        $this->assertTrue($onlinePrimaryTable->hasIndex('from'));
        $this->assertTrue($onlinePrimaryTable->getIndex('from')->hasColumnAtPosition('"select"'));
        $this->assertSame(array('"select"'), $onlinePrimaryTable->getIndex('from')->getQuotedColumns($platform));

        $this->assertTrue($onlinePrimaryTable->hasIndex('foo_index'));
        $this->assertTrue($onlinePrimaryTable->getIndex('foo_index')->hasColumnAtPosition('foo'));
        $this->assertSame(array('FOO'), $onlinePrimaryTable->getIndex('foo_index')->getQuotedColumns($platform));

        $this->assertTrue($onlinePrimaryTable->hasIndex('BAR_INDEX'));
        $this->assertTrue($onlinePrimaryTable->getIndex('BAR_INDEX')->hasColumnAtPosition('BAR'));
        $this->assertSame(array('BAR'), $onlinePrimaryTable->getIndex('BAR_INDEX')->getQuotedColumns($platform));

        $this->assertTrue($onlinePrimaryTable->hasIndex('BAZ_INDEX'));
        $this->assertTrue($onlinePrimaryTable->getIndex('BAZ_INDEX')->hasColumnAtPosition('"BAZ"'));
        $this->assertSame(array('BAZ'), $onlinePrimaryTable->getIndex('BAZ_INDEX')->getQuotedColumns($platform));

        // Foreign table assertions
        $this->assertTrue($onlineForeignTable->hasColumn('id'));
        $this->assertSame('ID', $onlineForeignTable->getColumn('id')->getQuotedName($platform));
        $this->assertTrue($onlineForeignTable->hasPrimaryKey());
        $this->assertSame(array('ID'), $onlineForeignTable->getPrimaryKey()->getQuotedColumns($platform));

        $this->assertTrue($onlineForeignTable->hasColumn('"Fk"'));
        $this->assertSame('"Fk"', $onlineForeignTable->getColumn('"Fk"')->getQuotedName($platform));

        $this->assertTrue($onlineForeignTable->hasIndex('"Fk_index"'));
        $this->assertTrue($onlineForeignTable->getIndex('"Fk_index"')->hasColumnAtPosition('"Fk"'));
        $this->assertSame(array('"Fk"'), $onlineForeignTable->getIndex('"Fk_index"')->getQuotedColumns($platform));

        $this->assertTrue($onlineForeignTable->hasForeignKey('"Primary_Table_Fk"'));
        $this->assertSame(
            $primaryTableName,
            $onlineForeignTable->getForeignKey('"Primary_Table_Fk"')->getQuotedForeignTableName($platform)
        );
        $this->assertSame(
            array('"Fk"'),
            $onlineForeignTable->getForeignKey('"Primary_Table_Fk"')->getQuotedLocalColumns($platform)
        );
        $this->assertSame(
            array('"Id"'),
            $onlineForeignTable->getForeignKey('"Primary_Table_Fk"')->getQuotedForeignColumns($platform)
        );
    }
}
