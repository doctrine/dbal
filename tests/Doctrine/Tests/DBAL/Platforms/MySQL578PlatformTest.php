<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\MySQL578Platform;

class MySQL578PlatformTest extends MySQL57PlatformTest
{
    /**
     * {@inheritdoc}
     */
    public function createPlatform()
    {
        return new MySQL578Platform();
    }

    /**
     * @group DBAL-553
     */
    public function testHasNativeJsonType()
    {
        $this->assertTrue($this->_platform->hasNativeJsonType());
    }

    /**
     * @group DBAL-553
     */
    public function testReturnsJsonTypeDeclarationSQL()
    {
        $this->assertSame('JSON', $this->_platform->getJsonTypeDeclarationSQL(array()));
    }

    /**
     * @group DBAL-553
     */
    public function testInitializesJsonTypeMapping()
    {
        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('json'));
        $this->assertEquals('json_array', $this->_platform->getDoctrineTypeMapping('json'));
    }
}
