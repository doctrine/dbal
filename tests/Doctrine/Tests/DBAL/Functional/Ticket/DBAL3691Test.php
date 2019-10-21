<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Schema\MySqlSchemaManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @group DBAL-3691
 */
class DBAL3691Test extends TestCase
{
    public function testIssue()
    {
        $conn     = $this->createMock(Connection::class);
        $platform = new MariaDb1027Platform();

        $schemaManager = new MySqlSchemaManager($conn, $platform);

        $reflectionMethod = new ReflectionMethod($schemaManager, '_getPortableTableColumnDefinition');
        $reflectionMethod->setAccessible(true);

        $column = $reflectionMethod->invoke($schemaManager, [
            'field' => 'string_empty_by_default',
            'type' => 'varchar',
            'length' => 255,
            'default' => '',
            'notnull' => true,
            'extra' => false,
            'null' => false,
        ]);

        $this->assertEquals(null, $column->getDefault());
    }
}
