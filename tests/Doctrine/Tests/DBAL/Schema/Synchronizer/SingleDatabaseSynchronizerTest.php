<?php

namespace Doctrine\Tests\DBAL\Schema\Synchronizer;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Synchronizer\SingleDatabaseSynchronizer;

class SingleDatabaseSynchronizerTest extends \PHPUnit_Framework_TestCase
{
    private $conn;
    private $synchronizer;

    protected function setUp()
    {
        $this->conn = DriverManager::getConnection(array(
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ));
        $this->synchronizer = new SingleDatabaseSynchronizer($this->conn);
    }

    public function testGetCreateSchema()
    {
        $schema = new Schema();
        $table = $schema->createTable('test');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));

        $sql = $this->synchronizer->getCreateSchema($schema);
        $this->assertEquals(array('CREATE TABLE test (id INTEGER NOT NULL, PRIMARY KEY(id))'), $sql);
    }

    public function testGetUpdateSchema()
    {
        $schema = new Schema();
        $table = $schema->createTable('test');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));

        $sql = $this->synchronizer->getUpdateSchema($schema);
        $this->assertEquals(array('CREATE TABLE test (id INTEGER NOT NULL, PRIMARY KEY(id))'), $sql);
    }

    public function testGetDropSchema()
    {
        $schema = new Schema();
        $table = $schema->createTable('test');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));

        $this->synchronizer->createSchema($schema);

        $sql = $this->synchronizer->getDropSchema($schema);
        $this->assertEquals(array('DROP TABLE test'), $sql);
    }

    public function testGetDropAllSchema()
    {
        $schema = new Schema();
        $table = $schema->createTable('test');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));

        $this->synchronizer->createSchema($schema);

        $sql = $this->synchronizer->getDropAllSchema();
        $this->assertEquals(array('DROP TABLE test'), $sql);
    }
}

