<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\AbstractOptionallyNamedObject;
use Doctrine\DBAL\Schema\Exception\NameIsNotInitialized;
use Doctrine\DBAL\Schema\Name;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AbstractOptionallyNamedObjectTest extends TestCase
{
    #[DataProvider('emptyNameProvider')]
    public function testEmptyName(?string $name): void
    {
        $object = new /** @extends AbstractOptionallyNamedObject<Name> */
        class ($name) extends AbstractOptionallyNamedObject {
        };

        self::assertNull($object->getObjectName());
    }

    /** @return iterable<string,array{?string}> */
    public static function emptyNameProvider(): iterable
    {
        yield 'empty-string' => [''];
        yield 'empty-null' => [null];
    }

    public function testAccessToUninitializedName(): void
    {
        $object = new /** @extends AbstractOptionallyNamedObject<Name> */
        class () extends AbstractOptionallyNamedObject {
            /** @phpstan-ignore constructor.missingParentCall */
            public function __construct()
            {
            }
        };

        $this->expectException(NameIsNotInitialized::class);
        $object->getObjectName();
    }
}
