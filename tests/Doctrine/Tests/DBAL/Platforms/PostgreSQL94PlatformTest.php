<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\PostgreSQL94Platform;

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
        $this->assertSame('JSON', $this->_platform->getJsonTypeDeclarationSQL(array('jsonb' => false)));
        $this->assertSame('JSONB', $this->_platform->getJsonTypeDeclarationSQL(array('jsonb' => true)));
    }

    public function testInitializesJsonTypeMapping()
    {
        parent::testInitializesJsonTypeMapping();
        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('jsonb'));
        $this->assertEquals('json_array', $this->_platform->getDoctrineTypeMapping('jsonb'));
    }
}
