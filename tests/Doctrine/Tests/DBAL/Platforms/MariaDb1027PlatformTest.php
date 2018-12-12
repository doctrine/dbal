<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

class MariaDb1027PlatformTest extends AbstractMySQLPlatformTestCase
{
    /**
     * {@inheritdoc}
     */
    public function createPlatform() : MariaDb1027Platform
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
        self::assertSame(Type::JSON, $this->platform->getDoctrineTypeMapping('json'));
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

    public function testCreateUnnamedPrimaryKey() : void
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('name', 'string', ['length' => 50]);
        $table->setPrimaryKey(['id']);

        self::assertEquals(
            ['CREATE TABLE test (id INT NOT NULL, name VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'],
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
            ['CREATE TABLE test (id INT NOT NULL, name VARCHAR(50) NOT NULL, INDEX IDX_D87F7E0C5E237E06 (name)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'],
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
            ['CREATE TABLE test (id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'],
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
            ['CREATE TABLE test (id INT NOT NULL, name VARCHAR(50) NOT NULL, INDEX named_index (name)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'],
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
