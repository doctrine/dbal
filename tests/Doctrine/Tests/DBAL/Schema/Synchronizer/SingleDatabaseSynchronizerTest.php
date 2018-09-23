<?php

namespace Doctrine\Tests\DBAL\Schema\Synchronizer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Synchronizer\SingleDatabaseSynchronizer;
use PHPUnit\Framework\TestCase;

/**
 * @requires extension pdo_sqlite
 */
class SingleDatabaseSynchronizerTest extends TestCase
{
    /** @var Connection */
    private $conn;

    /** @var SingleDatabaseSynchronizer */
    private $synchronizer;

    protected function setUp()
    {
        $this->conn         = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $this->synchronizer = new SingleDatabaseSynchronizer($this->conn);
    }

    public function testGetCreateSchema()
    {
        $schema = new Schema();
        $table  = $schema->createTable('test');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);

        $sql = $this->synchronizer->getCreateSchema($schema);
        self::assertEquals(['CREATE TABLE test (id INTEGER NOT NULL, PRIMARY KEY(id))'], $sql);
    }

    public function testGetUpdateSchema()
    {
        $schema = new Schema();
        $table  = $schema->createTable('test');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);

        $sql = $this->synchronizer->getUpdateSchema($schema);
        self::assertEquals(['CREATE TABLE test (id INTEGER NOT NULL, PRIMARY KEY(id))'], $sql);
    }

    public function testGetDropSchema()
    {
        $schema = new Schema();
        $table  = $schema->createTable('test');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);

        $this->synchronizer->createSchema($schema);

        $sql = $this->synchronizer->getDropSchema($schema);
        self::assertEquals(['DROP TABLE test'], $sql);
    }

    public function testGetDropAllSchema()
    {
        $schema = new Schema();
        $table  = $schema->createTable('test');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);

        $this->synchronizer->createSchema($schema);

        $sql = $this->synchronizer->getDropAllSchema();
        self::assertEquals(['DROP TABLE test'], $sql);
    }
}
