<?php

namespace Doctrine\Tests\DBAL\Sharding\SQLAzure;

use Doctrine\DBAL\Platforms\SQLAzurePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Sharding\SQLAzure\Schema\MultiTenantVisitor;
use PHPUnit\Framework\TestCase;

class MultiTenantVisitorTest extends TestCase
{
    public function testMultiTenantPrimaryKey() : void
    {
        $platform = new SQLAzurePlatform();
        $visitor  = new MultiTenantVisitor();

        $schema = new Schema();
        $foo    = $schema->createTable('foo');
        $foo->addColumn('id', 'string');
        $foo->setPrimaryKey(['id']);
        $schema->visit($visitor);

        self::assertEquals(['id', 'tenant_id'], $foo->getPrimaryKey()->getColumns());
        self::assertTrue($foo->hasColumn('tenant_id'));
    }

    public function testMultiTenantNonPrimaryKey() : void
    {
        $platform = new SQLAzurePlatform();
        $visitor  = new MultiTenantVisitor();

        $schema = new Schema();
        $foo    = $schema->createTable('foo');
        $foo->addColumn('id', 'string');
        $foo->addColumn('created', 'datetime');
        $foo->setPrimaryKey(['id']);
        $foo->addIndex(['created'], 'idx');

        $foo->getPrimaryKey()->addFlag('nonclustered');
        $foo->getIndex('idx')->addFlag('clustered');

        $schema->visit($visitor);

        self::assertEquals(['id'], $foo->getPrimaryKey()->getColumns());
        self::assertTrue($foo->hasColumn('tenant_id'));
        self::assertEquals(['created', 'tenant_id'], $foo->getIndex('idx')->getColumns());
    }
}
