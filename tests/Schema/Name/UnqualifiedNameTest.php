<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Name;

use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use PHPUnit\Framework\TestCase;

class UnqualifiedNameTest extends TestCase
{
    public function testWithQualifier(): void
    {
        $identifier = Identifier::quoted('id');

        $name = new UnqualifiedName($identifier);

        self::assertSame($identifier, $name->getIdentifier());

        self::assertSame('"id"', $name->toString());
    }
}
