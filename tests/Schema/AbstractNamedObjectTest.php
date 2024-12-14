<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\AbstractNamedObject;
use Doctrine\DBAL\Schema\AbstractOptionallyNamedObject;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Exception\NameIsNotInitialized;
use Doctrine\DBAL\Schema\Name;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\TestCase;

class AbstractNamedObjectTest extends TestCase
{
    use VerifyDeprecations;

    public function testEmptyName(): void
    {
        $this->expectException(InvalidName::class);

        new /** @extends AbstractNamedObject<Name> */
        class ('') extends AbstractNamedObject {
        };
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
