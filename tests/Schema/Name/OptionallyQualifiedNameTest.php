<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Name;

use Doctrine\DBAL\Schema\Exception\IncomparableNames;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OptionallyQualifiedNameTest extends TestCase
{
    public function testQualifiedQuoted(): void
    {
        $name = OptionallyQualifiedName::quoted('customers', 'inventory');

        $unqualifiedName = $name->getUnqualifiedName();
        self::assertTrue($unqualifiedName->isQuoted());
        self::assertEquals('customers', $unqualifiedName->getValue());

        $qualifier = $name->getQualifier();
        self::assertNotNull($qualifier);
        self::assertTrue($qualifier->isQuoted());
        self::assertEquals('inventory', $qualifier->getValue());

        self::assertSame('"inventory"."customers"', $name->toString());
    }

    public function testUnqualifiedQuoted(): void
    {
        $name = OptionallyQualifiedName::quoted('customers');

        $unqualifiedName = $name->getUnqualifiedName();
        self::assertTrue($unqualifiedName->isQuoted());
        self::assertEquals('customers', $unqualifiedName->getValue());

        self::assertNull($name->getQualifier());

        self::assertSame('"customers"', $name->toString());
    }

    public function testQualifiedUnquoted(): void
    {
        $name = OptionallyQualifiedName::unquoted('customers', 'inventory');

        $unqualifiedName = $name->getUnqualifiedName();
        self::assertFalse($unqualifiedName->isQuoted());
        self::assertEquals('customers', $unqualifiedName->getValue());

        $qualifier = $name->getQualifier();
        self::assertNotNull($qualifier);
        self::assertFalse($qualifier->isQuoted());
        self::assertEquals('inventory', $qualifier->getValue());

        self::assertSame('inventory.customers', $name->toString());
    }

    public function testUnqualifiedUnquoted(): void
    {
        $name = OptionallyQualifiedName::unquoted('customers');

        $unqualifiedName = $name->getUnqualifiedName();
        self::assertFalse($unqualifiedName->isQuoted());
        self::assertEquals('customers', $unqualifiedName->getValue());

        self::assertNull($name->getQualifier());

        self::assertSame('customers', $name->toString());
    }

    public function testEqualsToSelf(): void
    {
        $name = OptionallyQualifiedName::unquoted('user.id');

        self::assertTrue($name->equals($name, UnquotedIdentifierFolding::NONE));
    }

    #[DataProvider('equalNameProvider')]
    public function testEqualsNames(OptionallyQualifiedName $name1, OptionallyQualifiedName $name2): void
    {
        self::assertTrue($name2->equals($name1, UnquotedIdentifierFolding::NONE));
        self::assertTrue($name1->equals($name2, UnquotedIdentifierFolding::NONE));
    }

    /** @return iterable<array{OptionallyQualifiedName, OptionallyQualifiedName}> */
    public static function equalNameProvider(): iterable
    {
        yield [
            OptionallyQualifiedName::unquoted('id'),
            OptionallyQualifiedName::unquoted('id'),
        ];

        yield [
            OptionallyQualifiedName::unquoted('id', 'user'),
            OptionallyQualifiedName::unquoted('id', 'user'),
        ];
    }

    #[DataProvider('unequalNameProvider')]
    public function testUnequalsNames(OptionallyQualifiedName $name1, OptionallyQualifiedName $name2): void
    {
        self::assertFalse($name1->equals($name2, UnquotedIdentifierFolding::NONE));
        self::assertFalse($name2->equals($name1, UnquotedIdentifierFolding::NONE));
    }

    /** @return iterable<array{OptionallyQualifiedName, OptionallyQualifiedName}> */
    public static function unequalNameProvider(): iterable
    {
        yield [
            OptionallyQualifiedName::unquoted('id'),
            OptionallyQualifiedName::unquoted('name'),
        ];

        yield [
            OptionallyQualifiedName::unquoted('id', 'user'),
            OptionallyQualifiedName::unquoted('name', 'user'),
        ];

        yield [
            OptionallyQualifiedName::unquoted('id', 'user'),
            OptionallyQualifiedName::unquoted('id', 'order'),
        ];
    }

    #[DataProvider('incomparableNameProvider')]
    public function testIncomparableNames(OptionallyQualifiedName $name1, OptionallyQualifiedName $name2): void
    {
        $this->expectException(IncomparableNames::class);
        $name1->equals($name2, UnquotedIdentifierFolding::NONE);
    }

    /** @return iterable<array{OptionallyQualifiedName, OptionallyQualifiedName}> */
    public static function incomparableNameProvider(): iterable
    {
        yield [
            OptionallyQualifiedName::unquoted('id'),
            OptionallyQualifiedName::unquoted('id', 'user'),
        ];

        yield [
            OptionallyQualifiedName::unquoted('id', 'user'),
            OptionallyQualifiedName::unquoted('id'),
        ];
    }
}
