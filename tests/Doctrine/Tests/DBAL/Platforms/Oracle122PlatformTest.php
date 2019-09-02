<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Oracle122Platform;

class Oracle122PlatformTest extends OraclePlatformTest
{
    public function createPlatform() : AbstractPlatform
    {
        return new Oracle122Platform();
    }

    public function testMaxIdentifierLength() : void
    {
        self::assertSame(128, $this->platform->getMaxIdentifierLength());
    }

    public function testFixSchemaElementName() : void
    {
        $tableName = 'ThisIsAStringTestLongerThan32Chars';
        self::assertSame($tableName, $this->platform->fixSchemaElementName($tableName));
    }
}
