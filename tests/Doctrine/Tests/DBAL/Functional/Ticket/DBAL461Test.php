<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Schema\SQLServerSchemaManager;

/**
 * @group DBAL-461
 */
class DBAL461Test extends \PHPUnit_Framework_TestCase
{
    public function testIssue()
    {
        $conn = $this->getMock('Doctrine\DBAL\Connection', array(), array(), '', false);
        $platform = $this->getMockForAbstractClass('Doctrine\DBAL\Platforms\AbstractPlatform');
        $platform->registerDoctrineTypeMapping('numeric', 'decimal');

        $schemaManager = new SQLServerSchemaManager($conn, $platform);

        $reflectionMethod = new \ReflectionMethod($schemaManager, '_getPortableTableColumnDefinition');
        $reflectionMethod->setAccessible(true);
        $column = $reflectionMethod->invoke($schemaManager, array(
            'type' => 'numeric(18,0)',
            'length' => null,
            'default' => null,
            'notnull' => false,
            'scale' => 18,
            'precision' => 0,
            'autoincrement' => false,
            'collation' => 'foo',
            'comment' => null,
        ));

        $this->assertEquals('Decimal', (string)$column->getType());
    }
}
