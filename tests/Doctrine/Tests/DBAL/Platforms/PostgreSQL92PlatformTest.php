<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\PostgreSQL92Platform;
use Doctrine\DBAL\Types\Type;

class PostgreSQL92PlatformTest extends AbstractPostgreSqlPlatformTestCase
{
    /**
     * {@inheritdoc}
     */
    public function createPlatform()
    {
        return new PostgreSQL92Platform();
    }

    /**
     * @group DBAL-553
     */
    public function testHasNativeJsonType()
    {
        self::assertTrue($this->_platform->hasNativeJsonType());
    }

    /**
     * @group DBAL-553
     */
    public function testReturnsJsonTypeDeclarationSQL()
    {
        self::assertSame('JSON', $this->_platform->getJsonTypeDeclarationSQL(array()));
    }

    public function testReturnsSmallIntTypeDeclarationSQL()
    {
        self::assertSame(
            'SMALLSERIAL',
            $this->_platform->getSmallIntTypeDeclarationSQL(array('autoincrement' => true))
        );

        self::assertSame(
            'SMALLINT',
            $this->_platform->getSmallIntTypeDeclarationSQL(array('autoincrement' => false))
        );

        self::assertSame(
            'SMALLINT',
            $this->_platform->getSmallIntTypeDeclarationSQL(array())
        );
    }

    /**
     * @group DBAL-553
     */
    public function testInitializesJsonTypeMapping()
    {
        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('json'));
        self::assertEquals(Type::JSON, $this->_platform->getDoctrineTypeMapping('json'));
    }

    /**
     * @group DBAL-1220
     */
    public function testReturnsCloseActiveDatabaseConnectionsSQL()
    {
        self::assertSame(
            "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = 'foo'",
            $this->_platform->getCloseActiveDatabaseConnectionsSQL('foo')
        );
    }
}
