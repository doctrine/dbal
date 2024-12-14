<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Name;

use Doctrine\DBAL\Schema\Exception\InvalidIdentifier;
use Doctrine\DBAL\Schema\Name\Identifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdentifierTest extends TestCase
{
    public function testIdentifierCannotBeEmpty(): void
    {
        $this->expectException(InvalidIdentifier::class);

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
}
