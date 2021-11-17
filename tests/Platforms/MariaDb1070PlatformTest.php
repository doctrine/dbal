<?php

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Types\Types;

class MariaDb1070PlatformTest extends AbstractMySQLPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new MariaDb1027Platform();
    }

    public function testHasNativeJsonType(): void
    {
        self::assertTrue($this->platform->hasNativeGuidType());
    }

    public function testReturnsGuidTypeDeclarationSQL(): void
    {
        self::assertSame('UUID', $this->platform->getGuidTypeDeclarationSQL([]));
    }

    public function testInitializesUuidTypeMapping(): void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('uuid'));
        self::assertSame(Types::GUID, $this->platform->getDoctrineTypeMapping('uuid'));
    }
}
