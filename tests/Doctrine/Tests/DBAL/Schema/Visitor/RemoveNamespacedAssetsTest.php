<?php

namespace Doctrine\Tests\DBAL\Schema\Visitor;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Visitor\RemoveNamespacedAssets;

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
}
