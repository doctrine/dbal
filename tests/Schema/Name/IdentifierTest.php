<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Name;

use Doctrine\DBAL\Schema\Exception\InvalidIdentifier;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdentifierTest extends TestCase
{
    public function testIdentifierCannotBeEmpty(): void
    {
        $this->expectException(InvalidIdentifier::class);

        /** @phpstan-ignore argument.type */
        Identifier::unquoted('');
    }

    #[DataProvider('toStringProvider')]
    public function testToString(Identifier $identifier, string $expected): void
    {
        self::assertSame($expected, $identifier->toString());
    }

    /** @return iterable<array{Identifier, string}> */
    public static function toStringProvider(): iterable
    {
        yield [Identifier::unquoted('id'), 'id'];
        yield [Identifier::quoted('name'), '"name"'];
        yield [Identifier::quoted('"value"'), '"""value"""'];
    }

    public function testEqualsToSelf(): void
    {
        $identifier = Identifier::unquoted('id');

        self::assertTrue($identifier->equals($identifier, UnquotedIdentifierFolding::NONE));
    }

    #[DataProvider('equalIdentifierProvider')]
    public function testEqualsToOther(
        Identifier $identifier1,
        Identifier $identifier2,
        UnquotedIdentifierFolding $folding,
    ): void {
        self::assertTrue($identifier1->equals($identifier2, $folding));
        self::assertTrue($identifier2->equals($identifier1, $folding));
    }

    /** @return iterable<array{Identifier, Identifier, UnquotedIdentifierFolding}> */
    public static function equalIdentifierProvider(): iterable
    {
        yield [
            Identifier::unquoted('id'),
            Identifier::unquoted('id'),
            UnquotedIdentifierFolding::NONE,
        ];

        yield [
            Identifier::quoted('id'),
            Identifier::quoted('id'),
            UnquotedIdentifierFolding::NONE,
        ];

        yield [
            Identifier::quoted('id'),
            Identifier::unquoted('ID'),
            UnquotedIdentifierFolding::LOWER,
        ];

        yield [
            Identifier::quoted('ID'),
            Identifier::unquoted('id'),
            UnquotedIdentifierFolding::UPPER,
        ];
    }

    #[DataProvider('unequalIdentifierProvider')]
    public function testNonEqualsToOther(
        Identifier $identifier1,
        Identifier $identifier2,
        UnquotedIdentifierFolding $folding,
    ): void {
        self::assertFalse($identifier1->equals($identifier2, $folding));
        self::assertFalse($identifier2->equals($identifier1, $folding));
    }

    /** @return iterable<array{Identifier, Identifier, UnquotedIdentifierFolding}> */
    public static function unequalIdentifierProvider(): iterable
    {
        yield [
            Identifier::unquoted('foo'),
            Identifier::unquoted('bar'),
            UnquotedIdentifierFolding::NONE,
        ];

        yield [
            Identifier::unquoted('id'),
            Identifier::unquoted('ID'),
            UnquotedIdentifierFolding::NONE,
        ];

        yield [
            Identifier::quoted('id'),
            Identifier::quoted('ID'),
            UnquotedIdentifierFolding::LOWER,
        ];

        yield [
            Identifier::quoted('ID'),
            Identifier::quoted('id'),
            UnquotedIdentifierFolding::UPPER,
        ];
    }
}
