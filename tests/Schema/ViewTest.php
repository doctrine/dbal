<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\View;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    use VerifyDeprecations;

    public function testEmptyName(): void
    {
        $this->expectException(InvalidName::class);

        new View('', '');
    }

    public function testOverqualifiedName(): void
    {
        $this->expectException(InvalidName::class);

        new View('warehouse.inventory.available_products', '');
    }

    /** @throws Exception */
    public function testGetUnqualifiedObjectName(): void
    {
        $view = new View('active_users', '');
        $name = $view->getObjectName();

        self::assertEquals(Identifier::unquoted('active_users'), $name->getUnqualifiedName());
        self::assertNull($name->getQualifier());
    }

    /** @throws Exception */
    public function testGetQualifiedObjectName(): void
    {
        $view = new View('inventory.available_products', '');
        $name = $view->getObjectName();

        self::assertEquals(Identifier::unquoted('available_products'), $name->getUnqualifiedName());
        self::assertEquals(Identifier::unquoted('inventory'), $name->getQualifier());
    }
}
