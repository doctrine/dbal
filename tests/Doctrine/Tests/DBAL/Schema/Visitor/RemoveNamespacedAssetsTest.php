<?php

namespace Doctrine\Tests\DBAL\Schema\Visitor;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Visitor\RemoveNamespacedAssets;
use Doctrine\DBAL\Platforms\MySqlPlatform;

class RemoveNamespacedAssetsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group DBAL-204
     */
    public function testRemoveNamespacedAssets()
    {
        $config = new SchemaConfig;
        $config->setName("test");
        $schema = new Schema(array(), array(), $config);

        $schema->createTable("test.test");
        $schema->createTable("foo.bar");
        $schema->createTable("baz");

        $schema->visit(new RemoveNamespacedAssets());

        $tables = $schema->getTables();
        $this->assertEquals(array("test.test", "test.baz"), array_keys($tables), "Only 2 tables should be present, both in 'test' namespace.");
    }

    /**
     * @group DBAL-204
     */
    public function testCleanupForeignKeys()
    {
        $config = new SchemaConfig;
        $config->setName("test");
        $schema = new Schema(array(), array(), $config);

        $fooTable = $schema->createTable("foo.bar");
        $fooTable->addColumn('id', 'integer');

        $testTable = $schema->createTable("test.test");
        $testTable->addColumn('id', 'integer');

        $testTable->addForeignKeyConstraint("foo.bar", array("id"), array("id"));

        $schema->visit(new RemoveNamespacedAssets());

        $sql = $schema->toSql(new MySqlPlatform());
        $this->assertEquals(1, count($sql), "Just one CREATE TABLE statement, no foreign key and table to foo.bar");
    }

    /**
     * @group DBAL-204
     */
    public function testCleanupForeignKeysDifferentOrder()
    {
        $config = new SchemaConfig;
        $config->setName("test");
        $schema = new Schema(array(), array(), $config);

        $testTable = $schema->createTable("test.test");
        $testTable->addColumn('id', 'integer');

        $fooTable = $schema->createTable("foo.bar");
        $fooTable->addColumn('id', 'integer');

        $testTable->addForeignKeyConstraint("foo.bar", array("id"), array("id"));

        $schema->visit(new RemoveNamespacedAssets());

        $sql = $schema->toSql(new MySqlPlatform());
        $this->assertEquals(1, count($sql), "Just one CREATE TABLE statement, no foreign key and table to foo.bar");
    }
}

