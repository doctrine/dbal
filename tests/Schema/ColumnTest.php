<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\UnknownColumnOption;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ColumnTest extends TestCase
{
    use VerifyDeprecations;

    public function testGet(): void
    {
        $column = $this->createColumn();

        self::assertEquals(UnqualifiedName::unquoted('foo'), $column->getObjectName());
        self::assertSame(Type::getType(Types::STRING), $column->getType());

        self::assertEquals(200, $column->getLength());
        self::assertEquals(5, $column->getPrecision());
        self::assertEquals(2, $column->getScale());
        self::assertTrue($column->getUnsigned());
        self::assertFalse($column->getNotnull());
        self::assertTrue($column->getFixed());
        self::assertEquals('baz', $column->getDefault());

        self::assertEquals(['charset' => 'utf8', 'enumType' => self::class], $column->getPlatformOptions());
        self::assertTrue($column->hasPlatformOption('charset'));
        self::assertEquals('utf8', $column->getPlatformOption('charset'));
        self::assertFalse($column->hasPlatformOption('collation'));
        self::assertTrue($column->hasPlatformOption('enumType'));
        self::assertEquals(self::class, $column->getPlatformOption('enumType'));
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
            'values' => [],
            'charset' => 'utf8',
            'enumType' => self::class,
        ];

        self::assertSame($expected, $this->createColumn()->toArray());
    }

    public function testSettingUnknownOptionIsStillSupported(): void
    {
        $this->expectException(UnknownColumnOption::class);
        $this->expectExceptionMessage('The "unknown_option" column option is not supported.');

        new Column('foo', self::createStub(Type::class), ['unknown_option' => 'bar']);
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
        return Column::editor()
            ->setUnquotedName('foo')
            ->setTypeName(Types::STRING)
            ->setLength(200)
            ->setPrecision(5)
            ->setScale(2)
            ->setUnsigned(true)
            ->setNotNull(false)
            ->setFixed(true)
            ->setDefaultValue('baz')
            ->setCharset('utf8')
            ->setEnumType(self::class)
            ->create();
    }

    public function testQuotedColumnName(): void
    {
        $column = Column::editor()
            ->setQuotedName('bar')
            ->setTypeName(Types::STRING)
            ->create();

        $mysqlPlatform  = new MySQLPlatform();
        $sqlitePlatform = new SQLitePlatform();

        self::assertEquals('"bar"', $column->getObjectName()->toString());
        self::assertEquals('`bar`', $column->getQuotedName($mysqlPlatform));
        self::assertEquals('"bar"', $column->getQuotedName($sqlitePlatform));

        $column = Column::editor()
            ->setQuotedName('bar')
            ->setTypeName(Types::STRING)
            ->create();

        $sqlServerPlatform = new SQLServerPlatform();

        self::assertEquals('"bar"', $column->getObjectName()->toString());
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
        $column = Column::editor()
            ->setUnquotedName('bar')
            ->setType(Type::getType(Types::STRING))
            ->create();
        self::assertSame('', $column->getComment());

        $column->setComment('foo');
        self::assertEquals('foo', $column->getComment());

        $columnArray = $column->toArray();
        self::assertArrayHasKey('comment', $columnArray);
        self::assertEquals('foo', $columnArray['comment']);
    }

    /** @throws Exception */
    public function testEmptyName(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6646');

        new Column('', Type::getType(Types::INTEGER));
    }

    /** @throws Exception */
    public function testGetObjectName(): void
    {
        $column = Column::editor()
            ->setUnquotedName('id')
            ->setType(Type::getType(Types::INTEGER))
            ->create();

        self::assertEquals(Identifier::unquoted('id'), $column->getObjectName()->getIdentifier());
    }

    public function testSetPlatformOptionJsonb(): void
    {
        $column = new Column('jsonb', Type::getType(Types::JSON));

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6939');
        $column->setPlatformOption('jsonb', true);
    }

    public function testSetPlatformOptionsJsonb(): void
    {
        $column = new Column('jsonb', Type::getType(Types::JSON));

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6939');
        $column->setPlatformOptions(['jsonb' => true]);
    }
}
