<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

class ColumnTest extends \PHPUnit\Framework\TestCase
{
    public function testGet()
    {
        $column = $this->createColumn();

        self::assertEquals("foo", $column->getName());
        self::assertSame(Type::getType('string'), $column->getType());

        self::assertEquals(200, $column->getLength());
        self::assertEquals(5, $column->getPrecision());
        self::assertEquals(2, $column->getScale());
        self::assertTrue($column->getUnsigned());
        self::assertFalse($column->getNotNull());
        self::assertTrue($column->getFixed());
        self::assertEquals("baz", $column->getDefault());

        self::assertEquals(array('foo' => 'bar'), $column->getPlatformOptions());
        self::assertTrue($column->hasPlatformOption('foo'));
        self::assertEquals('bar', $column->getPlatformOption('foo'));
        self::assertFalse($column->hasPlatformOption('bar'));

        self::assertEquals(array('bar' => 'baz'), $column->getCustomSchemaOptions());
        self::assertTrue($column->hasCustomSchemaOption('bar'));
        self::assertEquals('baz', $column->getCustomSchemaOption('bar'));
        self::assertFalse($column->hasCustomSchemaOption('foo'));
    }

    public function testToArray()
    {
        $expected = array(
            'name' => 'foo',
            'type' => Type::getType('string'),
            'default' => 'baz',
            'notnull' => false,
            'length' => 200,
            'precision' => 5,
            'scale' => 2,
            'fixed' => true,
            'unsigned' => true,
            'autoincrement' => false,
            'columnDefinition' => null,
            'comment' => null,
            'foo' => 'bar',
            'bar' => 'baz'
        );

        self::assertEquals($expected, $this->createColumn()->toArray());
    }

    /**
     * @group legacy
     * @expectedDeprecation The "unknown_option" column option is not supported, setting it is deprecated and will cause an error in Doctrine 3.0
     */
    public function testSettingUnknownOptionIsStillSupported() : void
    {
        new Column('foo', $this->createMock(Type::class), ['unknown_option' => 'bar']);
    }

    /**
     * @return Column
     */
    public function createColumn()
    {
        $options = array(
            'length' => 200,
            'precision' => 5,
            'scale' => 2,
            'unsigned' => true,
            'notnull' => false,
            'fixed' => true,
            'default' => 'baz',
            'platformOptions' => array('foo' => 'bar'),
            'customSchemaOptions' => array('bar' => 'baz'),
        );

        $string = Type::getType('string');
        return new Column("foo", $string, $options);
    }

    /**
     * @group DBAL-64
     * @group DBAL-830
     */
    public function testQuotedColumnName()
    {
        $string = Type::getType('string');
        $column = new Column("`bar`", $string, array());

        $mysqlPlatform = new \Doctrine\DBAL\Platforms\MySqlPlatform();
        $sqlitePlatform = new \Doctrine\DBAL\Platforms\SqlitePlatform();

        self::assertEquals('bar', $column->getName());
        self::assertEquals('`bar`', $column->getQuotedName($mysqlPlatform));
        self::assertEquals('"bar"', $column->getQuotedName($sqlitePlatform));

        $column = new Column("[bar]", $string);

        $sqlServerPlatform = new \Doctrine\DBAL\Platforms\SQLServerPlatform();

        self::assertEquals('bar', $column->getName());
        self::assertEquals('[bar]', $column->getQuotedName($sqlServerPlatform));
    }

    /**
     * @dataProvider getIsQuoted
     * @group DBAL-830
     */
    public function testIsQuoted($columnName, $isQuoted)
    {
        $type = Type::getType('string');
        $column = new Column($columnName, $type);

        self::assertSame($isQuoted, $column->isQuoted());
    }

    public function getIsQuoted()
    {
        return array(
            array('bar', false),
            array('`bar`', true),
            array('"bar"', true),
            array('[bar]', true),
        );
    }

    /**
     * @group DBAL-42
     */
    public function testColumnComment()
    {
        $column = new Column("bar", Type::getType('string'));
        self::assertNull($column->getComment());

        $column->setComment("foo");
        self::assertEquals("foo", $column->getComment());

        $columnArray = $column->toArray();
        self::assertArrayHasKey('comment', $columnArray);
        self::assertEquals('foo', $columnArray['comment']);
    }
}
