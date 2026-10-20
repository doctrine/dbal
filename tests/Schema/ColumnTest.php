<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\InvalidIdentifier;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\TestCase;

class ColumnTest extends TestCase
{
    use VerifyDeprecations;

    public function testGet(): void
    {
        $column = $this->createColumn();

        self::assertEquals(UnqualifiedName::unquoted('foo'), $column->getObjectName());
        self::assertSame(Types::STRING, $column->getTypeName());

        self::assertEquals(200, $column->getLength());
        self::assertEquals(5, $column->getPrecision());
        self::assertEquals(2, $column->getScale());
        self::assertTrue($column->getUnsigned());
        self::assertFalse($column->getNotnull());
        self::assertTrue($column->getFixed());
        self::assertEquals('baz', $column->getDefault());

        self::assertEquals('utf8', $column->getCharset());
        self::assertEquals(self::class, $column->getEnumType());
        self::assertNull($column->getCollation());
    }

    public function testToArrayWithType(): void
    {
        $expected = [
            'name' => UnqualifiedName::unquoted('foo'),
            'typeName' => Types::STRING,
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
            'type' => Type::getType(Types::STRING),
            'charset' => 'utf8',
            'enumType' => self::class,
        ];

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7490');

        self::assertEquals($expected, $this->createColumn()->toArray());
    }

    public function testToArray(): void
    {
        $expected = [
            'name' => UnqualifiedName::unquoted('foo'),
            'typeName' => Types::STRING,
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

        $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7490');

        self::assertEquals($expected, $this->createColumn()->toArray(true));
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
        self::assertEquals('`bar`', $column->getObjectName()->toSQL($mysqlPlatform));
        self::assertEquals('"bar"', $column->getObjectName()->toSQL($sqlitePlatform));

        $column = Column::editor()
            ->setQuotedName('bar')
            ->setTypeName(Types::STRING)
            ->create();

        $sqlServerPlatform = new SQLServerPlatform();

        self::assertEquals('"bar"', $column->getObjectName()->toString());
        self::assertEquals('[bar]', $column->getObjectName()->toSQL($sqlServerPlatform));
    }

    public function testColumnComment(): void
    {
        $column = Column::editor()
            ->setUnquotedName('bar')
            ->setTypeName(Types::STRING)
            ->create();
        self::assertSame('', $column->getComment());

        $column = $column->edit()
            ->setComment('foo')
            ->create();
        self::assertEquals('foo', $column->getComment());

        $columnArray = $column->toArray(true);
        self::assertArrayHasKey('comment', $columnArray);
        self::assertEquals('foo', $columnArray['comment']);
    }

    /** @throws Exception */
    public function testEmptyName(): void
    {
        $this->expectException(InvalidIdentifier::class);

        Column::editor()
            ->setUnquotedName('') /** @phpstan-ignore argument.type */
            ->setTypeName(Types::INTEGER)
            ->create();
    }

    /** @throws Exception */
    public function testGetObjectName(): void
    {
        $column = Column::editor()
            ->setUnquotedName('id')
            ->setTypeName(Types::INTEGER)
            ->create();

        self::assertEquals(Identifier::unquoted('id'), $column->getObjectName()->getIdentifier());
    }

    public function testGetTypeIsDeprecated(): void
    {
        $column = Column::editor()
            ->setUnquotedName('foo')
            ->setTypeName(Types::STRING)
            ->create();

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7490');

        self::assertInstanceOf(StringType::class, $column->getType());
    }

    public function testGetTypeNameIsNotDeprecated(): void
    {
        $column = Column::editor()
            ->setUnquotedName('foo')
            ->setTypeName(Types::STRING)
            ->create();

        $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7490');

        self::assertSame(Types::STRING, $column->getTypeName());
    }
}
