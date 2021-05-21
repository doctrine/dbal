<?php

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;
use Doctrine\DBAL\Types\DecimalType;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DBAL461Test extends TestCase
{
    public function testIssue(): void
    {
        $conn     = $this->createMock(Connection::class);
        $platform = $this->getMockForAbstractClass(SQLServer2012Platform::class);
        $platform->registerDoctrineTypeMapping('numeric', 'decimal');

        $schemaManager = new SQLServerSchemaManager($conn, $platform);

        $reflectionMethod = new ReflectionMethod($schemaManager, '_getPortableTableColumnDefinition');
        $reflectionMethod->setAccessible(true);
        $column = $reflectionMethod->invoke($schemaManager, [
            'type' => 'numeric(18,0)',
            'length' => null,
            'default' => null,
            'notnull' => false,
            'scale' => 18,
            'precision' => 0,
            'autoincrement' => false,
            'collation' => 'foo',
            'comment' => null,
        ]);

        self::assertInstanceOf(DecimalType::class, $column->getType());
    }
}
