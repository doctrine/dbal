<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Types\Type;

class PostgreSqlPlatformTest extends AbstractPostgreSqlPlatformTestCase
{
    public function createPlatform() : AbstractPlatform
    {
        return new PostgreSqlPlatform();
    }

    public function testSupportsPartialIndexes() : void
    {
        self::assertTrue($this->platform->supportsPartialIndexes());
    }

    public function testColumnCollationDeclarationSQL() : void
    {
        self::assertEquals(
            'COLLATE "en_US.UTF-8"',
            $this->platform->getColumnCollationDeclarationSQL('en_US.UTF-8')
        );
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
        self::assertEquals(Type::JSON, $this->platform->getDoctrineTypeMapping('json'));
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
