<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MariaDb1043Platform;

class MariaDb1043PlatformTest extends AbstractMySQLPlatformTestCase
{
    /**
     * {@inheritdoc}
     */
    public function createPlatform() : AbstractPlatform
    {
        return new MariaDb1043Platform();
    }

    public function testInheritsMariaDb1027Platform() : void
    {
        self::assertInstanceOf(MariaDb1027Platform::class, $this->platform);
    }

    public function testHasNativeJsonType() : void
    {
        self::assertTrue($this->platform->hasNativeJsonType());
    }

    /**
     * From MariaDB 10.4.3, the JSON_VALID function is automatically used as a CHECK constraint for the JSON data type
     * alias in order to ensure that a valid json document is inserted.
     *
     * @link https://mariadb.com/kb/en/library/json-data-type/
     */
    public function testReturnsJsonTypeDeclarationSQL() : void
    {
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL([]));
    }

    /**
     * Overrides and skips AbstractMySQLPlatformTestCase test regarding propagation
     * of unsupported default values for Blob and Text columns.
     *
     * @see AbstractMySQLPlatformTestCase::testDoesNotPropagateDefaultValuesForUnsupportedColumnTypes()
     */
    public function testDoesNotPropagateDefaultValuesForUnsupportedColumnTypes() : void
    {
        $this->markTestSkipped('MariaDB104Platform support propagation of default values for BLOB and TEXT columns');
    }
}
