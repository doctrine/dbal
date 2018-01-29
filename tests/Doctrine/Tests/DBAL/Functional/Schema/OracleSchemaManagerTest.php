<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\TestUtil;

class OracleSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function setUp()
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

        self::assertHasTable($tables, 'list_tables_test_new_name');
    }

    public function testListTableWithBinary()
    {
        $tableName = 'test_binary_table';

        $table = new \Doctrine\DBAL\Schema\Table($tableName);
        $table->addColumn('id', 'integer');
        $table->addColumn('column_varbinary', 'binary', []);
        $table->addColumn('column_binary', 'binary', ['fixed' => true]);
        $table->setPrimaryKey(['id']);

        $this->_sm->createTable($table);

        $table = $this->_sm->listTableDetails($tableName);

        self::assertInstanceOf('Doctrine\DBAL\Types\BinaryType', $table->getColumn('column_varbinary')->getType());
        self::assertFalse($table->getColumn('column_varbinary')->getFixed());

        self::assertInstanceOf('Doctrine\DBAL\Types\BinaryType', $table->getColumn('column_binary')->getType());
        self::assertFalse($table->getColumn('column_binary')->getFixed());
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
        $table->setPrimaryKey(['id']);

        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns($tableName);

        self::assertTrue($columns['id']->getNotnull());
        self::assertTrue($columns['foo']->getNotnull());
        self::assertTrue($columns['bar']->getNotnull());

        $diffTable = clone $table;
        $diffTable->changeColumn('foo', ['notnull' => false]);
        $diffTable->changeColumn('bar', ['length' => 1024]);

        $this->_sm->alterTable($comparator->diffTable($table, $diffTable));

        $columns = $this->_sm->listTableColumns($tableName);

        self::assertTrue($columns['id']->getNotnull());
        self::assertFalse($columns['foo']->getNotnull());
        self::assertTrue($columns['bar']->getNotnull());
    }

    public function testListDatabases()
    {
        // We need the temp connection that has privileges to create a database.
        $sm = TestUtil::getTempConnection()->getSchemaManager();

        $sm->dropAndCreateDatabase('c##test_create_database');

        $databases = $this->_sm->listDatabases();
        $databases = array_map('strtolower', $databases);

        self::assertContains('c##test_create_database', $databases);
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
            ['autoincrement' => true, 'comment' => 'Explicit casing.']
        );
        $offlinePrimaryTable->addColumn('select', 'integer', ['comment' => 'Reserved keyword.']);
        $offlinePrimaryTable->addColumn('foo', 'integer', ['comment' => 'Implicit uppercasing.']);
        $offlinePrimaryTable->addColumn('BAR', 'integer');
        $offlinePrimaryTable->addColumn('"BAZ"', 'integer');
        $offlinePrimaryTable->addIndex(['select'], 'from');
        $offlinePrimaryTable->addIndex(['foo'], 'foo_index');
        $offlinePrimaryTable->addIndex(['BAR'], 'BAR_INDEX');
        $offlinePrimaryTable->addIndex(['"BAZ"'], 'BAZ_INDEX');
        $offlinePrimaryTable->setPrimaryKey(['"Id"']);

        $foreignTableName = 'foreign';
        $offlineForeignTable = new Schema\Table($foreignTableName);
        $offlineForeignTable->addColumn('id', 'integer', ['autoincrement' => true]);
        $offlineForeignTable->addColumn('"Fk"', 'integer');
        $offlineForeignTable->addIndex(['"Fk"'], '"Fk_index"');
        $offlineForeignTable->addForeignKeyConstraint(
            $primaryTableName,
            ['"Fk"'],
            ['"Id"'],
            [],
            '"Primary_Table_Fk"'
        );
        $offlineForeignTable->setPrimaryKey(['id']);

        $this->_sm->tryMethod('dropTable', $foreignTableName);
        $this->_sm->tryMethod('dropTable', $primaryTableName);

        $this->_sm->createTable($offlinePrimaryTable);
        $this->_sm->createTable($offlineForeignTable);

        $onlinePrimaryTable = $this->_sm->listTableDetails($primaryTableName);
        $onlineForeignTable = $this->_sm->listTableDetails($foreignTableName);

        $platform = $this->_sm->getDatabasePlatform();

        // Primary table assertions
        self::assertSame($primaryTableName, $onlinePrimaryTable->getQuotedName($platform));

        self::assertTrue($onlinePrimaryTable->hasColumn('"Id"'));
        self::assertSame('"Id"', $onlinePrimaryTable->getColumn('"Id"')->getQuotedName($platform));
        self::assertTrue($onlinePrimaryTable->hasPrimaryKey());
        self::assertSame(['"Id"'], $onlinePrimaryTable->getPrimaryKey()->getQuotedColumns($platform));

        self::assertTrue($onlinePrimaryTable->hasColumn('select'));
        self::assertSame('"select"', $onlinePrimaryTable->getColumn('select')->getQuotedName($platform));

        self::assertTrue($onlinePrimaryTable->hasColumn('foo'));
        self::assertSame('FOO', $onlinePrimaryTable->getColumn('foo')->getQuotedName($platform));

        self::assertTrue($onlinePrimaryTable->hasColumn('BAR'));
        self::assertSame('BAR', $onlinePrimaryTable->getColumn('BAR')->getQuotedName($platform));

        self::assertTrue($onlinePrimaryTable->hasColumn('"BAZ"'));
        self::assertSame('BAZ', $onlinePrimaryTable->getColumn('"BAZ"')->getQuotedName($platform));

        self::assertTrue($onlinePrimaryTable->hasIndex('from'));
        self::assertTrue($onlinePrimaryTable->getIndex('from')->hasColumnAtPosition('"select"'));
        self::assertSame(['"select"'], $onlinePrimaryTable->getIndex('from')->getQuotedColumns($platform));

        self::assertTrue($onlinePrimaryTable->hasIndex('foo_index'));
        self::assertTrue($onlinePrimaryTable->getIndex('foo_index')->hasColumnAtPosition('foo'));
        self::assertSame(['FOO'], $onlinePrimaryTable->getIndex('foo_index')->getQuotedColumns($platform));

        self::assertTrue($onlinePrimaryTable->hasIndex('BAR_INDEX'));
        self::assertTrue($onlinePrimaryTable->getIndex('BAR_INDEX')->hasColumnAtPosition('BAR'));
        self::assertSame(['BAR'], $onlinePrimaryTable->getIndex('BAR_INDEX')->getQuotedColumns($platform));

        self::assertTrue($onlinePrimaryTable->hasIndex('BAZ_INDEX'));
        self::assertTrue($onlinePrimaryTable->getIndex('BAZ_INDEX')->hasColumnAtPosition('"BAZ"'));
        self::assertSame(['BAZ'], $onlinePrimaryTable->getIndex('BAZ_INDEX')->getQuotedColumns($platform));

        // Foreign table assertions
        self::assertTrue($onlineForeignTable->hasColumn('id'));
        self::assertSame('ID', $onlineForeignTable->getColumn('id')->getQuotedName($platform));
        self::assertTrue($onlineForeignTable->hasPrimaryKey());
        self::assertSame(['ID'], $onlineForeignTable->getPrimaryKey()->getQuotedColumns($platform));

        self::assertTrue($onlineForeignTable->hasColumn('"Fk"'));
        self::assertSame('"Fk"', $onlineForeignTable->getColumn('"Fk"')->getQuotedName($platform));

        self::assertTrue($onlineForeignTable->hasIndex('"Fk_index"'));
        self::assertTrue($onlineForeignTable->getIndex('"Fk_index"')->hasColumnAtPosition('"Fk"'));
        self::assertSame(['"Fk"'], $onlineForeignTable->getIndex('"Fk_index"')->getQuotedColumns($platform));

        self::assertTrue($onlineForeignTable->hasForeignKey('"Primary_Table_Fk"'));
        self::assertSame(
            $primaryTableName,
            $onlineForeignTable->getForeignKey('"Primary_Table_Fk"')->getQuotedForeignTableName($platform)
        );
        self::assertSame(
            ['"Fk"'],
            $onlineForeignTable->getForeignKey('"Primary_Table_Fk"')->getQuotedLocalColumns($platform)
        );
        self::assertSame(
            ['"Id"'],
            $onlineForeignTable->getForeignKey('"Primary_Table_Fk"')->getQuotedForeignColumns($platform)
        );
    }

    public function testListTableColumnsSameTableNamesInDifferentSchemas()
    {
        $table = $this->createListTableColumns();
        $this->_sm->dropAndCreateTable($table);

        $otherTable = new Table($table->getName());
        $otherTable->addColumn('id', Type::STRING);
        TestUtil::getTempConnection()->getSchemaManager()->dropAndCreateTable($otherTable);

        $columns = $this->_sm->listTableColumns($table->getName(), $this->_conn->getUsername());
        self::assertCount(7, $columns);
    }

    /**
     * @group DBAL-1234
     */
    public function testListTableIndexesPrimaryKeyConstraintNameDiffersFromIndexName()
    {
        $table = new Table('list_table_indexes_pk_id_test');
        $table->setSchemaConfig($this->_sm->createSchemaConfig());
        $table->addColumn('id', 'integer', ['notnull' => true]);
        $table->addUniqueIndex(['id'], 'id_unique_index');
        $this->_sm->dropAndCreateTable($table);

        // Adding a primary key on already indexed columns
        // Oracle will reuse the unique index, which cause a constraint name differing from the index name
        $this->_sm->createConstraint(new Schema\Index('id_pk_id_index', ['id'], true, true), 'list_table_indexes_pk_id_test');

        $tableIndexes = $this->_sm->listTableIndexes('list_table_indexes_pk_id_test');

        self::assertArrayHasKey('primary', $tableIndexes, 'listTableIndexes() has to return a "primary" array key.');
        self::assertEquals(['id'], array_map('strtolower', $tableIndexes['primary']->getColumns()));
        self::assertTrue($tableIndexes['primary']->isUnique());
        self::assertTrue($tableIndexes['primary']->isPrimary());
    }

    /**
     * @group DBAL-2555
     */
    public function testListTableDateTypeColumns()
    {
        $table = new Table('tbl_date');
        $table->addColumn('col_date', 'date');
        $table->addColumn('col_datetime', 'datetime');
        $table->addColumn('col_datetimetz', 'datetimetz');

        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns('tbl_date');

        self::assertSame('date', $columns['col_date']->getType()->getName());
        self::assertSame('datetime', $columns['col_datetime']->getType()->getName());
        self::assertSame('datetimetz', $columns['col_datetimetz']->getType()->getName());
    }

    public function testCreateAndListSequences() : void
    {
        self::markTestSkipped("Skipped for uppercase letters are contained in sequences' names. Fix the schema manager in 3.0.");
    }
}
