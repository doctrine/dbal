<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema;
use Doctrine\DBAL\Types\Type;
use function array_map;
use function dirname;
use function extension_loaded;
use function version_compare;

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
        self::assertFileExists($path);
        $this->_sm->dropDatabase($path);
        self::assertFileNotExists($path);
    }

    /**
     * @group DBAL-1220
     */
    public function testDropsDatabaseWithActiveConnections()
    {
        $this->_sm->dropAndCreateDatabase('test_drop_database');

        self::assertFileExists('test_drop_database');

        $params = $this->_conn->getParams();
        $params['dbname'] = 'test_drop_database';

        $user = $params['user'] ?? null;
        $password = $params['password'] ?? null;

        $connection = $this->_conn->getDriver()->connect($params, $user, $password);

        self::assertInstanceOf('Doctrine\DBAL\Driver\Connection', $connection);

        $this->_sm->dropDatabase('test_drop_database');

        self::assertFileNotExists('test_drop_database');

        unset($connection);
    }

    public function testRenameTable()
    {
        $this->createTestTable('oldname');
        $this->_sm->renameTable('oldname', 'newname');

        $tables = $this->_sm->listTableNames();
        self::assertContains('newname', $tables);
        self::assertNotContains('oldname', $tables);
    }

    public function createListTableColumns()
    {
        $table = parent::createListTableColumns();
        $table->getColumn('id')->setAutoincrement(true);

        return $table;
    }

    public function testListForeignKeysFromExistingDatabase()
    {
        $this->_conn->exec(<<<EOS
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
            new Schema\ForeignKeyConstraint(array('log'), 'log', array(null), 'FK_3',
                array('onUpdate' => 'SET NULL', 'onDelete' => 'NO ACTION', 'deferrable' => false, 'deferred' => false)),
            new Schema\ForeignKeyConstraint(array('parent'), 'user', array('id'), '1',
                array('onUpdate' => 'NO ACTION', 'onDelete' => 'CASCADE', 'deferrable' => false, 'deferred' => false)),
            new Schema\ForeignKeyConstraint(array('page'), 'page', array('key'), 'FK_1',
                array('onUpdate' => 'NO ACTION', 'onDelete' => 'NO ACTION', 'deferrable' => true, 'deferred' => true)),
        );

        self::assertEquals($expected, $this->_sm->listTableForeignKeys('user'));
    }

    public function testColumnCollation()
    {
        $table = new Schema\Table('test_collation');
        $table->addColumn('id', 'integer');
        $table->addColumn('text', 'text');
        $table->addColumn('foo', 'text')->setPlatformOption('collation', 'BINARY');
        $table->addColumn('bar', 'text')->setPlatformOption('collation', 'NOCASE');
        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns('test_collation');

        self::assertArrayNotHasKey('collation', $columns['id']->getPlatformOptions());
        self::assertEquals('BINARY', $columns['text']->getPlatformOption('collation'));
        self::assertEquals('BINARY', $columns['foo']->getPlatformOption('collation'));
        self::assertEquals('NOCASE', $columns['bar']->getPlatformOption('collation'));
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

        self::assertInstanceOf('Doctrine\DBAL\Types\BlobType', $table->getColumn('column_varbinary')->getType());
        self::assertFalse($table->getColumn('column_varbinary')->getFixed());

        self::assertInstanceOf('Doctrine\DBAL\Types\BlobType', $table->getColumn('column_binary')->getType());
        self::assertFalse($table->getColumn('column_binary')->getFixed());
    }

    public function testNonDefaultPKOrder()
    {
        if ( ! extension_loaded('sqlite3')) {
            $this->markTestSkipped('This test requires the SQLite3 extension.');
        }

        $version = \SQLite3::version();
        if(version_compare($version['versionString'], '3.7.16', '<')) {
            $this->markTestSkipped('This version of sqlite doesn\'t return the order of the Primary Key.');
        }
        $this->_conn->exec(<<<EOS
CREATE TABLE non_default_pk_order (
    id INTEGER,
    other_id INTEGER,
    PRIMARY KEY(other_id, id)
)
EOS
        );

        $tableIndexes = $this->_sm->listTableIndexes('non_default_pk_order');

         self::assertCount(1, $tableIndexes);

        self::assertArrayHasKey('primary', $tableIndexes, 'listTableIndexes() has to return a "primary" array key.');
        self::assertEquals(array('other_id', 'id'), array_map('strtolower', $tableIndexes['primary']->getColumns()));
    }

    /**
     * @group DBAL-1779
     */
    public function testListTableColumnsWithWhitespacesInTypeDeclarations()
    {
        $sql = <<<SQL
CREATE TABLE dbal_1779 (
    foo VARCHAR (64) ,
    bar TEXT (100)
)
SQL;

        $this->_conn->exec($sql);

        $columns = $this->_sm->listTableColumns('dbal_1779');

        self::assertCount(2, $columns);

        self::assertArrayHasKey('foo', $columns);
        self::assertArrayHasKey('bar', $columns);

        self::assertSame(Type::getType(Type::STRING), $columns['foo']->getType());
        self::assertSame(Type::getType(Type::TEXT), $columns['bar']->getType());

        self::assertSame(64, $columns['foo']->getLength());
        self::assertSame(100, $columns['bar']->getLength());
    }

    /**
     * @dataProvider getDiffListIntegerAutoincrementTableColumnsData
     * @group DBAL-924
     */
    public function testDiffListIntegerAutoincrementTableColumns($integerType, $unsigned, $expectedComparatorDiff)
    {
        $tableName = 'test_int_autoincrement_table';

        $offlineTable = new \Doctrine\DBAL\Schema\Table($tableName);
        $offlineTable->addColumn('id', $integerType, array('autoincrement' => true, 'unsigned' => $unsigned));
        $offlineTable->setPrimaryKey(array('id'));

        $this->_sm->dropAndCreateTable($offlineTable);

        $onlineTable = $this->_sm->listTableDetails($tableName);
        $comparator = new Schema\Comparator();
        $diff = $comparator->diffTable($offlineTable, $onlineTable);

        if ($expectedComparatorDiff) {
            self::assertEmpty($this->_sm->getDatabasePlatform()->getAlterTableSQL($diff));
        } else {
            self::assertFalse($diff);
        }
    }

    /**
     * @return array
     */
    public function getDiffListIntegerAutoincrementTableColumnsData()
    {
        return array(
            array('smallint', false, true),
            array('smallint', true, true),
            array('integer', false, false),
            array('integer', true, true),
            array('bigint', false, true),
            array('bigint', true, true),
        );
    }

    /**
     * @group DBAL-2921
     */
    public function testPrimaryKeyNoAutoIncrement()
    {
        $table = new Schema\Table('test_pk_auto_increment');
        $table->addColumn('id', 'integer');
        $table->addColumn('text', 'text');
        $table->setPrimaryKey(['id']);
        $this->_sm->dropAndCreateTable($table);

        $this->_conn->insert('test_pk_auto_increment', ['text' => '1']);

        $this->_conn->query('DELETE FROM test_pk_auto_increment');

        $this->_conn->insert('test_pk_auto_increment', ['text' => '2']);

        $query = $this->_conn->query('SELECT id FROM test_pk_auto_increment WHERE text = "2"');
        $query->execute();
        $lastUsedIdAfterDelete = (int) $query->fetchColumn();

        // with an empty table, non autoincrement rowid is always 1
        $this->assertEquals(1, $lastUsedIdAfterDelete);
    }
}
