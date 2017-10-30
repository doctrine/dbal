<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Schema\Comparator;
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
        self::assertTrue($this->_platform->hasNativeJsonType());
    }

    /**
     * From MariaDB 10.2.7, JSON type is an alias to LONGTEXT
     * @link https://mariadb.com/kb/en/library/json-data-type/
     */
    public function testReturnsJsonTypeDeclarationSQL() : void
    {
        self::assertSame('LONGTEXT', $this->_platform->getJsonTypeDeclarationSQL([]));
    }

    public function testInitializesJsonTypeMapping() : void
    {
        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('json'));
        self::assertSame(Type::JSON, $this->_platform->getDoctrineTypeMapping('json'));
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

    /**
     * Since MariaDB 10.2, Text and Blob can have a default value.
     */
    public function testPropagateDefaultValuesForTextAndBlobColumnTypes() : void
    {
        $table = new Table("text_blob_default_value");

        $table->addColumn('def_text', Type::TEXT, ['default' => "hello"]);
        $table->addColumn('def_blob', Type::BLOB, ['default' => 'world']);

        self::assertSame(
            ["CREATE TABLE text_blob_default_value (def_text LONGTEXT DEFAULT 'hello' NOT NULL, def_blob LONGBLOB DEFAULT 'world' NOT NULL) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB"],
            $this->_platform->getCreateTableSQL($table)
        );

        $diffTable = clone $table;

        $diffTable->changeColumn('def_text', ['default' => "hello"]);
        $diffTable->changeColumn('def_blob', ['default' => 'world']);

        $comparator = new Comparator();

        self::assertFalse($comparator->diffTable($table, $diffTable));
    }

    public function testPropagateDefaultValuesForJsonColumnType() : void
    {
        $table = new Table("text_json_default_value");

        $json = json_encode(['prop1' => "Hello", 'prop2' => 10]);

        $table->addColumn('def_json', Type::TEXT, ['default' => $json]);

        $jsonType = $this->createPlatform()->getJsonTypeDeclarationSQL($table->getColumn('def_json')->toArray());

        self::assertSame(
            ["CREATE TABLE text_json_default_value (def_json $jsonType DEFAULT '{\"prop1\":\"Hello\",\"prop2\":10}' NOT NULL) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB"],
            $this->_platform->getCreateTableSQL($table)
        );

        $diffTable = clone $table;

        $diffTable->changeColumn('def_json', ['default' => $json]);

        $comparator = new Comparator();

        self::assertFalse($comparator->diffTable($table, $diffTable));
    }
}
