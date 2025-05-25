<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Collections;

use Doctrine\DBAL\Schema\Collections\Exception\ObjectAlreadyExists;
use Doctrine\DBAL\Schema\Collections\Exception\ObjectDoesNotExist;
use Doctrine\DBAL\Schema\Collections\OptionallyUnqualifiedNamedObjectSet;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\OptionallyNamedObject;
use PHPUnit\Framework\TestCase;

class OptionallyUnqualifiedNamedObjectSetTest extends TestCase
{
    public function testInstantiationWithoutArguments(): void
    {
        $set = new OptionallyUnqualifiedNamedObjectSet();

        self::assertTrue($set->isEmpty());
        self::assertEmpty($set->toList());
    }

    public function testInstantiationWithArguments(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject(null, 2);

        $set = new OptionallyUnqualifiedNamedObjectSet($object1, $object2);

        self::assertSame([$object1, $object2], $set->toList());
    }

    public function testAdd(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject(null, 2);

        $set = new OptionallyUnqualifiedNamedObjectSet($object1);

        $set->add($object2);
        self::assertSame([$object1, $object2], $set->toList());
    }

    public function testAddExistingObject(): void
    {
        $object = $this->createObject('object', 1);

        $set = new OptionallyUnqualifiedNamedObjectSet($object);

        $this->expectException(ObjectAlreadyExists::class);

        $set->add($object);
    }

    public function testRemove(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject(null, 2);

        $set = new OptionallyUnqualifiedNamedObjectSet($object1, $object2);

        $set->remove(UnqualifiedName::unquoted('object1'));
        self::assertSame([$object2], $set->toList());
    }

    public function testRemoveNonExistingObject(): void
    {
        $object1 = $this->createObject('object1', 1);

        $set = new OptionallyUnqualifiedNamedObjectSet($object1);

        $this->expectException(ObjectDoesNotExist::class);

        $set->remove(UnqualifiedName::unquoted('object2'));
    }

    public function testGetExistingObject(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject(null, 2);
        $object3 = $this->createObject('object3', 3);

        $set = new OptionallyUnqualifiedNamedObjectSet($object1, $object2, $object3);

        self::assertSame($object1, $set->get(UnqualifiedName::unquoted('object1')));
        self::assertSame($object3, $set->get(UnqualifiedName::unquoted('object3')));
    }

    public function testGetNonExistingObject(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject(null, 2);

        $set = new OptionallyUnqualifiedNamedObjectSet($object1, $object2);

        self::assertNull($set->get(UnqualifiedName::unquoted('object3')));
    }

    public function testModifyObject(): void
    {
        $object11 = $this->createObject('object1', 11);
        $object12 = $this->createObject('object1', 12);
        $object2  = $this->createObject('object2', 2);

        $set = new OptionallyUnqualifiedNamedObjectSet($object11, $object2);

        $set->modify(
            UnqualifiedName::unquoted('object1'),
            static fn (OptionallyNamedObject $object): OptionallyNamedObject => $object12,
        );

        self::assertSame([$object12, $object2], $set->toList());
    }

    public function testModifyNonExistingObject(): void
    {
        $object1 = $this->createObject('object1', 1);

        $set = new OptionallyUnqualifiedNamedObjectSet($object1);

        $this->expectException(ObjectDoesNotExist::class);

        $set->modify(
            UnqualifiedName::unquoted('object2'),
            static fn (OptionallyNamedObject $object): OptionallyNamedObject => $object,
        );
    }

    public function testRenameToExistingName(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject(null, 2);
        $object3 = $this->createObject('object3', 3);

        $set = new OptionallyUnqualifiedNamedObjectSet($object1, $object2, $object3);

        $this->expectException(ObjectAlreadyExists::class);

        $set->modify(
            UnqualifiedName::unquoted('object1'),
            static fn (OptionallyNamedObject $object): OptionallyNamedObject => $object3,
        );
    }

    public function testRenameToNullName(): void
    {
        $object1 = $this->createObject('object1', 1);
        $object2 = $this->createObject(null, 2);
        $object3 = $this->createObject('object3', 3);

        $set = new OptionallyUnqualifiedNamedObjectSet($object1, $object2, $object3);

        $set->modify(
            UnqualifiedName::unquoted('object1'),
            static fn (OptionallyNamedObject $object): OptionallyNamedObject => $object2,
        );

        self::assertSame([$object2, $object2, $object3], $set->toList());
    }

    /**
     * @param ?non-empty-string $name
     *
     * @return OptionallyNamedObject<UnqualifiedName>
     */
    private function createObject(?string $name, int $value): OptionallyNamedObject
    {
        return new /** @template-implements OptionallyNamedObject<UnqualifiedName> */
        class ($name, $value) implements OptionallyNamedObject {
            private readonly ?UnqualifiedName $name;

            /** @param ?non-empty-string $name */
            public function __construct(?string $name, private readonly int $value)
            {
                $this->name = $name !== null ? UnqualifiedName::unquoted($name) : null;
            }

            public function getObjectName(): ?UnqualifiedName
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
