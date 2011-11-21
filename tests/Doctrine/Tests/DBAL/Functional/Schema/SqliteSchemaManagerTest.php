<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema;

require_once __DIR__ . '/../../../TestInit.php';

class SqliteSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    /**
     * SQLITE does not support databases.
     *
     * @expectedException Doctrine\DBAL\DBALException
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

    /**
     * @expectedException Doctrine\DBAL\DBALException
     */
    public function testRenameTable()
    {
        $this->_sm->renameTable('oldname', 'newname');
    }
}