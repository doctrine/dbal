<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Types\Type;

class PostgreSQL94PlatformTest extends PostgreSQL92PlatformTest
{
    /**
     * {@inheritdoc}
     */
    public function createPlatform()
    {
        return new PostgreSQL94Platform();
    }

    public function testReturnsJsonTypeDeclarationSQL()
    {
        parent::testReturnsJsonTypeDeclarationSQL();
        self::assertSame('JSON', $this->_platform->getJsonTypeDeclarationSQL(array('jsonb' => false)));
        self::assertSame('JSONB', $this->_platform->getJsonTypeDeclarationSQL(array('jsonb' => true)));
    }

    public function testInitializesJsonTypeMapping()
    {
        parent::testInitializesJsonTypeMapping();
        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('jsonb'));
        self::assertEquals(Type::JSON, $this->_platform->getDoctrineTypeMapping('jsonb'));
    }
}
