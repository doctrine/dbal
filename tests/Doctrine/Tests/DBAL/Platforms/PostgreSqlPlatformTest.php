<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\PostgreSqlPlatform;

class PostgreSqlPlatformTest extends AbstractPostgreSqlPlatformTestCase
{
    public function createPlatform()
    {
        return new PostgreSqlPlatform;
    }

    public function testSupportsPartialIndexes()
    {
        $this->assertTrue($this->_platform->supportsPartialIndexes());
    }

    public function testInitializesTsvectorTypeMapping()
    {
        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('tsvector'));
        $this->assertEquals('text', $this->_platform->getDoctrineTypeMapping('tsvector'));
    }

}
