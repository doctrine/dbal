<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Name;

use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use PHPUnit\Framework\TestCase;

class OptionallyQualifiedNameTest extends TestCase
{
    public function testWithQualifier(): void
    {
        $unqualifiedName = Identifier::quoted('customers');
        $qualifier       = Identifier::unquoted('inventory');

        $name = new OptionallyQualifiedName($unqualifiedName, $qualifier);

        self::assertSame($unqualifiedName, $name->getUnqualifiedName());
        self::assertSame($qualifier, $name->getQualifier());

        self::assertSame('inventory."customers"', $name->toString());
    }

    public function testWithoutQualifier(): void
    {
        $unqualifiedName = Identifier::unquoted('users');

        $name = new OptionallyQualifiedName($unqualifiedName, null);

        self::assertSame($unqualifiedName, $name->getUnqualifiedName());
        self::assertNull($name->getQualifier());

        self::assertSame('users', $name->toString());
    }
}
