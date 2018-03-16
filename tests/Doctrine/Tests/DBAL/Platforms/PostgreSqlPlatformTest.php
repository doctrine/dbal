<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Types\Type;

class PostgreSqlPlatformTest extends AbstractPostgreSqlPlatformTestCase
{
    public function createPlatform()
    {
        return new PostgreSqlPlatform();
    }

    public function testSupportsPartialIndexes()
    {
        self::assertTrue($this->platform->supportsPartialIndexes());
    }

    public function testColumnCollationDeclarationSQL()
    {
        self::assertEquals(
            'COLLATE "en_US.UTF-8"',
            $this->platform->getColumnCollationDeclarationSQL('en_US.UTF-8')
        );
    }

    /**
     * @group DBAL-553
     */
    public function testHasNativeJsonType()
    {
        self::assertTrue($this->platform->hasNativeJsonType());
    }

    /**
     * @group DBAL-553
     */
    public function testReturnsJsonTypeDeclarationSQL()
    {
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL([]));
    }

    public function testReturnsSmallIntTypeDeclarationSQL()
    {
        self::assertSame(
            'SMALLSERIAL',
            $this->platform->getSmallIntTypeDeclarationSQL(['autoincrement' => true])
        );

        self::assertSame(
            'SMALLINT',
            $this->platform->getSmallIntTypeDeclarationSQL(['autoincrement' => false])
        );

        self::assertSame(
            'SMALLINT',
            $this->platform->getSmallIntTypeDeclarationSQL([])
        );
    }

    /**
     * @group DBAL-553
     */
    public function testInitializesJsonTypeMapping()
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('json'));
        self::assertEquals(Type::JSON, $this->platform->getDoctrineTypeMapping('json'));
    }

    /**
     * @group DBAL-1220
     */
    public function testReturnsCloseActiveDatabaseConnectionsSQL()
    {
        self::assertSame(
            "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = 'foo'",
            $this->platform->getCloseActiveDatabaseConnectionsSQL('foo')
        );
    }
}
