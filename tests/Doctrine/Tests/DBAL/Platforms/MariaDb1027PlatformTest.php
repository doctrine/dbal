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
    public function createPlatform()
    {
        return new MariaDb1027Platform();
    }

    public function testHasNativeJsonType()
    {
        self::assertTrue($this->_platform->hasNativeJsonType());
    }

    public function testReturnsJsonTypeDeclarationSQL()
    {
        self::assertSame('JSON', $this->_platform->getJsonTypeDeclarationSQL([]));
    }

    public function testInitializesJsonTypeMapping()
    {
        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('json'));
        self::assertSame(Type::JSON, $this->_platform->getDoctrineTypeMapping('json'));
    }

    /**
     * Overrides AbstractMySQLPlatformTestCase::testDoesNotPropagateDefaultValuesForUnsupportedColumnTypes()
     */
    public function testDoesNotPropagateDefaultValuesForUnsupportedColumnTypes()
    {
        $table = new Table("text_blob_default_value");

        $table->addColumn('def_text', 'text', array('default' => 'def'));
        $table->addColumn('def_blob', 'blob', array('default' => 'def'));

        self::assertSame(
            array('CREATE TABLE text_blob_default_value (def_text LONGTEXT DEFAULT \'def\' NOT NULL, def_blob LONGBLOB DEFAULT \'def\' NOT NULL) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'),
            $this->_platform->getCreateTableSQL($table)
        );

        $diffTable = clone $table;

        $diffTable->changeColumn('def_text', array('default' => 'def'));
        $diffTable->changeColumn('def_blob', array('default' => 'def'));

        $comparator = new Comparator();

        self::assertFalse($comparator->diffTable($table, $diffTable));
    }


}
