<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Types\Types;

class MariaDb1027PlatformTest extends AbstractMySQLPlatformTestCase
{
    /**
     * {@inheritdoc}
     */
    public function createPlatform() : AbstractPlatform
    {
        return new MariaDb1027Platform();
    }

    public function testHasNativeJsonType() : void
    {
        self::assertFalse($this->platform->hasNativeJsonType());
    }

    /**
     * From MariaDB 10.2.7, JSON type is an alias to LONGTEXT
     *
     * @link https://mariadb.com/kb/en/library/json-data-type/
     */
    public function testReturnsJsonTypeDeclarationSQL() : void
    {
        self::assertSame('LONGTEXT', $this->platform->getJsonTypeDeclarationSQL([]));
    }

    public function testInitializesJsonTypeMapping() : void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('json'));
        self::assertSame(Types::JSON, $this->platform->getDoctrineTypeMapping('json'));
    }

    /**
     * Overrides and skips AbstractMySQLPlatformTestCase test regarding propagation
     * of unsupported default values for Blob and Text columns.
     *
     * @see AbstractMySQLPlatformTestCase::testDoesNotPropagateDefaultValuesForUnsupportedColumnTypes()
     */
    public function testDoesNotPropagateDefaultValuesForUnsupportedColumnTypes() : void
    {
        $this->markTestSkipped('MariaDB102Platform support propagation of default values for BLOB and TEXT columns');
    }
}
