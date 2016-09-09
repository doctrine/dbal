<?php

namespace Doctrine\Tests\DBAL\Schema\Visitor;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Visitor\AddDefaultNamespace;

/**
 * @group DBAL-1168
 */
class AddDefaultNamespaceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider namespacedPlatforms
     */
    public function testNoNamespacesAndDefaultNamespaceAdded(AbstractPlatform $platform)
    {
        $config = new SchemaConfig();
        $config->setName('test');
        $schema = new Schema([], [], $config);
        $schema->createTable('foo');

        $this->assertEmpty($schema->getNamespaces());

        $schema->visit(new AddDefaultNamespace($platform));

        $this->assertNotEmpty($schema->getNamespaces());
        $this->assertCount(1, $schema->getNamespaces());
    }

    /**
     * @dataProvider namespacedPlatforms
     */
    public function testCustomNamespaceAndDefaultNamespaceAdded(AbstractPlatform $platform)
    {
        $config = new SchemaConfig();
        $config->setName('test');
        $schema = new Schema([], [], $config);
        $schema->createTable('foo');
        $schema->createTable('bar.baz');

        $this->assertNotEmpty($schema->getNamespaces());
        $this->assertCount(1, $schema->getNamespaces());

        $schema->visit(new AddDefaultNamespace($platform));

        $this->assertNotEmpty($schema->getNamespaces());
        $this->assertCount(2, $schema->getNamespaces());
    }

    /**
     * @dataProvider namespacedPlatforms
     */
    public function testExplicitDefaultNamespaceAndDefaultNamespaceNotAdded(AbstractPlatform $platform)
    {
        $config = new SchemaConfig();
        $config->setName('test');
        $schema = new Schema([], [], $config);
        $schema->createTable('foo');
        $schema->createTable($platform->getDefaultSchemaName().'.baz');

        $this->assertNotEmpty($schema->getNamespaces());
        $this->assertCount(1, $schema->getNamespaces());

        $schema->visit(new AddDefaultNamespace($platform));

        $this->assertNotEmpty($schema->getNamespaces());
        $this->assertCount(1, $schema->getNamespaces());
    }

    /**
     * @dataProvider notNamespacedPlatforms
     */
    public function testIncompatiblePlatformsUnaffected(AbstractPlatform $platform)
    {
        $config = new SchemaConfig();
        $config->setName('test');
        $schema = new Schema([], [], $config);
        $schema->createTable('foo');

        $this->assertEmpty($schema->getNamespaces());

        $schema->visit(new AddDefaultNamespace($platform));

        $this->assertEmpty($schema->getNamespaces());
    }

    public function namespacedPlatforms()
    {
        return [
            [new PostgreSqlPlatform()],
            [new SQLServerPlatform()],
        ];
    }

    public function notNamespacedPlatforms()
    {
        return [
            [new MySqlPlatform()],
            [new OraclePlatform()],
            [new SqlitePlatform()],
        ];
    }
}
