<?php

namespace Doctrine\Tests\DBAL\Sharding\SQLAzure;

use Doctrine\DBAL\Platforms\SQLAzurePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Sharding\SQLAzure\Schema\MultiTenantVisitor;

class MultiTenantVisitorTest extends \PHPUnit_Framework_TestCase
{
    public function testMultiTenantPrimaryKey()
    {
        $platform = new SQLAzurePlatform();
        $visitor = new MultiTenantVisitor();

        $schema = new Schema();
        $foo = $schema->createTable('foo');
        $foo->addColumn('id', 'string');
        $foo->setPrimaryKey(array('id'));
        $schema->visit($visitor);

        $this->assertEquals(array('id', 'tenant_id'), $foo->getPrimaryKey()->getColumns());
        $this->assertTrue($foo->hasColumn('tenant_id'));
    }

    public function testMultiTenantNonPrimaryKey()
    {
        $platform = new SQLAzurePlatform();
        $visitor = new MultiTenantVisitor();

        $schema = new Schema();
        $foo = $schema->createTable('foo');
        $foo->addColumn('id', 'string');
        $foo->addColumn('created', 'datetime');
        $foo->setPrimaryKey(array('id'));
        $foo->addIndex(array('created'), 'idx');

        $foo->getPrimaryKey()->addFlag('nonclustered');
        $foo->getIndex('idx')->addFlag('clustered');

        $schema->visit($visitor);

        $this->assertEquals(array('id'), $foo->getPrimaryKey()->getColumns());
        $this->assertTrue($foo->hasColumn('tenant_id'));
        $this->assertEquals(array('created', 'tenant_id'), $foo->getIndex('idx')->getColumns());
    }
}

