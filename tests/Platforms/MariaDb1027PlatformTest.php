<?php

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Types\Types;

class MariaDb1027PlatformTest extends AbstractMySQLPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new MariaDb1027Platform();
    }

    public function testHasNativeJsonType(): void
    {
        self::assertTrue($this->platform->hasNativeJsonType());
    }

    /**
     * @link https://mariadb.com/kb/en/library/json-data-type/
     */
    public function testReturnsJsonTypeDeclarationSQL(): void
    {
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL([]));
    }

    public function testInitializesJsonTypeMapping(): void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('json'));
        self::assertSame(Types::JSON, $this->platform->getDoctrineTypeMapping('json'));
    }

    public function testIgnoresDifferenceInDefaultValuesForUnsupportedColumnTypes(): void
    {
        self::markTestSkipped('MariaDb1027Platform supports default values for BLOB and TEXT columns');
    }
}
