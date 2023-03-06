<?php

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDb1043Platform;
use Doctrine\DBAL\Types\Types;

class MariaDb1043PlatformTest extends AbstractMySQLPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new MariaDb1043Platform();
    }

    public function testHasNativeJsonType(): void
    {
        self::assertFalse($this->platform->hasNativeJsonType());
    }

    /**
     * From MariaDB 10.2.7, JSON type is an alias to LONGTEXT however from 10.4.3 setting a column
     * as JSON adds additional functionality so use JSON.
     *
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
        self::markTestSkipped('MariaDb1043Platform supports default values for BLOB and TEXT columns');
    }
}
