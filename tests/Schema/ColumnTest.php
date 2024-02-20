<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\UnknownColumnOption;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ColumnTest extends TestCase
{
    public function testGet(): void
    {
        $column = $this->createColumn();

        self::assertEquals('foo', $column->getName());
        self::assertSame(Type::getType(Types::STRING), $column->getType());

        self::assertEquals(200, $column->getLength());
        self::assertEquals(5, $column->getPrecision());
        self::assertEquals(2, $column->getScale());
        self::assertTrue($column->getUnsigned());
        self::assertFalse($column->getNotnull());
        self::assertTrue($column->getFixed());
        self::assertEquals('baz', $column->getDefault());

        self::assertEquals(['foo' => 'bar'], $column->getPlatformOptions());
        self::assertTrue($column->hasPlatformOption('foo'));
        self::assertEquals('bar', $column->getPlatformOption('foo'));
        self::assertFalse($column->hasPlatformOption('bar'));
    }

    public function testToArray(): void
    {
        $expected = [
            'name' => 'foo',
            'type' => Type::getType(Types::STRING),
            'default' => 'baz',
            'notnull' => false,
            'length' => 200,
            'precision' => 5,
            'scale' => 2,
            'fixed' => true,
            'unsigned' => true,
            'autoincrement' => false,
            'columnDefinition' => null,
            'comment' => '',
            'foo' => 'bar',
        ];

        self::assertSame($expected, $this->createColumn()->toArray());
    }

    public function testSettingUnknownOptionIsStillSupported(): void
    {
        $this->expectException(UnknownColumnOption::class);
        $this->expectExceptionMessage('The "unknown_option" column option is not supported.');

        new Column('foo', $this->createMock(Type::class), ['unknown_option' => 'bar']);
    }

    public function testOptionsShouldNotBeIgnored(): void
    {
        $this->expectException(UnknownColumnOption::class);
        $this->expectExceptionMessage('The "unknown_option" column option is not supported.');

        $col1 = new Column('bar', Type::getType(Types::INTEGER), ['unknown_option' => 'bar', 'notnull' => true]);
        self::assertTrue($col1->getNotnull());

        $col2 = new Column('bar', Type::getType(Types::INTEGER), ['unknown_option' => 'bar', 'notnull' => false]);
        self::assertFalse($col2->getNotnull());
    }

    public function createColumn(): Column
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
        ];

        $string = Type::getType(Types::STRING);

        return new Column('foo', $string, $options);
    }

    public function testQuotedColumnName(): void
    {
        $string = Type::getType(Types::STRING);
        $column = new Column('`bar`', $string, []);

        $mysqlPlatform  = new MySQLPlatform();
        $sqlitePlatform = new SQLitePlatform();

        self::assertEquals('bar', $column->getName());
        self::assertEquals('`bar`', $column->getQuotedName($mysqlPlatform));
        self::assertEquals('"bar"', $column->getQuotedName($sqlitePlatform));

        $column = new Column('[bar]', $string);

        $sqlServerPlatform = new SQLServerPlatform();

        self::assertEquals('bar', $column->getName());
        self::assertEquals('[bar]', $column->getQuotedName($sqlServerPlatform));
    }

    #[DataProvider('getIsQuoted')]
    public function testIsQuoted(string $columnName, bool $isQuoted): void
    {
        $type   = Type::getType(Types::STRING);
        $column = new Column($columnName, $type);

        self::assertSame($isQuoted, $column->isQuoted());
    }

    /** @return mixed[][] */
    public static function getIsQuoted(): iterable
    {
        return [
            ['bar', false],
            ['`bar`', true],
            ['"bar"', true],
            ['[bar]', true],
        ];
    }

    public function testColumnComment(): void
    {
        $column = new Column('bar', Type::getType(Types::STRING));
        self::assertSame('', $column->getComment());

        $column->setComment('foo');
        self::assertEquals('foo', $column->getComment());

        $columnArray = $column->toArray();
        self::assertArrayHasKey('comment', $columnArray);
        self::assertEquals('foo', $columnArray['comment']);
    }
}
