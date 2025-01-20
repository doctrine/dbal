<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Name;

use Doctrine\DBAL\Schema\Name\UnqualifiedName;
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
}
