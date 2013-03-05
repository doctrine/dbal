<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;

use Doctrine\DBAL\Schema;

require_once __DIR__ . '/../../../TestInit.php';

class SqliteSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    /**
     * SQLITE does not support databases.
     *
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testListDatabases()
    {
        $this->_sm->listDatabases();
    }

    public function testCreateAndDropDatabase()
    {
        $path = dirname(__FILE__).'/test_create_and_drop_sqlite_database.sqlite';

        $this->_sm->createDatabase($path);
        $this->assertEquals(true, file_exists($path));
        $this->_sm->dropDatabase($path);
        $this->assertEquals(false, file_exists($path));
    }

    public function testRenameTable()
    {
        $this->createTestTable('oldname');
        $this->_sm->renameTable('oldname', 'newname');

        $tables = $this->_sm->listTableNames();
        $this->assertContains('newname', $tables);
        $this->assertNotContains('oldname', $tables);
    }

    public function createListTableColumns()
    {
        $table = parent::createListTableColumns();
        $table->getColumn('id')->setAutoincrement(true);

        return $table;
    }

    public function testListForeignKeysFromExistingDatabase()
    {
        $this->_conn->executeQuery(<<<EOS
CREATE TABLE user (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page INTEGER CONSTRAINT FK_1 REFERENCES page (key) DEFERRABLE INITIALLY DEFERRED,
    parent INTEGER REFERENCES user(id) ON DELETE CASCADE,
    log INTEGER,
    CONSTRAINT FK_3 FOREIGN KEY (log) REFERENCES log ON UPDATE SET NULL NOT DEFERRABLE
)
EOS
        );

        $expected = array(
            new ForeignKeyConstraint(array('log'), 'log', array(null), 'FK_3',
                array('onUpdate' => 'SET NULL', 'onDelete' => 'NO ACTION', 'deferrable' => false, 'deferred' => false)),
            new ForeignKeyConstraint(array('parent'), 'user', array('id'), '1',
                array('onUpdate' => 'NO ACTION', 'onDelete' => 'CASCADE', 'deferrable' => false, 'deferred' => false)),
            new ForeignKeyConstraint(array('page'), 'page', array('key'), 'FK_1',
                array('onUpdate' => 'NO ACTION', 'onDelete' => 'NO ACTION', 'deferrable' => true, 'deferred' => true)),
        );

        $this->assertEquals($expected, $this->_sm->listTableForeignKeys('user'));
    }

    public function testCollationCharset()
    {
        $table = new \Doctrine\DBAL\Schema\Table($tableName = 'test_collation_charset');
        $column = $table->addColumn($columnName = 'test', 'string');

        $this->_sm->dropAndCreateTable($table);
        $columns = $this->_sm->listTableColumns($tableName);

        $this->assertFalse($columns[$columnName]->hasPlatformOption('collate')); // SQLite should not report any collation on the column

        $column->setPlatformOption('collate', $collation = 'NOCASE');

        $this->_sm->dropAndCreateTable($table);
        $columns = $this->_sm->listTableColumns($tableName);

        $this->assertEquals($collation, $columns[$columnName]->getPlatformOption('collate'));
    }
}
