<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DBAL461Test extends TestCase
{
    public function testIssue(): void
    {
        $conn     = $this->createMock(Connection::class);
        $platform = new SQLServerPlatform();
        $platform->registerDoctrineTypeMapping('numeric', Types::DECIMAL);

        $schemaManager = new SQLServerSchemaManager($conn, $platform);

        $reflectionMethod = new ReflectionMethod($schemaManager, '_getPortableTableColumnDefinition');
        $column           = $reflectionMethod->invoke($schemaManager, [
            'name' => 'test',
            'type' => 'numeric',
            'length' => null,
            'default' => null,
            'is_nullable' => true,
            'scale' => 18,
            'precision' => 0,
            'is_identity' => false,
            'collation_name' => 'foo',
            'comment' => null,
        ]);

        self::assertInstanceOf(DecimalType::class, $column->getType());
    }
}
