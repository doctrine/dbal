<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Types\TypeProvider;
use PHPUnit\Framework\TestCase;

class SchemaConfigTest extends TestCase
{
    public function testSetTypeRegistryReplacesRegistry(): void
    {
        $config   = new SchemaConfig();
        $registry = self::createStub(TypeProvider::class);

        self::assertNull($config->getTypeRegistry());

        $config->setTypeRegistry($registry);

        self::assertSame($registry, $config->getTypeRegistry());
    }
}
