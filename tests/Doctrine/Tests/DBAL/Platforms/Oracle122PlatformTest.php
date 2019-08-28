<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Oracle122Platform;
use function uniqid;

class Oracle122PlatformTest extends OraclePlatformTest
{

    public function createPlatform() : AbstractPlatform
    {
        return new Oracle122Platform();
    }
    
    public function testMaxIdentifierLength()
    {    
        self::assertSame(128, $this->platform->getMaxIdentifierLength());
    }
    
    public function testFixSchemaElementName()
    {
        $tableName = uniqid().uniqid().uniqid();
        self::assertSame($tableName, $this->platform->fixSchemaElementName($tableName));
    }
}
