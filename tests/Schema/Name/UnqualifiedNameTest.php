<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Name;

use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use PHPUnit\Framework\TestCase;

class UnqualifiedNameTest extends TestCase
{
    public function testQuoted(): void
    {
        $name = UnqualifiedName::quoted('id');

        $identifier = $name->getIdentifier();

        self::assertTrue($identifier->isQuoted());
        self::assertEquals('id', $identifier->getValue());

        self::assertSame('"id"', $name->toString());
    }

    public function testUnquoted(): void
    {
        $name = UnqualifiedName::unquoted('id');

        $identifier = $name->getIdentifier();

        self::assertFalse($identifier->isQuoted());
        self::assertEquals('id', $identifier->getValue());

        self::assertSame('id', $name->toString());
    }

    public function testEqualsToSelf(): void
    {
        $name = UnqualifiedName::unquoted('id');

        self::assertTrue($name->equals($name, UnquotedIdentifierFolding::NONE));
    }

    public function testEqualsToOther(): void
    {
        $name1 = UnqualifiedName::unquoted('id');
        $name2 = UnqualifiedName::unquoted('id');

        self::assertTrue($name1->equals($name2, UnquotedIdentifierFolding::NONE));
        self::assertTrue($name2->equals($name1, UnquotedIdentifierFolding::NONE));
    }

    public function testNotEqualsToOther(): void
    {
        $name1 = UnqualifiedName::unquoted('id');
        $name2 = UnqualifiedName::unquoted('name');

        self::assertFalse($name1->equals($name2, UnquotedIdentifierFolding::NONE));
        self::assertFalse($name2->equals($name1, UnquotedIdentifierFolding::NONE));
    }
}
