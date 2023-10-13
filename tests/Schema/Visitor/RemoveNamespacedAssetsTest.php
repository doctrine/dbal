<?php

namespace Doctrine\DBAL\Tests\Schema\Visitor;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Visitor\RemoveNamespacedAssets;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

use function array_keys;

class RemoveNamespacedAssetsTest extends TestCase
{
    public function testRemoveNamespacedAssets(): void
    {
        $config = new SchemaConfig();
        $config->setName('test');
        $schema = new Schema([], [], $config);

        $schema->createTable('test.test');
        $schema->createTable('foo.bar');
        $schema->createTable('baz');

        $schema->visit(new RemoveNamespacedAssets());

        $tables = $schema->getTables();
        self::assertEquals(['test.test', 'test.baz'], array_keys($tables));
    }

    public function testCleanupForeignKeys(): void
    {
        $config = new SchemaConfig();
        $config->setName('test');
        $schema = new Schema([], [], $config);

        $fooTable = $schema->createTable('foo.bar');
        $fooTable->addColumn('id', Types::INTEGER);

        $testTable = $schema->createTable('test.test');
        $testTable->addColumn('id', Types::INTEGER);

        $testTable->addForeignKeyConstraint('foo.bar', ['id'], ['id']);

        $schema->visit(new RemoveNamespacedAssets());

        $sql = $schema->toSql(new MySQLPlatform());
        self::assertCount(1, $sql, 'Just one CREATE TABLE statement, no foreign key and table to foo.bar');
    }

    public function testCleanupForeignKeysDifferentOrder(): void
    {
        $config = new SchemaConfig();
        $config->setName('test');
        $schema = new Schema([], [], $config);

        $testTable = $schema->createTable('test.test');
        $testTable->addColumn('id', Types::INTEGER);

        $fooTable = $schema->createTable('foo.bar');
        $fooTable->addColumn('id', Types::INTEGER);

        $testTable->addForeignKeyConstraint('foo.bar', ['id'], ['id']);

        $schema->visit(new RemoveNamespacedAssets());

        $sql = $schema->toSql(new MySQLPlatform());
        self::assertCount(1, $sql, 'Just one CREATE TABLE statement, no foreign key and table to foo.bar');
    }
}
