<?php

namespace Doctrine\Tests\DBAL\Schema\Visitor;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Visitor\RemoveNamespacedAssets;
use PHPUnit\Framework\TestCase;
use function array_keys;

class RemoveNamespacedAssetsTest extends TestCase
{
    /**
     * @group DBAL-204
     */
    public function testRemoveNamespacedAssets() : void
    {
        $config = new SchemaConfig();
        $config->setName('test');
        $schema = new Schema([], [], $config);

        $schema->createTable('test.test');
        $schema->createTable('foo.bar');
        $schema->createTable('baz');

        $schema->visit(new RemoveNamespacedAssets());

        $tables = $schema->getTables();
        self::assertEquals(['test.test', 'test.baz'], array_keys($tables), "Only 2 tables should be present, both in 'test' namespace.");
    }

    /**
     * @group DBAL-204
     */
    public function testCleanupForeignKeys() : void
    {
        $config = new SchemaConfig();
        $config->setName('test');
        $schema = new Schema([], [], $config);

        $fooTable = $schema->createTable('foo.bar');
        $fooTable->addColumn('id', 'integer');

        $testTable = $schema->createTable('test.test');
        $testTable->addColumn('id', 'integer');

        $testTable->addForeignKeyConstraint('foo.bar', ['id'], ['id']);

        $schema->visit(new RemoveNamespacedAssets());

        $sql = $schema->toSql(new MySqlPlatform());
        self::assertCount(1, $sql, 'Just one CREATE TABLE statement, no foreign key and table to foo.bar');
    }

    /**
     * @group DBAL-204
     */
    public function testCleanupForeignKeysDifferentOrder() : void
    {
        $config = new SchemaConfig();
        $config->setName('test');
        $schema = new Schema([], [], $config);

        $testTable = $schema->createTable('test.test');
        $testTable->addColumn('id', 'integer');

        $fooTable = $schema->createTable('foo.bar');
        $fooTable->addColumn('id', 'integer');

        $testTable->addForeignKeyConstraint('foo.bar', ['id'], ['id']);

        $schema->visit(new RemoveNamespacedAssets());

        $sql = $schema->toSql(new MySqlPlatform());
        self::assertCount(1, $sql, 'Just one CREATE TABLE statement, no foreign key and table to foo.bar');
    }
}
