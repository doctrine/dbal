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
use Doctrine\DBAL\Tests\Types\InMemoryTypeProvider;
use Doctrine\DBAL\Types\Exception\TypeNotRegistered;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
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

    public function testToArrayWithType(): void
    {
        $expected = [
            'name' => 'foo',
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

        self::assertSame($expected, $this->createColumn()->toArray());
    }

    public function testToArray(): void
    {
        $expected = [
            'name' => 'foo',
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

        self::assertSame($expected, $this->createColumn()->toArray(true));
    }

    public function testSettingUnknownOptionIsStillSupported(): void
    {
        $column = Column::editor()
            ->setUnquotedName('foo')
            ->setTypeName(Types::STRING)
            ->create();

        $this->expectException(UnknownColumnOption::class);
        $this->expectExceptionMessage('The "unknown_option" column option is not supported.');

        $column->setOptions(['unknown_option' => 'bar']);
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

    /** @return list<array{string, bool}> */
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
            ->setTypeName(Types::STRING)
            ->create();
        self::assertSame('', $column->getComment());

        $column = $column->edit()
            ->setComment('foo')
            ->create();
        self::assertEquals('foo', $column->getComment());

        $columnArray = $column->toArray();
        self::assertArrayHasKey('comment', $columnArray);
        self::assertEquals('foo', $columnArray['comment']);
    }

    /** @throws Exception */
    public function testEmptyName(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6646');

        new Column('', Types::INTEGER);
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

    public function testPassingTypeInstanceToConstructorIsDeprecated(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7490');

        new Column('foo', Type::getTypeRegistry()->get(Types::STRING));
    }

    public function testGetTypeIsDeprecated(): void
    {
        $column = new Column('foo', Types::STRING);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7490');

        self::assertInstanceOf(StringType::class, $column->getType());
    }

    public function testGetTypeNameIsNotDeprecated(): void
    {
        $column = new Column('foo', Types::STRING);

        $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7490');

        self::assertSame(Types::STRING, $column->getTypeName());
    }

    public function testGetTypeUsesInjectedTypeRegistry(): void
    {
        $customType = new StringType();
        $registry   = new TypeRegistry([Types::STRING => $customType]);

        $column = new Column('foo', Types::STRING);
        $column->setTypeRegistry($registry);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7490');

        self::assertSame($customType, $column->getType());
        self::assertNotSame(Type::getType(Types::STRING), $column->getType());
    }

    public function testGetTypeFallsBackToGlobalRegistryWhenRegistryIsNull(): void
    {
        $column = new Column('foo', Types::STRING);
        $column->setTypeRegistry(null);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7490');

        self::assertSame(Type::getType(Types::STRING), $column->getType());
    }

    public function testSetTypeUsesInjectedTypeRegistryForNameLookup(): void
    {
        $customType = new StringType();
        $registry   = new TypeRegistry([Types::STRING => $customType]);

        $column = new Column('foo', Types::INTEGER);
        $column->setTypeRegistry($registry);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7381');

        $column->setType($customType);

        self::assertSame(Types::STRING, $column->getTypeName());
    }

    public function testGetTypeUsesInjectedCustomTypeProvider(): void
    {
        $customType = new StringType();
        $provider   = new InMemoryTypeProvider(['my_string' => $customType]);

        $column = new Column('foo', 'my_string');
        $column->setTypeRegistry($provider);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7490');

        self::assertSame($customType, $column->getType());
    }

    public function testSetTypeUsesInjectedCustomTypeProviderForNameLookup(): void
    {
        $customType = new StringType();
        $provider   = new InMemoryTypeProvider(['my_string' => $customType]);

        $column = new Column('foo', Types::INTEGER);
        $column->setTypeRegistry($provider);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7381');

        $column->setType($customType);

        self::assertSame('my_string', $column->getTypeName());
    }

    public function testSetTypeThrowsWhenTypeIsNotFoundInCustomTypeProvider(): void
    {
        $provider = new InMemoryTypeProvider(['my_string' => new StringType()]);

        $column = new Column('foo', Types::INTEGER);
        $column->setTypeRegistry($provider);

        $this->expectException(TypeNotRegistered::class);

        $column->setType(new IntegerType());
    }

    public function testSetPlatformOptionJsonb(): void
    {
        $column = new Column('jsonb', Types::JSON);

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
