<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Collections;

use Doctrine\DBAL\Schema\Collections\Exception\ObjectAlreadyExists;
use Doctrine\DBAL\Schema\Collections\Exception\ObjectDoesNotExist;
use Doctrine\DBAL\Schema\Collections\UnqualifiedNamedObjectSet;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\NamedObject;
use PHPUnit\Framework\TestCase;

class UnqualifiedNamedObjectSetTest extends TestCase
{
    public function testInstantiationWithoutArguments(): void
    {
        $set = new UnqualifiedNamedObjectSet();

        self::assertTrue($set->isEmpty());
        self::assertEmpty($set->toList());
    }

    public function testInstantiationWithArguments(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject('object2', 2);

        $set = new UnqualifiedNamedObjectSet($object1, $object2);

        self::assertSame([$object1, $object2], $set->toList());
    }

    public function testAdd(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject('object2', 2);

        $set = new UnqualifiedNamedObjectSet($object1);

        $set->add($object2);
        self::assertSame([$object1, $object2], $set->toList());
    }

    public function testAddExistingObject(): void
    {
        $object = $this->createObject('object', 1);

        $set = new UnqualifiedNamedObjectSet($object);

        $this->expectException(ObjectAlreadyExists::class);

        $set->add($object);
    }

    public function testRemove(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject('object2', 2);

        $set = new UnqualifiedNamedObjectSet($object1, $object2);

        $set->remove($object1->getObjectName());
        self::assertSame([$object2], $set->toList());
    }

    public function testRemoveNonExistingObject(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject('object2', 2);

        $set = new UnqualifiedNamedObjectSet($object1);

        $this->expectException(ObjectDoesNotExist::class);

        $set->remove($object2->getObjectName());
    }

    public function testGetExistingObject(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject('object2', 2);

        $set = new UnqualifiedNamedObjectSet($object1, $object2);

        self::assertSame($object1, $set->get($object1->getObjectName()));
        self::assertSame($object2, $set->get($object2->getObjectName()));
    }

    public function testGetNonExistingObject(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject('object2', 2);

        $set = new UnqualifiedNamedObjectSet($object1);

        self::assertNull($set->get($object2->getObjectName()));
    }

    public function testModifyObjectWithoutRenaming(): void
    {
        $object11 = $this->createObject('object1', 11);
        $object12 = $this->createObject('object1', 12);
        $object2  = $this->createObject('object2', 2);

        $set = new UnqualifiedNamedObjectSet($object11, $object2);

        $set->modify(
            $object11->getObjectName(),
            static fn (NamedObject $object): NamedObject => $object12,
        );

        self::assertSame([$object12, $object2], $set->toList());
    }

    public function testModifyObjectWithRenaming(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject('object2', 2);
        $object3 = $this->createObject('object3', 3);

        $set = new UnqualifiedNamedObjectSet($object1, $object2);

        $set->modify(
            $object1->getObjectName(),
            static fn (NamedObject $object): NamedObject => $object3,
        );

        self::assertNull($set->get($object1->getObjectName()));
        self::assertSame($object3, $set->get($object3->getObjectName()));
        self::assertSame([$object3, $object2], $set->toList());
    }

    public function testModifyNonExistingObject(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject('object2', 2);

        $set = new UnqualifiedNamedObjectSet($object1);

        $this->expectException(ObjectDoesNotExist::class);

        $set->modify(
            $object2->getObjectName(),
            static fn (NamedObject $object): NamedObject => $object,
        );
    }

    public function testRenameToExistingName(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject('object2', 2);

        $set = new UnqualifiedNamedObjectSet($object1, $object2);

        $this->expectException(ObjectAlreadyExists::class);

        $set->modify(
            $object1->getObjectName(),
            static fn (NamedObject $object): NamedObject => $object2,
        );
    }

    /**
     * @param non-empty-string $name
     *
     * @return NamedObject<UnqualifiedName>
     */
    private function createObject(string $name, int $value): NamedObject
    {
        return new /** @template-implements NamedObject<UnqualifiedName> */
        class ($name, $value) implements NamedObject {
            private readonly UnqualifiedName $name;

            /** @param non-empty-string $name */
            public function __construct(string $name, private readonly int $value)
            {
                $this->name = UnqualifiedName::unquoted($name);
            }

            public function getObjectName(): UnqualifiedName
            {
                return $this->name;
            }

            public function getValue(): int
            {
                return $this->value;
            }
        };
    }
}
