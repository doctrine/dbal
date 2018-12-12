<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\PostgreSQL92Platform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
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

    public function testCreateUnnamedPrimaryKey() : void
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('name', 'string', ['length' => 50]);
        $table->setPrimaryKey(['id']);

        self::assertEquals(
            ['CREATE TABLE test (id INT NOT NULL, name VARCHAR(50) NOT NULL, PRIMARY KEY(id))'],
            $this->platform->getCreateTableSQL($table)
        );
    }

    public function testCreateUnnamedNonPrimaryIndex() : void
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('name', 'string', ['length' => 50]);
        $table->addIndex(['name']);

        self::assertEquals(
            [
                'CREATE TABLE test (id INT NOT NULL, name VARCHAR(50) NOT NULL)',
                'CREATE INDEX IDX_D87F7E0C5E237E06 ON test (name)',
            ],
            $this->platform->getCreateTableSQL($table)
        );
    }

    public function testCreateUnnamedConstraintName() : void
    {
        self::assertEquals(
            'ALTER TABLE foo ADD PRIMARY KEY (a, b)',
            $this->platform->getCreatePrimaryKeySQL(
                new Index(null, ['a', 'b'], false, false),
                'foo'
            )
        );
    }

    public function testCreateNamedPrimaryKey() : void
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id'], 'primary_key_name');

        self::assertEquals(
            ['CREATE TABLE test (id INT NOT NULL, PRIMARY KEY(id))'],
            $this->platform->getCreateTableSQL($table)
        );
    }

    public function testCreateNamedNonPrimaryIndex() : void
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('name', 'string', ['length' => 50]);
        $table->addIndex(['name'], 'named_index');

        self::assertEquals(
            [
                'CREATE TABLE test (id INT NOT NULL, name VARCHAR(50) NOT NULL)',
                'CREATE INDEX named_index ON test (name)',
            ],
            $this->platform->getCreateTableSQL($table)
        );
    }

    public function testCreateNamedConstraintName() : void
    {
        self::assertEquals(
            'ALTER TABLE foo ADD CONSTRAINT constraint_name PRIMARY KEY (a, b)',
            $this->platform->getCreatePrimaryKeySQL(
                new Index('constraint_name', ['a', 'b']),
                'foo'
            )
        );
    }
}
