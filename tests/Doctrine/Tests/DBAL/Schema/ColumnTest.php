<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

class ColumnTest extends TestCase
{
    public function testGet() : void
    {
        $column = $this->createColumn();

        self::assertEquals('foo', $column->getName());
        self::assertSame(Type::getType('string'), $column->getType());

        self::assertEquals(200, $column->getLength());
        self::assertEquals(5, $column->getPrecision());
        self::assertEquals(2, $column->getScale());
        self::assertTrue($column->getUnsigned());
        self::assertFalse($column->getNotNull());
        self::assertTrue($column->getFixed());
        self::assertEquals('baz', $column->getDefault());

        self::assertEquals(['foo' => 'bar'], $column->getPlatformOptions());
        self::assertTrue($column->hasPlatformOption('foo'));
        self::assertEquals('bar', $column->getPlatformOption('foo'));
        self::assertFalse($column->hasPlatformOption('bar'));

        self::assertEquals(['bar' => 'baz'], $column->getCustomSchemaOptions());
        self::assertTrue($column->hasCustomSchemaOption('bar'));
        self::assertEquals('baz', $column->getCustomSchemaOption('bar'));
        self::assertFalse($column->hasCustomSchemaOption('foo'));
    }

    public function testToArray() : void
    {
        $expected = [
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
            'bar' => 'baz',
        ];

        self::assertEquals($expected, $this->createColumn()->toArray());
    }

    /**
     * @group legacy
     * @expectedDeprecation The "unknown_option" column option is not supported, setting it is deprecated and will cause an error in Doctrine DBAL 3.0
     */
    public function testSettingUnknownOptionIsStillSupported() : void
    {
        $this->expectNotToPerformAssertions();

        new Column('foo', $this->createMock(Type::class), ['unknown_option' => 'bar']);
    }

    /**
     * @group legacy
     * @expectedDeprecation The "unknown_option" column option is not supported, setting it is deprecated and will cause an error in Doctrine DBAL 3.0
     */
    public function testOptionsShouldNotBeIgnored() : void
    {
        $col1 = new Column('bar', Type::getType(Types::INTEGER), ['unknown_option' => 'bar', 'notnull' => true]);
        self::assertTrue($col1->getNotnull());

        $col2 = new Column('bar', Type::getType(Types::INTEGER), ['unknown_option' => 'bar', 'notnull' => false]);
        self::assertFalse($col2->getNotnull());
    }

    public function createColumn() : Column
    {
        $options = [
            'length' => 200,
            'precision' => 5,
            'scale' => 2,
            'unsigned' => true,
            'notnull' => false,
            'fixed' => true,
            'default' => 'baz',
            'platformOptions' => ['foo' => 'bar'],
            'customSchemaOptions' => ['bar' => 'baz'],
        ];

        $string = Type::getType('string');

        return new Column('foo', $string, $options);
    }

    /**
     * @group DBAL-64
     * @group DBAL-830
     */
    public function testQuotedColumnName() : void
    {
        $string = Type::getType('string');
        $column = new Column('`bar`', $string, []);

        $mysqlPlatform  = new MySqlPlatform();
        $sqlitePlatform = new SqlitePlatform();

        self::assertEquals('bar', $column->getName());
        self::assertEquals('`bar`', $column->getQuotedName($mysqlPlatform));
        self::assertEquals('"bar"', $column->getQuotedName($sqlitePlatform));

        $column = new Column('[bar]', $string);

        $sqlServerPlatform = new SQLServerPlatform();

        self::assertEquals('bar', $column->getName());
        self::assertEquals('[bar]', $column->getQuotedName($sqlServerPlatform));
    }

    /**
     * @dataProvider getIsQuoted
     * @group DBAL-830
     */
    public function testIsQuoted(string $columnName, bool $isQuoted) : void
    {
        $type   = Type::getType('string');
        $column = new Column($columnName, $type);

        self::assertSame($isQuoted, $column->isQuoted());
    }

    /**
     * @return mixed[][]
     */
    public static function getIsQuoted() : iterable
    {
        return [
            ['bar', false],
            ['`bar`', true],
            ['"bar"', true],
            ['[bar]', true],
        ];
    }

    /**
     * @group DBAL-42
     */
    public function testColumnComment() : void
    {
        $column = new Column('bar', Type::getType('string'));
        self::assertNull($column->getComment());

        $column->setComment('foo');
        self::assertEquals('foo', $column->getComment());

        $columnArray = $column->toArray();
        self::assertArrayHasKey('comment', $columnArray);
        self::assertEquals('foo', $columnArray['comment']);
    }
}
