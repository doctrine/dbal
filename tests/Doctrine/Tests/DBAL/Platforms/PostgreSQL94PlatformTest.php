<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Types\Types;

class PostgreSQL94PlatformTest extends PostgreSQL92PlatformTest
{
    /**
     * {@inheritdoc}
     */
    public function createPlatform() : AbstractPlatform
    {
        return new PostgreSQL94Platform();
    }

    public function testReturnsJsonTypeDeclarationSQL() : void
    {
        parent::testReturnsJsonTypeDeclarationSQL();
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL(['jsonb' => false]));
        self::assertSame('JSONB', $this->platform->getJsonTypeDeclarationSQL(['jsonb' => true]));
    }

    public function testInitializesJsonTypeMapping() : void
    {
        parent::testInitializesJsonTypeMapping();
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('jsonb'));
        self::assertEquals(Types::JSON, $this->platform->getDoctrineTypeMapping('jsonb'));
    }
}
