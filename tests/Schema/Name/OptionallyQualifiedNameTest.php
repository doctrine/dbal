<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Name;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
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
}
