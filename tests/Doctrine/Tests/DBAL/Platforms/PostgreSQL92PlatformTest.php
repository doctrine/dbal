<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL92Platform;
use Doctrine\DBAL\Types\Types;

class PostgreSQL92PlatformTest extends AbstractPostgreSqlPlatformTestCase
{
    /**
     * {@inheritdoc}
     */
    public function createPlatform() : AbstractPlatform
    {
        return new PostgreSQL92Platform();
    }

    /**
     * @group DBAL-553
     */
    public function testHasNativeJsonType() : void
    {
        self::assertTrue($this->platform->hasNativeJsonType());
    }

    /**
     * @group DBAL-553
     */
    public function testReturnsJsonTypeDeclarationSQL() : void
    {
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL([]));
    }

    public function testReturnsSmallIntTypeDeclarationSQL() : void
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
    public function testInitializesJsonTypeMapping() : void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('json'));
        self::assertEquals(Types::JSON, $this->platform->getDoctrineTypeMapping('json'));
    }

    /**
     * @group DBAL-1220
     */
    public function testReturnsCloseActiveDatabaseConnectionsSQL() : void
    {
        self::assertSame(
            "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = 'foo'",
            $this->platform->getCloseActiveDatabaseConnectionsSQL('foo')
        );
    }
}
