<?php

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

class PostgreSQL94PlatformTest extends AbstractPostgreSQLPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new PostgreSQL94Platform();
    }

    public function testSupportsPartialIndexes(): void
    {
        self::assertTrue($this->platform->supportsPartialIndexes());
    }

    public function testGetCreateTableSQLWithUniqueConstraints(): void
    {
        $table = new Table('foo');
        $table->addColumn('id', 'string');
        $table->addUniqueConstraint(['id'], 'test_unique_constraint');
        self::assertSame(
            [
                'CREATE TABLE foo (id VARCHAR(255) NOT NULL)',
                'ALTER TABLE foo ADD CONSTRAINT test_unique_constraint UNIQUE (id)',
            ],
            $this->platform->getCreateTableSQL($table),
            'Unique constraints are added to table.'
        );
    }

    public function testGetCreateTableSQLWithColumnCollation(): void
    {
        $table = new Table('foo');
        $table->addColumn('id', 'string');
        $table->addOption('comment', 'foo');
        self::assertSame(
            [
                'CREATE TABLE foo (id VARCHAR(255) NOT NULL)',
                "COMMENT ON TABLE foo IS 'foo'",
            ],
            $this->platform->getCreateTableSQL($table),
            'Comments are added to table.'
        );
    }

    public function testColumnCollationDeclarationSQL(): void
    {
        self::assertEquals(
            'COLLATE "en_US.UTF-8"',
            $this->platform->getColumnCollationDeclarationSQL('en_US.UTF-8')
        );
    }

    public function testGetDropIndexSQLWithRegularIndex(): void
    {
        $index = new Index('index_name', ['foo']);
        $this->assertEquals(
            'DROP INDEX index_name',
            $this->platform->getDropIndexSQL($index)
        );
    }

    public function testGetDropIndexSQLWithPrimaryKeyTableAsString(): void
    {
        $index = new Index('index_name', ['foo'], false, true);
        $this->assertEquals(
            'ALTER TABLE table_name DROP CONSTRAINT index_name',
            $this->platform->getDropIndexSQL($index, 'table_name')
        );
    }

    public function testGetDropIndexSQLWithPrimaryKeyTableAsObject(): void
    {
        $index = new Index('index_name', ['foo'], false, true);
        $table = new Table('table_name');
        $this->assertEquals(
            'ALTER TABLE table_name DROP CONSTRAINT index_name',
            $this->platform->getDropIndexSQL($index, $table)
        );
    }

    public function testGetDropIndexSQLWithPrimaryKeyNoTable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $index = new Index('index_name', ['foo'], false, true);
        $this->platform->getDropIndexSQL($index);
    }

    public function testGetDropIndexSQLWithPrimaryKeyEmptyStringAsTable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $index = new Index('index_name', ['foo'], false, true);
        $this->platform->getDropIndexSQL($index, '');
    }

    public function testHasNativeJsonType(): void
    {
        self::assertTrue($this->platform->hasNativeJsonType());
    }

    public function testReturnsJsonTypeDeclarationSQL(): void
    {
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL([]));
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL(['jsonb' => false]));
        self::assertSame('JSONB', $this->platform->getJsonTypeDeclarationSQL(['jsonb' => true]));
    }

    public function testReturnsSmallIntTypeDeclarationSQL(): void
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

    public function testInitializesJsonTypeMapping(): void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('json'));
        self::assertEquals(Types::JSON, $this->platform->getDoctrineTypeMapping('json'));
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('jsonb'));
        self::assertEquals(Types::JSON, $this->platform->getDoctrineTypeMapping('jsonb'));
    }
}
