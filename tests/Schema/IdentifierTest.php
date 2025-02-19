<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Identifier;
use PHPUnit\Framework\TestCase;

class IdentifierTest extends TestCase
{
    public function testEmptyName(): void
    {
        $this->expectException(InvalidName::class);

        new Identifier('');
    }

    /** @throws Exception */
    public function testGetObjectName(): void
    {
        $identifier = new Identifier('warehouse.inventory.products.id');
        $name       = $identifier->getObjectName();

        self::assertEquals([
            \Doctrine\DBAL\Schema\Name\Identifier::unquoted('warehouse'),
            \Doctrine\DBAL\Schema\Name\Identifier::unquoted('inventory'),
            \Doctrine\DBAL\Schema\Name\Identifier::unquoted('products'),
            \Doctrine\DBAL\Schema\Name\Identifier::unquoted('id'),
        ], $name->getIdentifiers());
    }
}
