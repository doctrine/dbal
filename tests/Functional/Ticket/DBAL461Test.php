<?php

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DBAL461Test extends TestCase
{
    public function testIssue(): void
    {
        $configuration = $this->createStub(Configuration::class);
        $configuration->method('getDisableTypeComments')->willReturn(false);

        $conn = $this->createMock(Connection::class);
        $conn->method('getConfiguration')->willReturn($configuration);

        $platform = new SQLServer2012Platform();
        $platform->registerDoctrineTypeMapping('numeric', Types::DECIMAL);

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
