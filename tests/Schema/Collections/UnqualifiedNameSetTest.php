<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Collections;

use Doctrine\DBAL\Schema\Collections\Exception\SetAlreadyContainsName;
use Doctrine\DBAL\Schema\Collections\Exception\SetDoesNotContainName;
use Doctrine\DBAL\Schema\Collections\UnqualifiedNameSet;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use PHPUnit\Framework\TestCase;

class UnqualifiedNameSetTest extends TestCase
{
    public function testAdd(): void
    {
        $name1 = $this->createName('name1');
        $name2 = $this->createName('name2');

        $set = new UnqualifiedNameSet();

        $set->add($name1);

        self::assertTrue($set->contains($name1));
        self::assertFalse($set->contains($name2));
    }

    public function testAddExistingName(): void
    {
        $name = $this->createName('name');

        $set = new UnqualifiedNameSet();

        $set->add($name);

        $this->expectException(SetAlreadyContainsName::class);

        $set->add($name);
    }

    public function testRemove(): void
    {
        $name1 = $this->createName('name1');
        $name2 = $this->createName('name2');

        $set = new UnqualifiedNameSet();

        $set->add($name1);
        $set->add($name2);
        $set->remove($name1);

        self::assertFalse($set->contains($name1));
        self::assertTrue($set->contains($name2));
    }

    public function testRemoveNonExistingObject(): void
    {
        $name1 = $this->createName('name1');
        $name2 = $this->createName('name2');

        $set = new UnqualifiedNameSet();

        $set->add($name1);

        $this->expectException(SetDoesNotContainName::class);

        $set->remove($name2);
    }

    /** @param non-empty-string $name */
    private function createName(string $name): UnqualifiedName
    {
        return UnqualifiedName::unquoted($name);
    }
}
